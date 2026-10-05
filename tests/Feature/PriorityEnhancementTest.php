<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminConfidentialityAcknowledged;
use App\Models\ChecklistItem;
use App\Models\PriorityEvidence;
use App\Models\PriorityItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Priority List / To-Do List enhancement coverage:
 *
 *  - server-owned default due dates derived from priority
 *  - preservation of historical deadlines on edit
 *  - urgency instructions, deadline labels and overdue handling
 *  - urgency-first default ordering plus filters/search/pagination
 *  - evidence upload/display/authorization and role-limited deletion
 *  - checklist permissions (admin + supervisor manage, staff complete)
 *  - list/detail hierarchy, overdue phrasing and evidence cards
 *  - completion audit trail (completed_at / completed_by)
 */
class PriorityEnhancementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    private function user(string $role): User
    {
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

    private function item(array $overrides = []): PriorityItem
    {
        return PriorityItem::create(array_merge([
            'task_lesson' => 'Reconcile the cash book',
            'type' => PriorityItem::TYPE_PRIORITY_TASK,
            'description' => 'Reconcile the cash book before month end.',
            'priority' => PriorityItem::PRIORITY_MEDIUM,
            'assigned_staff_id' => null,
            'due_date' => null,
            'status' => PriorityItem::STATUS_PENDING,
            'notes' => null,
            'created_by' => $this->user(User::ROLE_ADMIN)->id,
        ], $overrides));
    }

    /* ---------------------------------------------------------------
     | Default due dates
     * -------------------------------------------------------------- */

    public function test_blank_due_date_is_defaulted_from_priority(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $base = Carbon::parse('2026-06-15 09:00:00');

        $expected = [
            PriorityItem::PRIORITY_URGENT => '2026-06-15',
            PriorityItem::PRIORITY_HIGH => '2026-06-16',
            PriorityItem::PRIORITY_MEDIUM => '2026-06-18',
            PriorityItem::PRIORITY_LOW => '2026-06-22',
        ];

        foreach ($expected as $priority => $date) {
            $this->travelTo($base->copy());

            $this->actingAs($admin)->post(route('admin.priority-items.store'), [
                'task_lesson' => 'Task '.$priority,
                'type' => PriorityItem::TYPE_PRIORITY_TASK,
                'priority' => $priority,
                'status' => PriorityItem::STATUS_PENDING,
                // due_date deliberately omitted
            ])->assertRedirect(route('admin.priority-items.index'));

            $stored = PriorityItem::where('task_lesson', 'Task '.$priority)->firstOrFail();
            $this->assertSame($date, $stored->due_date->format('Y-m-d'), "Default for {$priority} should be {$date}.");
        }
    }

    public function test_custom_due_date_is_respected_on_create(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $this->travelTo(Carbon::parse('2026-06-15 09:00:00'));

        $this->actingAs($admin)->post(route('admin.priority-items.store'), [
            'task_lesson' => 'Custom deadline',
            'type' => PriorityItem::TYPE_PRIORITY_TASK,
            'priority' => PriorityItem::PRIORITY_URGENT,
            'status' => PriorityItem::STATUS_PENDING,
            'due_date' => '2026-12-31',
        ])->assertRedirect(route('admin.priority-items.index'));

        $this->assertSame(
            '2026-12-31',
            PriorityItem::where('task_lesson', 'Custom deadline')->firstOrFail()->due_date->format('Y-m-d')
        );
    }

    public function test_editing_without_a_due_date_preserves_the_existing_deadline(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $this->travelTo(Carbon::parse('2026-06-15 09:00:00'));

        $item = $this->item([
            'priority' => PriorityItem::PRIORITY_LOW,
            'due_date' => '2026-05-01',
        ]);

        $this->actingAs($admin)->put(route('admin.priority-items.update', $item), [
            'task_lesson' => $item->task_lesson,
            'type' => $item->type,
            'priority' => PriorityItem::PRIORITY_URGENT,
            'status' => PriorityItem::STATUS_PENDING,
            'due_date' => '',
        ])->assertRedirect();

        $item->refresh();
        $this->assertSame('2026-05-01', $item->due_date->format('Y-m-d'));
        $this->assertSame(PriorityItem::PRIORITY_URGENT, $item->priority);
    }

    public function test_editing_can_still_change_the_deadline(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $item = $this->item(['due_date' => '2026-05-01']);

        $this->actingAs($admin)->put(route('admin.priority-items.update', $item), [
            'task_lesson' => $item->task_lesson,
            'type' => $item->type,
            'priority' => $item->priority,
            'status' => $item->status,
            'due_date' => '2026-08-09',
        ])->assertRedirect();

        $this->assertSame('2026-08-09', $item->refresh()->due_date->format('Y-m-d'));
    }

    /* ---------------------------------------------------------------
     | Urgency + deadline labels
     * -------------------------------------------------------------- */

    public function test_deadline_labels_and_overdue_state(): void
    {
        $this->travelTo(Carbon::parse('2026-06-15 09:00:00'));

        $today = $this->item(['due_date' => '2026-06-15']);
        $this->assertSame('DUE TODAY', $today->deadlineLabel());
        $this->assertFalse($today->isOverdue());
        $this->assertSame('badge-warn', $today->deadlineBadgeClass());

        $tomorrow = $this->item(['due_date' => '2026-06-16']);
        $this->assertSame('DUE TOMORROW', $tomorrow->deadlineLabel());

        $later = $this->item(['due_date' => '2026-06-20']);
        $this->assertSame('DUE IN 5 DAYS', $later->deadlineLabel());
        $this->assertSame('badge-info', $later->deadlineBadgeClass());

        $overdue = $this->item(['due_date' => '2026-06-13']);
        $this->assertSame('OVERDUE BY 2 DAYS', $overdue->deadlineLabel());
        $this->assertTrue($overdue->isOverdue());
        $this->assertSame('badge-danger', $overdue->deadlineBadgeClass());

        $completed = $this->item([
            'due_date' => '2026-06-13',
            'status' => PriorityItem::STATUS_COMPLETED,
        ]);
        $this->assertSame('COMPLETED', $completed->deadlineLabel());
        $this->assertFalse($completed->isOverdue());
        $this->assertSame('badge-success', $completed->deadlineBadgeClass());
    }

    public function test_urgency_instruction_matches_priority(): void
    {
        $this->assertSame('URGENT — ACTION REQUIRED NOW', $this->item(['priority' => PriorityItem::PRIORITY_URGENT])->urgencyInstruction());
        $this->assertSame('HIGH — ACTION NOW / DUE TOMORROW', $this->item(['priority' => PriorityItem::PRIORITY_HIGH])->urgencyInstruction());
        $this->assertSame('MEDIUM — COMPLETE WITHIN 3 DAYS', $this->item(['priority' => PriorityItem::PRIORITY_MEDIUM])->urgencyInstruction());
        $this->assertSame('LOW — COMPLETE WITHIN 1 WEEK', $this->item(['priority' => PriorityItem::PRIORITY_LOW])->urgencyInstruction());
    }

    public function test_list_and_detail_pages_show_urgency_and_deadline(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $this->travelTo(Carbon::parse('2026-06-15 09:00:00'));

        $item = $this->item([
            'task_lesson' => 'Urgent filing',
            'priority' => PriorityItem::PRIORITY_URGENT,
            'due_date' => '2026-06-15',
        ]);

        $this->actingAs($admin)->get(route('admin.priority-items.index'))
            ->assertOk()
            ->assertSee('Urgent')
            ->assertSee('URGENT — ACTION NOW')
            ->assertSee('DUE TODAY');

        $this->actingAs($admin)->get(route('admin.priority-items.show', $item))
            ->assertOk()
            ->assertSee('Priority guidance')
            ->assertSee('ACTION REQUIRED NOW')
            ->assertSee('Urgency')
            ->assertSee('DUE TODAY');
    }

    /* ---------------------------------------------------------------
     | Ordering, filters, search, pagination
     * -------------------------------------------------------------- */

    public function test_default_order_is_urgency_then_deadline(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);

        $this->item(['task_lesson' => 'ZZZ urgent later', 'priority' => PriorityItem::PRIORITY_URGENT, 'due_date' => '2026-07-20']);
        $this->item(['task_lesson' => 'YYY high first', 'priority' => PriorityItem::PRIORITY_HIGH, 'due_date' => '2026-07-16']);
        $this->item(['task_lesson' => 'XXX medium next', 'priority' => PriorityItem::PRIORITY_MEDIUM, 'due_date' => '2026-07-15']);
        $this->item(['task_lesson' => 'WWW urgent sooner', 'priority' => PriorityItem::PRIORITY_URGENT, 'due_date' => '2026-07-18']);
        $this->item(['task_lesson' => 'VVV low undated', 'priority' => PriorityItem::PRIORITY_LOW, 'due_date' => null]);

        $content = $this->actingAs($admin)->get(route('admin.priority-items.index'))->assertOk()->getContent();

        $order = ['WWW urgent sooner', 'ZZZ urgent later', 'YYY high first', 'XXX medium next', 'VVV low undated'];
        $positions = array_map(fn ($name) => strpos($content, $name), $order);

        foreach ($positions as $position) {
            $this->assertNotFalse($position, 'Every seeded task should render on the page.');
        }

        $sorted = $positions;
        sort($sorted);

        $this->assertSame($sorted, $positions, 'Items must be ordered urgent -> high -> medium -> low, deadline ascending.');
    }

    public function test_search_filters_and_pagination_still_work(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);

        $this->item(['task_lesson' => 'Alpha reconciliation']);
        $this->item(['task_lesson' => 'Beta reconciliation', 'priority' => PriorityItem::PRIORITY_URGENT]);

        $this->actingAs($admin)->get(route('admin.priority-items.index', ['q' => 'Alpha']))
            ->assertOk()
            ->assertSee('Alpha reconciliation')
            ->assertDontSee('Beta reconciliation');

        $priority = $this->actingAs($admin)->get(route('admin.priority-items.index', ['priority' => PriorityItem::PRIORITY_URGENT]))
            ->assertOk();
        $priority->assertSee('Beta reconciliation');
        $priority->assertDontSee('Alpha reconciliation');

        for ($i = 0; $i < 55; $i++) {
            $this->item(['task_lesson' => 'Bulk item '.$i]);
        }

        $page = $this->actingAs($admin)->get(route('admin.priority-items.index'))->assertOk();
        $this->assertSame(50, substr_count($page->getContent(), 'data-col="Type"'), 'Index must paginate at 50 rows.');
    }

    /* ---------------------------------------------------------------
     | Evidence
     * -------------------------------------------------------------- */

    public function test_multiple_evidence_files_are_stored_and_listed(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $item = $this->item();

        foreach (['first.png', 'second.png'] as $name) {
            $this->actingAs($admin)->post(route('admin.priority-items.evidence.add', $item), [
                'evidence' => UploadedFile::fake()->image($name, 60, 60),
            ])->assertRedirect();
        }

        $this->assertSame(2, $item->evidences()->count());

        $this->actingAs($admin)->get(route('admin.priority-items.show', $item))
            ->assertOk()
            ->assertSee('first.png')
            ->assertSee('second.png')
            ->assertSee('Evidence of Completion');
    }

    public function test_uploading_evidence_does_not_complete_the_item(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $item = $this->item(['status' => PriorityItem::STATUS_PENDING]);

        $this->actingAs($admin)->post(route('admin.priority-items.evidence.add', $item), [
            'evidence' => UploadedFile::fake()->image('proof.png', 60, 60),
        ])->assertRedirect();

        $this->assertSame(PriorityItem::STATUS_PENDING, $item->refresh()->status);
    }

    public function test_evidence_view_and_download_require_a_signature(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $item = $this->item();

        $this->actingAs($admin)->post(route('admin.priority-items.evidence.add', $item), [
            'evidence' => UploadedFile::fake()->image('proof.png', 60, 60),
        ])->assertRedirect();

        $evidence = PriorityEvidence::firstOrFail();

        $view = URL::temporarySignedRoute('admin.priority-items.evidence.view', now()->addMinutes(30), [
            'item' => $item->id,
            'evidence' => $evidence->id,
        ]);
        $this->actingAs($admin)->get($view)->assertOk();

        $download = URL::temporarySignedRoute('admin.priority-items.evidence.download', now()->addMinutes(30), [
            'item' => $item->id,
            'evidence' => $evidence->id,
        ]);
        $this->actingAs($admin)->get($download)
            ->assertOk()
            ->assertHeader('content-disposition');

        $this->actingAs($admin)
            ->get(route('admin.priority-items.evidence.view', [$item, $evidence]))
            ->assertForbidden();
    }

    public function test_evidence_is_hidden_from_users_without_access_and_wrong_items_404(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $owner = $this->user(User::ROLE_STAFF);
        $outsider = $this->user(User::ROLE_STAFF);

        $item = $this->item(['assigned_staff_id' => $owner->id]);

        $this->actingAs($admin)->post(route('admin.priority-items.evidence.add', $item), [
            'evidence' => UploadedFile::fake()->image('proof.png', 60, 60),
        ])->assertRedirect();

        $evidence = PriorityEvidence::firstOrFail();

        $url = URL::temporarySignedRoute('admin.priority-items.evidence.view', now()->addMinutes(30), [
            'item' => $item->id,
            'evidence' => $evidence->id,
        ]);

        $this->actingAs($owner)->get($url)->assertOk();
        $this->actingAs($outsider)->get($url)->assertForbidden();

        $other = $this->item(['task_lesson' => 'Different item']);
        $crossUrl = URL::temporarySignedRoute('admin.priority-items.evidence.view', now()->addMinutes(30), [
            'item' => $other->id,
            'evidence' => $evidence->id,
        ]);
        $this->actingAs($admin)->get($crossUrl)->assertNotFound();
    }

    public function test_only_admin_or_supervisor_can_delete_evidence(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $supervisor = $this->user(User::ROLE_SUPERVISOR);
        $staff = $this->user(User::ROLE_STAFF);

        $item = $this->item();

        $this->actingAs($admin)->post(route('admin.priority-items.evidence.add', $item), [
            'evidence' => UploadedFile::fake()->image('proof.png', 60, 60),
        ])->assertRedirect();

        $first = PriorityEvidence::firstOrFail();
        $this->actingAs($staff)->delete(route('admin.priority-items.evidence.delete', [$item, $first]))->assertForbidden();
        $this->assertDatabaseHas('priority_evidences', ['id' => $first->id]);

        $this->actingAs($supervisor)->delete(route('admin.priority-items.evidence.delete', [$item, $first]))->assertRedirect();
        $this->assertDatabaseMissing('priority_evidences', ['id' => $first->id]);

        $this->actingAs($admin)->post(route('admin.priority-items.evidence.add', $item), [
            'evidence' => UploadedFile::fake()->image('again.png', 60, 60),
        ])->assertRedirect();

        $second = PriorityEvidence::firstOrFail();
        $this->actingAs($admin)->delete(route('admin.priority-items.evidence.delete', [$item, $second]))->assertRedirect();
        $this->assertDatabaseMissing('priority_evidences', ['id' => $second->id]);
    }

    /* ---------------------------------------------------------------
     | Checklist permissions
     * -------------------------------------------------------------- */

    public function test_admin_and_supervisor_can_add_checklist_items_but_staff_cannot(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $supervisor = $this->user(User::ROLE_SUPERVISOR);
        $staff = $this->user(User::ROLE_STAFF);

        $item = $this->item(); // unassigned -> visible to staff + supervisors

        $this->actingAs($admin)->post(route('admin.priority-items.checklist.add', $item), ['title' => 'Admin item'])->assertRedirect();
        $this->actingAs($supervisor)->post(route('admin.priority-items.checklist.add', $item), ['title' => 'Supervisor item'])->assertRedirect();

        $this->actingAs($staff)->post(route('admin.priority-items.checklist.add', $item), ['title' => 'Staff item'])->assertForbidden();

        $this->assertDatabaseHas('checklist_items', ['title' => 'Admin item']);
        $this->assertDatabaseHas('checklist_items', ['title' => 'Supervisor item']);
        $this->assertDatabaseMissing('checklist_items', ['title' => 'Staff item']);
    }

    public function test_admin_and_supervisor_can_delete_checklist_items_but_staff_cannot(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $supervisor = $this->user(User::ROLE_SUPERVISOR);
        $staff = $this->user(User::ROLE_STAFF);

        $item = $this->item();
        $manageable = $item->checklistItems()->create(['title' => 'Removable', 'sort_order' => 1]);

        $this->actingAs($staff)->post(route('admin.priority-items.checklist.destroy', $item), [
            'checklist_item_id' => $manageable->id,
        ])->assertForbidden();
        $this->assertDatabaseHas('checklist_items', ['id' => $manageable->id]);

        $this->actingAs($supervisor)->post(route('admin.priority-items.checklist.destroy', $item), [
            'checklist_item_id' => $manageable->id,
        ])->assertRedirect();
        $this->assertDatabaseMissing('checklist_items', ['id' => $manageable->id]);

        $second = $item->checklistItems()->create(['title' => 'Admin removable', 'sort_order' => 2]);
        $this->actingAs($admin)->post(route('admin.priority-items.checklist.destroy', $item), [
            'checklist_item_id' => $second->id,
        ])->assertRedirect();
        $this->assertDatabaseMissing('checklist_items', ['id' => $second->id]);
    }

    public function test_admin_and_supervisor_can_edit_checklist_items_but_staff_cannot(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $supervisor = $this->user(User::ROLE_SUPERVISOR);
        $staff = $this->user(User::ROLE_STAFF);

        $item = $this->item();
        $checklistItem = $item->checklistItems()->create(['title' => 'Original', 'sort_order' => 1]);

        $this->actingAs($staff)->post(route('admin.priority-items.checklist.update', $item), [
            'checklist_item_id' => $checklistItem->id,
            'title' => 'Staff rename',
        ])->assertForbidden();
        $this->assertSame('Original', $checklistItem->refresh()->title);

        $this->actingAs($supervisor)->post(route('admin.priority-items.checklist.update', $item), [
            'checklist_item_id' => $checklistItem->id,
            'title' => 'Supervisor rename',
        ])->assertRedirect();
        $this->assertSame('Supervisor rename', $checklistItem->refresh()->title);

        $this->actingAs($admin)->post(route('admin.priority-items.checklist.update', $item), [
            'checklist_item_id' => $checklistItem->id,
            'title' => 'Admin rename',
        ])->assertRedirect();
        $this->assertSame('Admin rename', $checklistItem->refresh()->title);
    }

    public function test_staff_can_complete_assigned_checklist_items(): void
    {
        $staff = $this->user(User::ROLE_STAFF);
        $item = $this->item(['assigned_staff_id' => $staff->id]);
        $checklistItem = $item->checklistItems()->create(['title' => 'Do the thing', 'sort_order' => 1]);

        $this->actingAs($staff)->post(route('admin.priority-items.checklist.toggle', $item), [
            'checklist_item_id' => $checklistItem->id,
        ])->assertRedirect();

        $checklistItem->refresh();
        $this->assertTrue($checklistItem->completed);
        $this->assertSame($staff->id, $checklistItem->completed_by);
        $this->assertNotNull($checklistItem->completed_at);
    }

    public function test_staff_empty_state_hides_management_controls(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $staff = $this->user(User::ROLE_STAFF);
        $item = $this->item();

        $this->actingAs($staff)->get(route('admin.priority-items.show', $item))
            ->assertOk()
            ->assertSee('No checklist items have been added yet.')
            ->assertSee('Checklist items will appear here once they are assigned.')
            ->assertDontSee('id="addChecklistModal"', false);

        $this->actingAs($admin)->get(route('admin.priority-items.show', $item))
            ->assertOk()
            ->assertSee('id="addChecklistModal"', false)
            ->assertSee('No checklist items yet.', false);
    }

    /* ---------------------------------------------------------------
     | Separate derived urgency (Task 2)
     * -------------------------------------------------------------- */

    public function test_urgency_key_and_label_reflect_status_deadline_and_priority(): void
    {
        $this->travelTo(Carbon::parse('2026-06-15 09:00:00'));

        $cases = [
            'overdue' => [['due_date' => '2026-06-14'], 'overdue', 'OVERDUE', 'badge-danger'],
            'urgent_action' => [['priority' => PriorityItem::PRIORITY_URGENT, 'due_date' => '2026-06-20'], 'urgent_action', 'URGENT — ACTION NOW', 'badge-urgent'],
            'urgent_no_deadline' => [['priority' => PriorityItem::PRIORITY_URGENT, 'due_date' => null], 'urgent_action', 'URGENT — ACTION NOW', 'badge-urgent'],
            'due_today' => [['due_date' => '2026-06-15'], 'due_today', 'DUE TODAY', 'badge-warn'],
            'due_tomorrow' => [['due_date' => '2026-06-16'], 'due_tomorrow', 'DUE TOMORROW', 'badge-info'],
            'due_soon' => [['due_date' => '2026-06-17'], 'due_soon', 'DUE SOON', 'badge-info'],
            'on_track_null' => [['due_date' => null], 'on_track', 'ON TRACK', 'badge-neutral'],
            'on_track_far' => [['due_date' => '2026-08-01'], 'on_track', 'ON TRACK', 'badge-neutral'],
            'completed' => [['status' => PriorityItem::STATUS_COMPLETED, 'due_date' => '2026-06-01'], 'completed', 'COMPLETED', 'badge-success'],
        ];

        foreach ($cases as $name => $case) {
            [$overrides, $key, $label, $badge] = $case;
            $item = $this->item($overrides);

            $this->assertSame($key, $item->urgencyKey(), $name);
            $this->assertSame($label, $item->urgencyLabel(), $name);
            $this->assertSame($badge, $item->urgencyBadgeClass(), $name);
        }
    }

    public function test_urgency_filter_returns_only_matching_items(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $this->travelTo(Carbon::parse('2026-06-15 09:00:00'));

        $this->item(['task_lesson' => 'Overdue task', 'due_date' => '2026-06-13']);
        $this->item(['task_lesson' => 'Today task', 'due_date' => '2026-06-15']);
        $this->item(['task_lesson' => 'Tomorrow task', 'due_date' => '2026-06-16']);
        $this->item(['task_lesson' => 'Soon task', 'due_date' => '2026-06-17']);
        $this->item(['task_lesson' => 'On track task', 'due_date' => '2026-08-01']);
        $this->item(['task_lesson' => 'Completed task', 'due_date' => '2026-06-10', 'status' => PriorityItem::STATUS_COMPLETED]);

        $this->actingAs($admin)->get(route('admin.priority-items.index', ['urgency' => PriorityItem::URGENCY_OVERDUE]))
            ->assertOk()->assertSee('Overdue task')->assertDontSee('Today task');

        $this->actingAs($admin)->get(route('admin.priority-items.index', ['urgency' => PriorityItem::URGENCY_DUE_TODAY]))
            ->assertOk()->assertSee('Today task')->assertDontSee('Overdue task');

        $this->actingAs($admin)->get(route('admin.priority-items.index', ['urgency' => PriorityItem::URGENCY_DUE_TOMORROW]))
            ->assertOk()->assertSee('Tomorrow task')->assertDontSee('Today task');

        $this->actingAs($admin)->get(route('admin.priority-items.index', ['urgency' => PriorityItem::URGENCY_DUE_SOON]))
            ->assertOk()->assertSee('Soon task')->assertDontSee('On track task');

        $this->actingAs($admin)->get(route('admin.priority-items.index', ['urgency' => PriorityItem::URGENCY_COMPLETED]))
            ->assertOk()->assertSee('Completed task')->assertDontSee('Overdue task');
    }

    public function test_attention_summary_counts_visible_action_required_records(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $this->travelTo(Carbon::parse('2026-06-15 09:00:00'));

        $this->item(['task_lesson' => 'Overdue', 'due_date' => '2026-06-10']);
        $this->item(['task_lesson' => 'Urgent', 'priority' => PriorityItem::PRIORITY_URGENT, 'due_date' => '2026-06-30']);
        $this->item(['task_lesson' => 'Today', 'due_date' => '2026-06-15']);
        $this->item(['task_lesson' => 'Tomorrow', 'due_date' => '2026-06-16']);
        $this->item(['task_lesson' => 'Completed', 'due_date' => '2026-06-01', 'status' => PriorityItem::STATUS_COMPLETED]);

        $response = $this->actingAs($admin)->get(route('admin.priority-items.index'))->assertOk();

        $summary = $response->viewData('summary');
        $this->assertSame(3, $summary['action_required']);
        $this->assertSame(1, $summary['overdue']);
        $this->assertSame(1, $summary['due_today']);
        $this->assertSame(1, $summary['due_tomorrow']);
        $this->assertSame(1, $summary['completed']);
    }

    public function test_index_shows_evidence_file_count(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $item = $this->item(['task_lesson' => 'Has proof']);

        foreach (['a.png', 'b.png'] as $name) {
            $this->actingAs($admin)->post(route('admin.priority-items.evidence.add', $item), [
                'evidence' => UploadedFile::fake()->image($name, 40, 40),
            ])->assertRedirect();
        }

        $this->actingAs($admin)->get(route('admin.priority-items.index'))
            ->assertOk()
            ->assertSee('2 files');
    }

    public function test_staff_sees_derived_urgency_and_can_filter_by_it(): void
    {
        $staff = $this->user(User::ROLE_STAFF);
        $this->travelTo(Carbon::parse('2026-06-15 09:00:00'));

        $this->item([
            'task_lesson' => 'Staff overdue',
            'assigned_staff_id' => $staff->id,
            'due_date' => '2026-06-01',
        ]);

        $this->actingAs($staff)->get(route('admin.priority-items.index'))
            ->assertOk()
            ->assertSee('Staff overdue')
            ->assertSee('OVERDUE');

        $this->actingAs($staff)->get(route('admin.priority-items.index', ['urgency' => PriorityItem::URGENCY_OVERDUE]))
            ->assertOk()
            ->assertSee('Staff overdue');
    }

    /* ---------------------------------------------------------------
     | Overdue phrasing and completion audit trail
     * -------------------------------------------------------------- */

    public function test_overdue_days_agrees_with_the_existing_deadline_label(): void
    {
        $this->travelTo(Carbon::parse('2026-06-15 09:00:00'));

        $overdue = $this->item(['due_date' => '2026-06-10']);
        $this->assertSame(5, $overdue->overdueDays());
        $this->assertSame('OVERDUE BY 5 DAYS', $overdue->deadlineLabel());
        $this->assertTrue($overdue->isOverdue());

        $future = $this->item(['due_date' => '2026-06-20']);
        $this->assertNull($future->overdueDays());
        $this->assertFalse($future->isOverdue());

        // A completed item is never overdue, so it must not report overdue days.
        $done = $this->item([
            'status' => PriorityItem::STATUS_COMPLETED,
            'due_date' => '2026-06-10',
        ]);
        $this->assertNull($done->overdueDays());
        $this->assertFalse($done->isOverdue());

        // An undated item cannot be overdue either.
        $undated = $this->item(['due_date' => null]);
        $this->assertNull($undated->overdueDays());
    }

    public function test_list_and_detail_surface_how_many_days_an_item_is_overdue(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $this->travelTo(Carbon::parse('2026-06-15 09:00:00'));

        $this->item([
            'task_lesson' => 'Deeply overdue item',
            'due_date' => '2026-06-08',
        ]);

        $this->actingAs($admin)->get(route('admin.priority-items.index'))
            ->assertOk()
            ->assertSee('7 days overdue')
            ->assertSee('priority-row-overdue', false);

        $this->actingAs($admin)->get(route('admin.priority-items.show', PriorityItem::first()))
            ->assertOk()
            ->assertSee('7 days overdue')
            ->assertSee('priority-detail-overdue', false);
    }

    public function test_completing_an_item_records_when_and_by_whom(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $item = $this->item();

        $this->assertNull($item->completed_at);
        $this->assertNull($item->completed_by);

        $this->travelTo(Carbon::parse('2026-06-15 09:30:00'));

        $this->actingAs($admin)->put(route('admin.priority-items.update', $item), [
            'task_lesson' => $item->task_lesson,
            'type' => $item->type,
            'priority' => $item->priority,
            'status' => PriorityItem::STATUS_COMPLETED,
        ])->assertRedirect();

        $item->refresh();

        $this->assertSame(PriorityItem::STATUS_COMPLETED, $item->status);
        $this->assertTrue($item->isCompleted());
        $this->assertNotNull($item->completed_at);
        $this->assertSame($admin->id, $item->completed_by);
        $this->assertTrue($item->completer->is($admin));
        $this->assertSame('2026-06-15', $item->completed_at->format('Y-m-d'));
    }

    public function test_completing_an_item_directly_on_create_records_the_creator(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $this->travelTo(Carbon::parse('2026-06-15 10:00:00'));

        $this->actingAs($admin)->post(route('admin.priority-items.store'), [
            'task_lesson' => 'Created already done',
            'type' => PriorityItem::TYPE_PRIORITY_TASK,
            'priority' => PriorityItem::PRIORITY_LOW,
            'status' => PriorityItem::STATUS_COMPLETED,
            'due_date' => '2026-06-20',
        ])->assertRedirect(route('admin.priority-items.index'));

        $item = PriorityItem::where('task_lesson', 'Created already done')->firstOrFail();

        $this->assertSame(PriorityItem::STATUS_COMPLETED, $item->status);
        $this->assertSame($admin->id, $item->completed_by);
        $this->assertNotNull($item->completed_at);
    }

    public function test_completion_metadata_is_not_stamped_on_unrelated_updates(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $item = $this->item();

        $this->actingAs($admin)->put(route('admin.priority-items.update', $item), [
            'task_lesson' => $item->task_lesson,
            'type' => $item->type,
            'priority' => PriorityItem::PRIORITY_HIGH,
            'status' => PriorityItem::STATUS_PENDING,
        ])->assertRedirect();

        $item->refresh();

        $this->assertSame(PriorityItem::PRIORITY_HIGH, $item->priority);
        $this->assertSame(PriorityItem::STATUS_PENDING, $item->status);
        $this->assertNull($item->completed_at);
        $this->assertNull($item->completed_by);
    }

    public function test_completed_detail_page_shows_the_recorded_completion_trail(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $this->travelTo(Carbon::parse('2026-06-15 09:30:00'));

        $item = $this->item(['task_lesson' => 'Finished work']);

        $this->actingAs($admin)->put(route('admin.priority-items.update', $item), [
            'task_lesson' => $item->task_lesson,
            'type' => $item->type,
            'priority' => $item->priority,
            'status' => PriorityItem::STATUS_COMPLETED,
        ])->assertRedirect();

        $this->actingAs($admin)->get(route('admin.priority-items.show', $item))
            ->assertOk()
            ->assertSee('priority-complete-banner', false)
            ->assertSee('COMPLETED')
            ->assertSee('Completed on:')
            ->assertSee('June 15, 2026')
            ->assertSee($admin->name);

        // A completed item no longer needs overdue nagging, so the banner is hidden.
        $this->travelTo(Carbon::parse('2026-08-01 09:00:00'));

        $this->actingAs($admin)->get(route('admin.priority-items.show', $item))
            ->assertOk()
            ->assertDontSee('Priority guidance')
            ->assertDontSee('days overdue');
    }

    public function test_legacy_completed_item_without_audit_data_does_not_invent_it(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);

        // Simulate a row that was completed before the audit columns existed.
        $item = $this->item([
            'status' => PriorityItem::STATUS_COMPLETED,
            'completed_at' => null,
            'completed_by' => null,
        ]);

        $this->assertTrue($item->isCompleted());
        $this->assertNull($item->completionDateFormatted());
        $this->assertNull($item->completer);

        $this->actingAs($admin)->get(route('admin.priority-items.show', $item))
            ->assertOk()
            ->assertSee('priority-complete-banner', false)
            ->assertSee('Completed on:')
            ->assertSee('Completed by:');
    }

    public function test_deleting_the_recording_user_keeps_the_completion_date(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $this->travelTo(Carbon::parse('2026-06-15 09:30:00'));

        $item = $this->item(['task_lesson' => 'Survives completer deletion']);
        $this->actingAs($admin)->put(route('admin.priority-items.update', $item), [
            'task_lesson' => $item->task_lesson,
            'type' => $item->type,
            'priority' => $item->priority,
            'status' => PriorityItem::STATUS_COMPLETED,
        ])->assertRedirect();

        // Users are soft-deleted, so the FK only clears on a hard delete.
        $admin->forceDelete();

        $item->refresh();

        $this->assertNull($item->completed_by);
        $this->assertNull($item->completer);
        $this->assertSame('2026-06-15', $item->completed_at->format('Y-m-d'));
    }

    public function test_detail_page_shows_checklist_completion_separately_from_item_status(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $item = $this->item();

        foreach (['Step one', 'Step two'] as $title) {
            $this->actingAs($admin)->post(route('admin.priority-items.checklist.add', $item), [
                'title' => $title,
            ])->assertRedirect();
        }

        $first = ChecklistItem::where('title', 'Step one')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.priority-items.checklist.toggle', $item), [
            'checklist_item_id' => $first->id,
        ])->assertRedirect();

        $this->actingAs($admin)->get(route('admin.priority-items.show', $item))
            ->assertOk()
            ->assertSee('1 of 2 complete')
            ->assertSee('50%')
            ->assertDontSee('All checklist items completed');

        // Completing every checklist item still must not complete the parent item.
        $second = ChecklistItem::where('title', 'Step two')->firstOrFail();
        $this->actingAs($admin)->post(route('admin.priority-items.checklist.toggle', $item), [
            'checklist_item_id' => $second->id,
        ])->assertRedirect();

        $item->refresh();
        $this->assertSame(PriorityItem::STATUS_PENDING, $item->status);

        $this->actingAs($admin)->get(route('admin.priority-items.show', $item))
            ->assertOk()
            ->assertSee('2 of 2 complete')
            ->assertSee('100%')
            ->assertSee('All checklist items completed')
            ->assertSee('This does not mark the item as completed on its own.');
    }

    public function test_evidence_section_uses_cards_and_hints_who_can_upload(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $staff = $this->user(User::ROLE_STAFF);
        $item = $this->item(['assigned_staff_id' => $staff->id]);

        $this->actingAs($admin)->post(route('admin.priority-items.evidence.add', $item), [
            'evidence' => UploadedFile::fake()->image('proof.png', 60, 60),
        ])->assertRedirect();

        $this->actingAs($staff)->get(route('admin.priority-items.show', $item))
            ->assertOk()
            ->assertSee('priority-evidence-card', false)
            ->assertSee('priority-evidence-hint', false)
            ->assertSee('Any staff member who can see this item can add evidence.')
            // Assigned staff can see and download the proof, but get no Delete control.
            ->assertDontSee('Remove this evidence?');
    }

    public function test_list_keeps_type_aware_evidence_indicator_and_empty_state(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);

        $this->item(['task_lesson' => 'Item with no proof']);

        $this->actingAs($admin)->get(route('admin.priority-items.index'))
            ->assertOk()
            ->assertSee('Item with no proof')
            ->assertSee('No evidence')
            ->assertSee('priority-evidence-empty', false);
    }

    public function test_detail_page_uses_a_type_aware_heading(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);

        $this->item([
            'task_lesson' => 'Teach the new batch',
            'type' => PriorityItem::TYPE_LESSON_LEARNED,
        ]);

        $this->actingAs($admin)->get(route('admin.priority-items.show', PriorityItem::first()))
            ->assertOk()
            ->assertSee('Lesson Learned #1');
    }
}
