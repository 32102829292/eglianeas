<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminConfidentialityAcknowledged;
use App\Models\KaizenConcern;
use App\Models\PriorityItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Covers the six Kaizen / Priority Item pages an admin actually navigates
 * through: list -> view -> edit for both modules.
 *
 * The edit and create screens render their option lists from the model's
 * authoritative constants (KaizenConcern::STATUSES, PriorityItem::TYPES /
 * PRIORITIES / STATUSES). If a controller forgets to hand one of those to the
 * view, Blade raises "Undefined variable" and the page 500s. These tests pin
 * both the HTTP status and the actual rendered content so the regression
 * cannot come back silently.
 */
class KaizenPriorityPageTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        $isAdmin = $role === User::ROLE_ADMIN;

        return User::create([
            'name' => ucfirst($role).' Tester',
            'email' => $role.'.'.uniqid().'@example.com',
            'password' => bcrypt('secret'),
            'role' => $role,
            'email_verified_at' => now(),
            'confidentiality_acknowledged_at' => now(),
            'confidentiality_ack_version' => EnsureAdminConfidentialityAcknowledged::CURRENT_VERSION,
        ]);
    }

    private function concern(?User $assignee, string $status = KaizenConcern::STATUS_PENDING): KaizenConcern
    {
        return KaizenConcern::create([
            'date_identified' => now()->subDays(3),
            'challenge' => 'Delayed supplier invoices',
            'recommended_solution' => 'Introduce a weekly invoice checklist.',
            'target_date' => now()->addDays(10),
            'implementation_date' => null,
            'assigned_staff_id' => $assignee?->id,
            'status' => $status,
            'notes' => 'Tracked by finance.',
            'created_by' => $this->user(User::ROLE_ADMIN)->id,
        ]);
    }

    private function item(?User $assignee, string $type = PriorityItem::TYPE_PRIORITY_TASK, string $priority = PriorityItem::PRIORITY_HIGH): PriorityItem
    {
        return PriorityItem::create([
            'task_lesson' => 'update the book',
            'type' => $type,
            'description' => 'Reconcile the cash book before month end.',
            'priority' => $priority,
            'assigned_staff_id' => $assignee?->id,
            'due_date' => now()->addDays(7),
            'status' => PriorityItem::STATUS_PENDING,
            'notes' => 'Coordinate with the auditor.',
            'created_by' => $this->user(User::ROLE_ADMIN)->id,
        ]);
    }

    /* ---------------------------------------------------------------
     | 1. Every screen loads
     * -------------------------------------------------------------- */

    public function test_all_six_admin_pages_load_without_error(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $concern = $this->concern(null);
        $item = $this->item(null);

        $urls = [
            route('admin.kaizen-concerns.index'),
            route('admin.kaizen-concerns.show', $concern),
            route('admin.kaizen-concerns.create'),
            route('admin.kaizen-concerns.edit', $concern),
            route('admin.priority-items.index'),
            route('admin.priority-items.show', $item),
            route('admin.priority-items.create'),
            route('admin.priority-items.edit', $item),
        ];

        foreach ($urls as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    /* ---------------------------------------------------------------
     | 2. Kaizen edit: options come from the model, values are kept
     * -------------------------------------------------------------- */

    public function test_kaizen_edit_renders_every_status_option_and_preserves_the_current_one(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $concern = $this->concern(null, KaizenConcern::STATUS_PENDING);

        $response = $this->actingAs($admin)->get(route('admin.kaizen-concerns.edit', $concern));
        $response->assertOk();

        foreach (KaizenConcern::STATUSES as $value => $label) {
            $response->assertSee('value="'.$value.'"', false);
            $response->assertSee($label, false);
        }

        // The stored status must be the selected option, not merely present.
        $this->assertSelectedOption($response->getContent(), 'status', KaizenConcern::STATUS_PENDING);
    }

    public function test_kaizen_create_renders_every_status_option(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);

        $response = $this->actingAs($admin)->get(route('admin.kaizen-concerns.create'));
        $response->assertOk();

        foreach (array_keys(KaizenConcern::STATUSES) as $value) {
            $response->assertSee('value="'.$value.'"', false);
        }
    }

    /* ---------------------------------------------------------------
     | 3. Priority edit: types / priorities / statuses + preserved values
     * -------------------------------------------------------------- */

    public function test_priority_edit_renders_types_priorities_and_statuses_and_preserves_values(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $item = $this->item(null, PriorityItem::TYPE_PRIORITY_TASK, PriorityItem::PRIORITY_HIGH);

        $response = $this->actingAs($admin)->get(route('admin.priority-items.edit', $item));
        $response->assertOk();

        foreach (PriorityItem::TYPES as $value => $label) {
            $response->assertSee('value="'.$value.'"', false);
            $response->assertSee($label, false);
        }
        foreach (array_keys(PriorityItem::PRIORITIES) as $value) {
            $response->assertSee('value="'.$value.'"', false);
        }
        foreach (array_keys(PriorityItem::STATUSES) as $value) {
            $response->assertSee('value="'.$value.'"', false);
        }

        $this->assertSelectedOption($response->getContent(), 'type', PriorityItem::TYPE_PRIORITY_TASK);
        $this->assertSelectedOption($response->getContent(), 'priority', PriorityItem::PRIORITY_HIGH);
        $this->assertSelectedOption($response->getContent(), 'status', PriorityItem::STATUS_PENDING);
    }

    /**
     * Lesson Learned must stay a TYPE inside Priority List / To-Do List and
     * not become a separate module.
     */
    public function test_lesson_learned_remains_a_priority_item_type(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $item = $this->item(null, PriorityItem::TYPE_LESSON_LEARNED);

        $response = $this->actingAs($admin)->get(route('admin.priority-items.edit', $item));
        $response->assertOk();
        $this->assertSelectedOption($response->getContent(), 'type', PriorityItem::TYPE_LESSON_LEARNED);

        // And it is offered in the list filter / create screen too.
        $this->actingAs($admin)->get(route('admin.priority-items.index'))
            ->assertOk()
            ->assertSee(PriorityItem::TYPES[PriorityItem::TYPE_LESSON_LEARNED], false);
    }

    public function test_priority_create_renders_types_priorities_and_statuses(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);

        $response = $this->actingAs($admin)->get(route('admin.priority-items.create'));
        $response->assertOk();

        foreach (array_keys(PriorityItem::TYPES) as $value) {
            $response->assertSee('value="'.$value.'"', false);
        }
        foreach (array_keys(PriorityItem::PRIORITIES) as $value) {
            $response->assertSee('value="'.$value.'"', false);
        }
        foreach (array_keys(PriorityItem::STATUSES) as $value) {
            $response->assertSee('value="'.$value.'"', false);
        }
    }

    /* ---------------------------------------------------------------
     | 4. Populated values (nothing lost by merely opening Edit)
     * -------------------------------------------------------------- */

    public function test_priority_edit_populates_every_stored_value(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $staff = $this->user(User::ROLE_STAFF);
        $staff->update(['name' => 'Angeli Gabaut']);

        $item = $this->item($staff);
        $item->forceFill([
            'task_lesson' => 'update the book',
            'due_date' => '2026-09-30',
            'description' => 'Reconcile the cash book before month end.',
            'notes' => 'Coordinate with the auditor.',
        ])->save();

        $html = $this->actingAs($admin)->get(route('admin.priority-items.edit', $item))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('value="update the book"', $html);
        $this->assertStringContainsString('Reconcile the cash book before month end.', $html);
        $this->assertStringContainsString('Coordinate with the auditor.', $html);
        $this->assertStringContainsString('2026-09-30', $html);
        $this->assertStringContainsString('Angeli Gabaut', $html);
        $this->assertSelectedOption($html, 'assigned_staff_id', (string) $staff->id);
    }

    public function test_opening_edit_does_not_mutate_the_record(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $item = $this->item($admin, PriorityItem::TYPE_TODO, PriorityItem::PRIORITY_URGENT);
        $before = $this->scalarState($item);

        $this->actingAs($admin)->get(route('admin.priority-items.edit', $item))->assertOk();

        $this->assertSame($before, $this->scalarState($item->fresh()));
    }

    /* ---------------------------------------------------------------
     | 5. Save + persistence
     * -------------------------------------------------------------- */

    public function test_priority_edit_saves_and_the_detail_page_reflects_the_change(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $item = $this->item($admin);

        $this->actingAs($admin)->put(route('admin.priority-items.update', $item), [
            'task_lesson' => 'reconcile the book',
            'type' => PriorityItem::TYPE_LESSON_LEARNED,
            'description' => 'Updated description.',
            'priority' => PriorityItem::PRIORITY_URGENT,
            'assigned_staff_id' => '',
            'due_date' => '2026-10-15',
            'status' => PriorityItem::STATUS_IN_PROGRESS,
            'notes' => 'Updated notes.',
        ])->assertRedirect();

        $item->refresh();
        $this->assertSame(PriorityItem::TYPE_LESSON_LEARNED, $item->type);
        $this->assertSame(PriorityItem::PRIORITY_URGENT, $item->priority);
        $this->assertSame(PriorityItem::STATUS_IN_PROGRESS, $item->status);
        $this->assertNull($item->assigned_staff_id);

        // Detail page shows the new state.
        $this->actingAs($admin)->get(route('admin.priority-items.show', $item))
            ->assertOk()
            ->assertSee('In Progress');

        // Re-opening Edit keeps the saved values selected.
        $html = $this->actingAs($admin)->get(route('admin.priority-items.edit', $item))
            ->assertOk()
            ->getContent();
        $this->assertSelectedOption($html, 'type', PriorityItem::TYPE_LESSON_LEARNED);
        $this->assertSelectedOption($html, 'priority', PriorityItem::PRIORITY_URGENT);
        $this->assertSelectedOption($html, 'status', PriorityItem::STATUS_IN_PROGRESS);
    }

    public function test_kaizen_edit_saves_and_the_detail_page_reflects_the_change(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $concern = $this->concern($admin);

        $this->actingAs($admin)->put(route('admin.kaizen-concerns.update', $concern), [
            'date_identified' => '2026-09-01',
            'challenge' => 'Updated challenge',
            'recommended_solution' => 'Updated solution',
            'target_date' => '2026-11-30',
            'implementation_date' => '2026-10-01',
            'assigned_staff_id' => '',
            'status' => KaizenConcern::STATUS_IN_PROGRESS,
            'notes' => 'Updated notes.',
        ])->assertRedirect();

        $concern->refresh();
        $this->assertSame(KaizenConcern::STATUS_IN_PROGRESS, $concern->status);

        $this->actingAs($admin)->get(route('admin.kaizen-concerns.show', $concern))
            ->assertOk()
            ->assertSee('In Progress');

        $html = $this->actingAs($admin)->get(route('admin.kaizen-concerns.edit', $concern))
            ->assertOk()
            ->getContent();
        $this->assertSelectedOption($html, 'status', KaizenConcern::STATUS_IN_PROGRESS);
    }

    public function test_invalid_option_is_rejected_by_validation(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $item = $this->item($admin);

        $this->actingAs($admin)->put(route('admin.priority-items.update', $item), [
            'task_lesson' => 'x',
            'type' => 'not_a_real_type',
            'priority' => PriorityItem::PRIORITY_HIGH,
            'status' => PriorityItem::STATUS_PENDING,
        ])->assertSessionHasErrors('type');

        $concern = $this->concern($admin);
        $this->actingAs($admin)->put(route('admin.kaizen-concerns.update', $concern), [
            'date_identified' => '2026-09-01',
            'challenge' => 'x',
            'recommended_solution' => 'y',
            'status' => 'not_a_real_status',
        ])->assertSessionHasErrors('status');
    }

    /* ---------------------------------------------------------------
     | 6. Business rules: unassigned visibility, client exclusion
     * -------------------------------------------------------------- */

    public function test_unassigned_items_are_visible_to_staff_supervisor_and_admin_but_not_clients(): void
    {
        $concern = $this->concern(null);
        $item = $this->item(null);

        $staff = $this->user(User::ROLE_STAFF);
        $supervisor = $this->user(User::ROLE_SUPERVISOR);
        $admin = $this->user(User::ROLE_ADMIN);
        $client = $this->user(User::ROLE_CLIENT);

        foreach ([$staff, $supervisor, $admin] as $viewer) {
            $this->actingAs($viewer)->get(route('admin.kaizen-concerns.index'))->assertOk()->assertSee('Delayed supplier invoices');
            $this->actingAs($viewer)->get(route('admin.kaizen-concerns.show', $concern))->assertOk();
            $this->actingAs($viewer)->get(route('admin.priority-items.index'))->assertOk()->assertSee('update the book');
            $this->actingAs($viewer)->get(route('admin.priority-items.show', $item))->assertOk();
        }

        // Clients are refused by the role middleware on every route.
        foreach ([
            route('admin.kaizen-concerns.index'),
            route('admin.kaizen-concerns.show', $concern),
            route('admin.kaizen-concerns.create'),
            route('admin.kaizen-concerns.edit', $concern),
            route('admin.priority-items.index'),
            route('admin.priority-items.show', $item),
            route('admin.priority-items.create'),
            route('admin.priority-items.edit', $item),
        ] as $url) {
            $this->actingAs($client)->get($url)->assertForbidden();
        }

        // Model-level guard agrees for a client even if a route is added later.
        $this->assertFalse($concern->isVisibleTo($client));
        $this->assertFalse($item->isVisibleTo($client));
    }

    public function test_items_assigned_to_another_staff_are_not_visible_to_that_staff(): void
    {
        $owner = $this->user(User::ROLE_STAFF);
        $other = $this->user(User::ROLE_STAFF);
        $concern = $this->concern($owner);
        $item = $this->item($owner);

        $this->actingAs($other)->get(route('admin.kaizen-concerns.show', $concern))->assertForbidden();
        $this->actingAs($other)->get(route('admin.priority-items.show', $item))->assertForbidden();
    }

    public function test_records_assigned_to_staff_are_hidden_from_supervisors(): void
    {
        $owner = $this->user(User::ROLE_STAFF);
        $supervisor = $this->user(User::ROLE_SUPERVISOR);
        $concern = $this->concern($owner);
        $item = $this->item($owner);

        $this->actingAs($supervisor)->get(route('admin.kaizen-concerns.show', $concern))->assertForbidden();
        $this->actingAs($supervisor)->get(route('admin.priority-items.show', $item))->assertForbidden();

        $this->assertFalse($concern->isVisibleTo($supervisor));
        $this->assertFalse($item->isVisibleTo($supervisor));
    }

    /**
     * Documents the current list scope: scopeVisibleTo only surfaces unassigned
     * records that are still pending/in-progress, while isVisibleTo allows any
     * unassigned record to be opened directly. Unassigned completed/overdue
     * records are therefore reachable by URL but absent from the staff list.
     */
    public function test_unassigned_closed_records_are_absent_from_the_list_but_reachable_directly(): void
    {
        $staff = $this->user(User::ROLE_STAFF);
        $supervisor = $this->user(User::ROLE_SUPERVISOR);

        $closedConcern = $this->concern(null, KaizenConcern::STATUS_COMPLETED);
        $closedConcern->update(['challenge' => 'Closed challenge marker']);

        // Distinct text, because the list page's create form reuses a placeholder.
        $closedItem = $this->item(null);
        $closedItem->update([
            'task_lesson' => 'Closed lesson marker',
            'status' => PriorityItem::STATUS_COMPLETED,
        ]);

        foreach ([$staff, $supervisor] as $viewer) {
            $this->actingAs($viewer)->get(route('admin.kaizen-concerns.index'))
                ->assertOk()
                ->assertDontSee('Closed challenge marker');
            $this->actingAs($viewer)->get(route('admin.priority-items.index'))
                ->assertOk()
                ->assertDontSee('Closed lesson marker');

            // isVisibleTo still permits direct access.
            $this->actingAs($viewer)->get(route('admin.kaizen-concerns.show', $closedConcern))->assertOk();
            $this->actingAs($viewer)->get(route('admin.priority-items.show', $closedItem))->assertOk();
        }

        $this->assertTrue($closedConcern->isVisibleTo($staff));
        $this->assertTrue($closedItem->isVisibleTo($staff));
    }

    /* ---------------------------------------------------------------
     | 7. Assignment notification still goes only to the assignee
     * -------------------------------------------------------------- */

    public function test_assignment_notification_goes_only_to_the_assigned_staff(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        \Illuminate\Support\Facades\Http::fake();

        $admin = $this->user(User::ROLE_ADMIN);
        $staff = $this->user(User::ROLE_STAFF);
        $other = $this->user(User::ROLE_STAFF);
        $concern = $this->concern($admin);

        $this->actingAs($admin)->put(route('admin.kaizen-concerns.update', $concern), [
            'date_identified' => '2026-09-01',
            'challenge' => 'c',
            'recommended_solution' => 's',
            'assigned_staff_id' => $staff->id,
            'status' => KaizenConcern::STATUS_PENDING,
        ])->assertRedirect();

        $this->assertSame(1, \App\Models\Notification::where('user_id', $staff->id)->count());
        $this->assertSame(0, \App\Models\Notification::where('user_id', $other->id)->count());
        $this->assertSame(0, \App\Models\Notification::where('user_id', $admin->id)->count());
    }

    public function test_priority_assignment_notification_goes_only_to_the_assigned_staff(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        \Illuminate\Support\Facades\Http::fake();

        $admin = $this->user(User::ROLE_ADMIN);
        $staff = $this->user(User::ROLE_STAFF);
        $other = $this->user(User::ROLE_STAFF);
        $item = $this->item($admin);

        $this->actingAs($admin)->put(route('admin.priority-items.update', $item), [
            'task_lesson' => 'update the book',
            'type' => PriorityItem::TYPE_PRIORITY_TASK,
            'priority' => PriorityItem::PRIORITY_HIGH,
            'assigned_staff_id' => $staff->id,
            'status' => PriorityItem::STATUS_PENDING,
        ])->assertRedirect();

        $this->assertSame(1, \App\Models\Notification::where('user_id', $staff->id)->count());
        $this->assertSame(0, \App\Models\Notification::where('user_id', $other->id)->count());
        $this->assertSame(0, \App\Models\Notification::where('user_id', $admin->id)->count());
    }

    public function test_clearing_assignment_sends_nobody_a_notification(): void    {
        $admin = $this->user(User::ROLE_ADMIN);
        $staff = $this->user(User::ROLE_STAFF);
        $concern = $this->concern($staff);

        $before = \App\Models\Notification::count();

        $this->actingAs($admin)->put(route('admin.kaizen-concerns.update', $concern), [
            'date_identified' => '2026-09-01',
            'challenge' => 'c',
            'recommended_solution' => 's',
            'assigned_staff_id' => '',
            'status' => KaizenConcern::STATUS_PENDING,
        ])->assertRedirect();

        $this->assertSame($before, \App\Models\Notification::count());
    }

    public function test_staff_and_supervisor_cannot_open_the_edit_or_create_screens(): void
    {
        $staff = $this->user(User::ROLE_STAFF);
        $supervisor = $this->user(User::ROLE_SUPERVISOR);
        $concern = $this->concern($staff);
        $item = $this->item($staff);

        foreach ([$staff, $supervisor] as $viewer) {
            $this->actingAs($viewer)->get(route('admin.kaizen-concerns.edit', $concern))->assertForbidden();
            $this->actingAs($viewer)->get(route('admin.kaizen-concerns.create'))->assertForbidden();
            $this->actingAs($viewer)->get(route('admin.priority-items.edit', $item))->assertForbidden();
            $this->actingAs($viewer)->get(route('admin.priority-items.create'))->assertForbidden();
        }
    }

    /* ---------------------------------------------------------------
     | 8. Evidence of implementation (multiple files per record)
     * -------------------------------------------------------------- */

    public function test_multiple_kaizen_evidence_files_can_be_uploaded_and_displayed(): void
    {
        Storage::fake('local');

        $admin = $this->user(User::ROLE_ADMIN);
        $concern = $this->concern($admin);

        foreach (['first-proof.pdf', 'second-proof.png'] as $index => $name) {
            $this->actingAs($admin)->post(route('admin.kaizen-concerns.evidence.add', $concern), [
                'evidence' => UploadedFile::fake()->create(
                    $name,
                    120,
                    $index === 0 ? 'application/pdf' : 'image/png'
                ),
            ])->assertRedirect();
        }

        $this->assertSame(2, $concern->evidences()->count());

        foreach ($concern->evidences as $evidence) {
            Storage::disk('local')->assertExists($evidence->path);
        }

        $this->actingAs($admin)->get(route('admin.kaizen-concerns.show', $concern))
            ->assertOk()
            ->assertSee('first-proof.pdf')
            ->assertSee('second-proof.png');
    }

    public function test_priority_items_support_multiple_evidence_and_show_it_in_the_list(): void
    {
        Storage::fake('local');

        $admin = $this->user(User::ROLE_ADMIN);
        $item = $this->item($admin);

        foreach (['priority-proof.pdf', 'priority-photo.png'] as $index => $name) {
            $this->actingAs($admin)->post(route('admin.priority-items.evidence.add', $item), [
                'evidence' => UploadedFile::fake()->create(
                    $name,
                    120,
                    $index === 0 ? 'application/pdf' : 'image/png'
                ),
            ])->assertRedirect();
        }

        $this->assertSame(2, $item->evidences()->count());

        $this->actingAs($admin)->get(route('admin.priority-items.show', $item))
            ->assertOk()
            ->assertSee('priority-proof.pdf')
            ->assertSee('priority-photo.png');

        $this->actingAs($admin)->get(route('admin.priority-items.index'))
            ->assertOk()
            ->assertSee('[2 files]');
    }

    public function test_evidence_can_be_downloaded_through_a_signed_route(): void
    {
        Storage::fake('local');

        $admin = $this->user(User::ROLE_ADMIN);
        $concern = $this->concern($admin);

        $this->actingAs($admin)->post(route('admin.kaizen-concerns.evidence.add', $concern), [
            'evidence' => UploadedFile::fake()->create('proof.pdf', 120, 'application/pdf'),
        ]);
        $evidence = $concern->evidences()->firstOrFail();

        $signed = URL::temporarySignedRoute('admin.kaizen-concerns.evidence.download', now()->addMinutes(30), [
            'concern' => $concern->id,
            'evidence' => $evidence->id,
        ]);

        $this->actingAs($admin)->get($signed)->assertOk();
    }

    public function test_evidence_routes_reject_unsigned_requests(): void
    {
        Storage::fake('local');

        $admin = $this->user(User::ROLE_ADMIN);
        $concern = $this->concern($admin);

        $this->actingAs($admin)->post(route('admin.kaizen-concerns.evidence.add', $concern), [
            'evidence' => UploadedFile::fake()->create('proof.pdf', 120, 'application/pdf'),
        ]);
        $evidence = $concern->evidences()->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.kaizen-concerns.evidence.view', [$concern, $evidence]))
            ->assertForbidden();

        $this->actingAs($admin)
            ->get(route('admin.kaizen-concerns.evidence.download', [$concern, $evidence]))
            ->assertForbidden();
    }

    public function test_admin_can_delete_evidence_but_staff_cannot(): void
    {
        Storage::fake('local');

        $admin = $this->user(User::ROLE_ADMIN);
        $staff = $this->user(User::ROLE_STAFF);
        $concern = $this->concern(null);

        $this->actingAs($admin)->post(route('admin.kaizen-concerns.evidence.add', $concern), [
            'evidence' => UploadedFile::fake()->create('proof.pdf', 120, 'application/pdf'),
        ]);
        $evidence = $concern->evidences()->firstOrFail();
        $path = $evidence->path;

        $this->actingAs($staff)
            ->delete(route('admin.kaizen-concerns.evidence.delete', [$concern, $evidence]))
            ->assertForbidden();
        $this->assertDatabaseHas('kaizen_evidences', ['id' => $evidence->id]);

        $this->actingAs($admin)
            ->delete(route('admin.kaizen-concerns.evidence.delete', [$concern, $evidence]))
            ->assertRedirect();

        $this->assertDatabaseMissing('kaizen_evidences', ['id' => $evidence->id]);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_evidence_cannot_be_fetched_through_another_record(): void
    {
        Storage::fake('local');

        $admin = $this->user(User::ROLE_ADMIN);
        $concern = $this->concern($admin);
        $other = $this->concern($admin);

        $this->actingAs($admin)->post(route('admin.kaizen-concerns.evidence.add', $concern), [
            'evidence' => UploadedFile::fake()->create('proof.pdf', 120, 'application/pdf'),
        ]);
        $evidence = $concern->evidences()->firstOrFail();

        $signed = URL::temporarySignedRoute('admin.kaizen-concerns.evidence.view', now()->addMinutes(30), [
            'concern' => $other->id,
            'evidence' => $evidence->id,
        ]);

        $this->actingAs($admin)->get($signed)->assertNotFound();
    }

    public function test_staff_who_cannot_view_the_record_cannot_access_its_evidence(): void
    {
        Storage::fake('local');

        $admin = $this->user(User::ROLE_ADMIN);
        $owner = $this->user(User::ROLE_STAFF);
        $other = $this->user(User::ROLE_STAFF);
        $concern = $this->concern($owner);

        $this->actingAs($admin)->post(route('admin.kaizen-concerns.evidence.add', $concern), [
            'evidence' => UploadedFile::fake()->create('proof.pdf', 120, 'application/pdf'),
        ]);
        $evidence = $concern->evidences()->firstOrFail();

        $signed = URL::temporarySignedRoute('admin.kaizen-concerns.evidence.view', now()->addMinutes(30), [
            'concern' => $concern->id,
            'evidence' => $evidence->id,
        ]);

        $this->actingAs($other)->get($signed)->assertForbidden();
        $this->actingAs($other)->post(route('admin.kaizen-concerns.evidence.add', $concern), [
            'evidence' => UploadedFile::fake()->create('sneaky.pdf', 120, 'application/pdf'),
        ])->assertForbidden();
    }

    public function test_admin_and_supervisor_can_manage_kaizen_checklist_but_staff_cannot(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $supervisor = $this->user(User::ROLE_SUPERVISOR);
        $staff = $this->user(User::ROLE_STAFF);

        $concern = $this->concern(null); // unassigned -> visible to staff + supervisors

        $this->actingAs($admin)->post(route('admin.kaizen-concerns.checklist.add', $concern), ['title' => 'Admin item'])->assertRedirect();
        $this->actingAs($supervisor)->post(route('admin.kaizen-concerns.checklist.add', $concern), ['title' => 'Supervisor item'])->assertRedirect();
        $this->actingAs($staff)->post(route('admin.kaizen-concerns.checklist.add', $concern), ['title' => 'Staff item'])->assertForbidden();

        $this->assertDatabaseHas('checklist_items', ['title' => 'Admin item']);
        $this->assertDatabaseHas('checklist_items', ['title' => 'Supervisor item']);
        $this->assertDatabaseMissing('checklist_items', ['title' => 'Staff item']);

        $manageable = $concern->checklistItems()->firstOrFail();

        $this->actingAs($staff)->post(route('admin.kaizen-concerns.checklist.destroy', $concern), [
            'checklist_item_id' => $manageable->id,
        ])->assertForbidden();
        $this->assertDatabaseHas('checklist_items', ['id' => $manageable->id]);

        $this->actingAs($supervisor)->post(route('admin.kaizen-concerns.checklist.destroy', $concern), [
            'checklist_item_id' => $manageable->id,
        ])->assertRedirect();
        $this->assertDatabaseMissing('checklist_items', ['id' => $manageable->id]);
    }

    public function test_staff_can_complete_a_visible_kaizen_checklist_item(): void
    {
        $staff = $this->user(User::ROLE_STAFF);
        $concern = $this->concern($staff);
        $item = $concern->checklistItems()->create(['title' => 'Do the thing', 'sort_order' => 1]);

        $this->actingAs($staff)->post(route('admin.kaizen-concerns.checklist.toggle', $concern), [
            'checklist_item_id' => $item->id,
        ])->assertRedirect();

        $item->refresh();
        $this->assertTrue($item->completed);
        $this->assertSame($staff->id, $item->completed_by);
    }

    public function test_authorized_staff_see_kaizen_evidence_upload_ui(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $supervisor = $this->user(User::ROLE_SUPERVISOR);
        $staff = $this->user(User::ROLE_STAFF);
        $otherStaff = $this->user(User::ROLE_STAFF);

        // Test 1: Unassigned concern - visible to operational staff
        $unassignedConcern = $this->concern(null);
        $show = route('admin.kaizen-concerns.show', $unassignedConcern);

        $this->actingAs($admin)->get($show)
            ->assertOk()
            ->assertSee('Add Evidence')
            ->assertSee('addEvidenceModal', false);

        $this->actingAs($supervisor)->get($show)
            ->assertOk()
            ->assertSee('Add Evidence')
            ->assertSee('addEvidenceModal', false);

        // Staff CAN see upload UI for unassigned concerns (operational staff can work on them)
        $this->actingAs($staff)->get($show)
            ->assertOk()
            ->assertSee('Add Evidence')
            ->assertSee('addEvidenceModal', false);

        // Test 2: Concern assigned to THIS staff member
        $assignedConcern = $this->concern($staff);
        $assignedShow = route('admin.kaizen-concerns.show', $assignedConcern);

        $this->actingAs($staff)->get($assignedShow)
            ->assertOk()
            ->assertSee('Add Evidence')
            ->assertSee('addEvidenceModal', false);

        // Test 3: Concern assigned to ANOTHER staff member - staff cannot even VIEW it (403)
        $otherConcern = $this->concern($otherStaff);
        $otherShow = route('admin.kaizen-concerns.show', $otherConcern);

        $this->actingAs($staff)->get($otherShow)
            ->assertForbidden();
    }

    public function test_admin_and_supervisor_can_edit_kaizen_checklist_items_but_staff_cannot(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $supervisor = $this->user(User::ROLE_SUPERVISOR);
        $staff = $this->user(User::ROLE_STAFF);

        $concern = $this->concern(null); // unassigned -> visible to staff + supervisors
        $item = $concern->checklistItems()->create(['title' => 'Original title', 'sort_order' => 1]);

        $this->actingAs($staff)->post(route('admin.kaizen-concerns.checklist.update', $concern), [
            'checklist_item_id' => $item->id,
            'title' => 'Staff rename',
        ])->assertForbidden();
        $this->assertSame('Original title', $item->fresh()->title);

        $this->actingAs($supervisor)->post(route('admin.kaizen-concerns.checklist.update', $concern), [
            'checklist_item_id' => $item->id,
            'title' => 'Supervisor rename',
        ])->assertRedirect();
        $this->assertSame('Supervisor rename', $item->fresh()->title);

        $this->actingAs($admin)->post(route('admin.kaizen-concerns.checklist.update', $concern), [
            'checklist_item_id' => $item->id,
            'title' => 'Admin rename',
        ])->assertRedirect();
        $this->assertSame('Admin rename', $item->fresh()->title);
    }

    public function test_a_kaizen_checklist_item_cannot_be_renamed_through_another_record(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);

        $concern = $this->concern(null);
        $other = $this->concern(null);
        $foreign = $other->checklistItems()->create(['title' => 'Belongs elsewhere', 'sort_order' => 1]);

        $this->actingAs($admin)->post(route('admin.kaizen-concerns.checklist.update', $concern), [
            'checklist_item_id' => $foreign->id,
            'title' => 'Hijacked',
        ])->assertNotFound();

        $this->assertSame('Belongs elsewhere', $foreign->fresh()->title);
    }

    public function test_kaizen_checklist_progress_and_edit_controls_follow_the_authorization_rules(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $staff = $this->user(User::ROLE_STAFF);

        $concern = $this->concern(null); // unassigned -> visible to staff + supervisors
        $concern->checklistItems()->create([
            'title' => 'Done item',
            'sort_order' => 1,
            'completed' => true,
        ]);
        $concern->checklistItems()->create(['title' => 'Open item', 'sort_order' => 2]);

        $show = route('admin.kaizen-concerns.show', $concern);

        $this->actingAs($admin)->get($show)
            ->assertOk()
            ->assertSee('checklist-progress')
            ->assertSee('1 of 2 complete')
            ->assertSee('editChecklistModal', false)
            ->assertSee('addChecklistModal', false);

        // Staff see the progress readout but no checklist management controls.
        $staffResponse = $this->actingAs($staff)->get($show)->assertOk();

        $staffResponse->assertSee('checklist-progress');
        $staffResponse->assertSee('1 of 2 complete');
        $staffResponse->assertDontSee('editChecklistModal', false);
        $staffResponse->assertDontSee('addChecklistModal', false);
    }

    /* ---------------------------------------------------------------
     | Evidence authorization (Kaizen + Priority)
     * -------------------------------------------------------------- */

    public function test_authorized_staff_can_upload_kaizen_evidence(): void
    {
        $staff = $this->user(User::ROLE_STAFF);
        $otherStaff = $this->user(User::ROLE_STAFF);
        $supervisor = $this->user(User::ROLE_SUPERVISOR);
        $client = $this->user(User::ROLE_CLIENT);

        Storage::fake('local');

        // Test 1: Staff can upload to unassigned concern (operational staff can work on it)
        $unassignedConcern = $this->concern(null);
        $file = UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf');

        $this->actingAs($staff)
            ->post(route('admin.kaizen-concerns.evidence.add', $unassignedConcern), ['evidence' => $file])
            ->assertRedirect();

        $this->assertSame(1, $unassignedConcern->evidences()->count());

        // Test 2: Staff can upload to their own assigned concern
        $myConcern = $this->concern($staff);
        $file2 = UploadedFile::fake()->create('proof2.pdf', 10, 'application/pdf');

        $this->actingAs($staff)
            ->post(route('admin.kaizen-concerns.evidence.add', $myConcern), ['evidence' => $file2])
            ->assertRedirect();

        $this->assertSame(1, $myConcern->evidences()->count());

        // Test 3: Staff CANNOT upload to another staff's assigned concern
        $otherConcern = $this->concern($otherStaff);
        $file3 = UploadedFile::fake()->create('proof3.pdf', 10, 'application/pdf');

        $this->actingAs($staff)
            ->post(route('admin.kaizen-concerns.evidence.add', $otherConcern), ['evidence' => $file3])
            ->assertForbidden();

        $this->assertSame(0, $otherConcern->evidences()->count());

        // Test 4: Client CANNOT upload
        $file4 = UploadedFile::fake()->create('client.pdf', 10, 'application/pdf');
        $this->actingAs($client)
            ->post(route('admin.kaizen-concerns.evidence.add', $unassignedConcern), ['evidence' => $file4])
            ->assertForbidden();

        // Test 5: Supervisor CAN upload to unassigned concern
        $file5 = UploadedFile::fake()->create('supervisor.pdf', 10, 'application/pdf');
        $this->actingAs($supervisor)
            ->post(route('admin.kaizen-concerns.evidence.add', $unassignedConcern), ['evidence' => $file5])
            ->assertRedirect();

        $this->assertSame(2, $unassignedConcern->evidences()->count());
    }

    public function test_authorized_staff_can_upload_priority_evidence(): void
    {
        $staff = $this->user(User::ROLE_STAFF);
        $otherStaff = $this->user(User::ROLE_STAFF);
        $supervisor = $this->user(User::ROLE_SUPERVISOR);
        $client = $this->user(User::ROLE_CLIENT);

        Storage::fake('local');

        // Test 1: Staff can upload to unassigned priority item (operational staff can work on it)
        $unassignedItem = $this->item(null);
        $file = UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf');

        $this->actingAs($staff)
            ->post(route('admin.priority-items.evidence.add', $unassignedItem), ['evidence' => $file])
            ->assertRedirect();

        $this->assertSame(1, $unassignedItem->evidences()->count());

        // Test 2: Staff can upload to their own assigned priority item
        $myItem = $this->item($staff);
        $file2 = UploadedFile::fake()->create('proof2.pdf', 10, 'application/pdf');

        $this->actingAs($staff)
            ->post(route('admin.priority-items.evidence.add', $myItem), ['evidence' => $file2])
            ->assertRedirect();

        $this->assertSame(1, $myItem->evidences()->count());

        // Test 3: Staff CANNOT upload to another staff's assigned priority item
        $otherItem = $this->item($otherStaff);
        $file3 = UploadedFile::fake()->create('proof3.pdf', 10, 'application/pdf');

        $this->actingAs($staff)
            ->post(route('admin.priority-items.evidence.add', $otherItem), ['evidence' => $file3])
            ->assertForbidden();

        $this->assertSame(0, $otherItem->evidences()->count());

        // Test 4: Client CANNOT upload
        $file4 = UploadedFile::fake()->create('client.pdf', 10, 'application/pdf');
        $this->actingAs($client)
            ->post(route('admin.priority-items.evidence.add', $unassignedItem), ['evidence' => $file4])
            ->assertForbidden();

        // Test 5: Supervisor CAN upload to unassigned item
        $file5 = UploadedFile::fake()->create('supervisor.pdf', 10, 'application/pdf');
        $this->actingAs($supervisor)
            ->post(route('admin.priority-items.evidence.add', $unassignedItem), ['evidence' => $file5])
            ->assertRedirect();

        $this->assertSame(2, $unassignedItem->evidences()->count());
    }

    public function test_authorized_staff_see_priority_evidence_upload_ui(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $supervisor = $this->user(User::ROLE_SUPERVISOR);
        $staff = $this->user(User::ROLE_STAFF);
        $otherStaff = $this->user(User::ROLE_STAFF);

        // Test 1: Unassigned item - visible to operational staff
        $unassignedItem = $this->item(null);
        $show = route('admin.priority-items.show', $unassignedItem);

        $this->actingAs($admin)->get($show)
            ->assertOk()
            ->assertSee('Add Evidence')
            ->assertSee('addEvidenceModal', false);

        $this->actingAs($supervisor)->get($show)
            ->assertOk()
            ->assertSee('Add Evidence')
            ->assertSee('addEvidenceModal', false);

        // Staff CAN see upload UI for unassigned items
        $this->actingAs($staff)->get($show)
            ->assertOk()
            ->assertSee('Add Evidence')
            ->assertSee('addEvidenceModal', false);

        // Test 2: Item assigned to THIS staff member
        $assignedItem = $this->item($staff);
        $assignedShow = route('admin.priority-items.show', $assignedItem);

        $this->actingAs($staff)->get($assignedShow)
            ->assertOk()
            ->assertSee('Add Evidence')
            ->assertSee('addEvidenceModal', false);

        // Test 3: Item assigned to ANOTHER staff member - staff cannot even VIEW it (403)
        $otherItem = $this->item($otherStaff);
        $otherShow = route('admin.priority-items.show', $otherItem);

        $this->actingAs($staff)->get($otherShow)
            ->assertForbidden();
    }

    /* ---------------------------------------------------------------
     | helper
     * -------------------------------------------------------------- */

    /**
     * Flatten a record's editable columns to scalars so comparisons are by
     * value. Comparing raw attribute arrays would compare two distinct Carbon
     * instances by identity, which says nothing about the stored data.
     */
    private function scalarState(PriorityItem $item): array
    {
        $state = $item->only([
            'task_lesson', 'type', 'description', 'priority',
            'assigned_staff_id', 'status', 'notes',
        ]);

        $state['due_date'] = $item->due_date?->format('Y-m-d');

        return $state;
    }

    /**
     * Assert that the <option value="..."> for $value inside <select name="$name">
     * carries the selected attribute. Uses a regex so it also matches options
     * rendered as `value="x" selected` or `value="x"  selected`.
     */
    private function assertSelectedOption(string $html, string $name, string $value): void
    {
        $escaped = preg_quote($name, '/');
        $selectPattern = '/<select\b[^>]*\bname="'.$escaped.'".*?<\/select>/is';
        $this->assertSame(
            1,
            preg_match($selectPattern, $html, $m),
            "Expected a <select name=\"{$name}\"> on the page."
        );

        $this->assertSame(
            1,
            preg_match('/<option\b[^>]*\bvalue="'.preg_quote($value, '/').'"[^>]*\bselected\b/is', $m[0]),
            "Expected option \"{$value}\" to be selected in <select name=\"{$name}\">."
        );
    }
}
