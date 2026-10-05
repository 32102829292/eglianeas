<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminConfidentialityAcknowledged;
use App\Models\KaizenConcern;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Covers the employee improvement board behaviour added on top of the existing
 * Kaizen module:
 *
 *  - every operational employee can submit their own Employee Suggestion;
 *  - the board/tag presentation renders on the list and detail screens;
 *  - an Implemented suggestion is visually unmistakable and shows its date;
 *  - the Not Implemented outcome is available alongside it;
 *  - only the pre-existing admin workflow can finalise a suggestion, so an
 *    employee can never mark anyone else's Kaizen as Implemented;
 *  - the implementation evidence area reuses the existing multi-file
 *    infrastructure and shows a neutral empty state.
 *
 * Nothing here replaces the original Kaizen tests; the pre-existing
 * KaizenPriorityPageTest continues to cover assignment, visibility and the
 * signed evidence routes.
 */
class KaizenEmployeeSuggestionTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, ?string $name = null): User
    {
        return User::create([
            'name' => $name ?? (ucfirst($role).' Tester'),
            'email' => $role.'.'.uniqid().'@example.com',
            'password' => bcrypt('secret'),
            'role' => $role,
            'email_verified_at' => now(),
            'confidentiality_acknowledged_at' => now(),
            'confidentiality_ack_version' => EnsureAdminConfidentialityAcknowledged::CURRENT_VERSION,
        ]);
    }

    private function concern(
        ?User $creator = null,
        string $status = KaizenConcern::STATUS_PENDING,
        ?User $assignee = null,
        ?string $type = KaizenConcern::TYPE_EMPLOYEE_SUGGESTION
    ): KaizenConcern {
        return KaizenConcern::create([
            'type' => $type,
            'date_identified' => now()->subDays(2),
            'challenge' => 'Client documents arrive without a checklist.',
            'recommended_solution' => 'Add a standardized document checklist for every client.',
            'status' => $status,
            'assigned_staff_id' => $assignee?->id,
            'created_by' => ($creator ?? $this->user(User::ROLE_ADMIN))->id,
        ]);
    }

    /* ---------------------------------------------------------------
     | 1. Employee Suggestion submission
     * -------------------------------------------------------------- */

    public function test_every_operational_employee_can_open_the_submit_improvement_screen(): void
    {
        foreach ([User::ROLE_STAFF, User::ROLE_SUPERVISOR, User::ROLE_ADMIN] as $role) {
            $this->actingAs($this->user($role))
                ->get(route('admin.kaizen-concerns.submit'))
                ->assertOk()
                ->assertSee('Submit Improvement')
                ->assertSee('Challenge / Opportunity for Improvement')
                ->assertSee('Recommended Solution');
        }
    }

    public function test_clients_cannot_open_the_submit_improvement_screen(): void
    {
        $this->actingAs($this->user(User::ROLE_CLIENT))
            ->get(route('admin.kaizen-concerns.submit'))
            ->assertForbidden();

        $this->actingAs($this->user(User::ROLE_CLIENT))
            ->post(route('admin.kaizen-concerns.submit.store'), [
                'date_identified' => now()->format('Y-m-d'),
                'challenge' => 'c',
                'recommended_solution' => 's',
            ])
            ->assertForbidden();
    }

    public function test_an_employee_suggestion_is_stored_as_their_own_pending_unassigned_record(): void
    {
        $staff = $this->user(User::ROLE_STAFF, 'Angeli');

        $this->actingAs($staff)->post(route('admin.kaizen-concerns.submit.store'), [
            'date_identified' => '2026-10-05',
            'challenge' => 'Improve client document processing',
            'recommended_solution' => 'Add a standardized document checklist for every client.',
            'notes' => 'Seen in three engagements this month.',
        ])->assertRedirect();

        $concern = KaizenConcern::query()->latest('id')->firstOrFail();

        $this->assertSame('Improve client document processing', $concern->challenge);
        $this->assertSame($staff->id, $concern->created_by);
        $this->assertSame(KaizenConcern::STATUS_PENDING, $concern->status);
        $this->assertSame(KaizenConcern::TYPE_EMPLOYEE_SUGGESTION, $concern->type);
        $this->assertNull($concern->assigned_staff_id);
        $this->assertNull($concern->implementation_date);

        // The submitter lands on their own suggestion, tagged and attributed.
        $this->actingAs($staff)->get(route('admin.kaizen-concerns.show', $concern))
            ->assertOk()
            ->assertSee('Employee Suggestion')
            ->assertSee('Angeli')
            ->assertSee('Improve client document processing');

        // And it appears on the board too.
        $this->actingAs($staff)->get(route('admin.kaizen-concerns.index'))
            ->assertOk()
            ->assertSee('Employee Suggestion')
            ->assertSee('Angeli')
            ->assertSee('Improve client document processing');
    }

    public function test_the_submitter_name_comes_from_the_session_and_cannot_be_spoofed(): void
    {
        $staff = $this->user(User::ROLE_STAFF, 'Angeli Gabaut');

        // The submit screen shows the authenticated employee, with no field to
        // type a name into.
        $screen = $this->actingAs($staff)->get(route('admin.kaizen-concerns.submit'))->assertOk();
        $screen->assertSee('Employee Suggestion');
        $screen->assertSee('Angeli Gabaut');
        $screen->assertDontSee('name="created_by"', false);
        $screen->assertDontSee('name="submitter"', false);

        // Posting a forged submitter is ignored; created_by stays the session user.
        $this->actingAs($staff)->post(route('admin.kaizen-concerns.submit.store'), [
            'date_identified' => '2026-10-05',
            'challenge' => 'Spoof attempt',
            'recommended_solution' => 'x',
            'created_by' => $this->user(User::ROLE_ADMIN)->id,
            'status' => KaizenConcern::STATUS_IMPLEMENTED,
            'type' => KaizenConcern::TYPE_ADMIN_CONCERN,
        ])->assertRedirect();

        $concern = KaizenConcern::query()->latest('id')->firstOrFail();

        $this->assertSame($staff->id, $concern->created_by);
        $this->assertSame(KaizenConcern::STATUS_PENDING, $concern->status);

        // The record is an Employee Suggestion because of where it was
        // submitted from; the request cannot claim it is anything else.
        $this->assertSame(KaizenConcern::TYPE_EMPLOYEE_SUGGESTION, $concern->type);
    }

    public function test_an_employee_may_suggest_an_optional_target_date(): void
    {
        $staff = $this->user(User::ROLE_STAFF);

        $this->actingAs($staff)->post(route('admin.kaizen-concerns.submit.store'), [
            'date_identified' => '2026-10-05',
            'challenge' => 'c',
            'recommended_solution' => 's',
            'target_date' => '2026-11-30',
        ])->assertRedirect();

        $this->assertSame(
            '2026-11-30',
            KaizenConcern::query()->latest('id')->firstOrFail()->target_date?->format('Y-m-d')
        );
    }

    public function test_submitting_a_suggestion_validates_the_improvement_fields(): void
    {
        $staff = $this->user(User::ROLE_STAFF);

        $this->actingAs($staff)->post(route('admin.kaizen-concerns.submit.store'), [
            'date_identified' => '2026-10-05',
            'challenge' => '',
            'recommended_solution' => '',
        ])->assertSessionHasErrors(['challenge', 'recommended_solution']);

        $this->assertSame(0, KaizenConcern::query()->count());
    }

    /* ---------------------------------------------------------------
     | 2. Employee Suggestion tag + status labels on the board
     * -------------------------------------------------------------- */

    public function test_the_board_renders_the_employee_suggestion_tag_and_all_four_statuses(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);

        $pending = $this->concern($admin, KaizenConcern::STATUS_PENDING);
        $inProgress = $this->concern($admin, KaizenConcern::STATUS_IN_PROGRESS);
        $notImplemented = $this->concern($admin, KaizenConcern::STATUS_NOT_IMPLEMENTED);
        $implemented = $this->concern($admin, KaizenConcern::STATUS_IMPLEMENTED);

        $response = $this->actingAs($admin)->get(route('admin.kaizen-concerns.index'))->assertOk();

        // Every entry is presented as an employee suggestion.
        $response->assertSee('Employee Suggestion');

        foreach ([$pending, $inProgress, $implemented, $notImplemented] as $concern) {
            $this->actingAs($admin)->get(route('admin.kaizen-concerns.show', $concern))
                ->assertOk()
                ->assertSee('Employee Suggestion')
                ->assertSee('Suggested Solution');
        }

        // The four board outcomes are all offered to an admin, and the
        // pre-existing Overdue outcome is preserved alongside them.
        $labels = array_values(KaizenConcern::STATUSES);

        foreach (['Pending', 'In Progress', 'Implemented', 'Not Implemented', 'Overdue'] as $label) {
            $this->assertContains($label, $labels);
        }

        $this->assertSame(
            [KaizenConcern::STATUS_PENDING, KaizenConcern::STATUS_IN_PROGRESS, KaizenConcern::STATUS_IMPLEMENTED, KaizenConcern::STATUS_NOT_IMPLEMENTED],
            array_slice(array_keys(KaizenConcern::STATUSES), 0, 4)
        );

        // And the filter dropdown offers them.
        foreach (array_keys(KaizenConcern::STATUSES) as $value) {
            $response->assertSee('value="'.$value.'"', false);
        }
    }

    /* ---------------------------------------------------------------
     | 4. The board is a compact 7-column table, not a 10-column grid
     * -------------------------------------------------------------- */

    public function test_the_board_lists_only_the_priority_columns(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $this->concern($admin, KaizenConcern::STATUS_PENDING);

        $content = $this->actingAs($admin)->get(route('admin.kaizen-concerns.index'))->assertOk()->getContent();

        foreach ([
            'Employee Suggestion',
            'Submitted By',
            'Status',
            'Target Date',
            'Implementation',
            'Evidence',
            'Actions',
        ] as $heading) {
            $this->assertStringContainsString(
                '>'.$heading.'<',
                $content,
                "The board must keep the \"{$heading}\" column."
            );
        }

        // Less important details live on the View page (and in the mobile
        // card) rather than taking up a table column, which is what allowed the
        // table to drop from 10 columns to 7.
        $table = $this->boardTable($content);

        foreach (['Recommended Solution', 'Suggested Solution', 'Assigned Staff', 'Date Identified', 'Notes', 'Checklist'] as $heading) {
            $this->assertStringNotContainsString(
                '>'.$heading.'<',
                $table,
                "\"{$heading}\" must not take up a column on the board table."
            );
        }

        // Each row shows who submitted it, next to the suggestion itself.
        $this->assertStringContainsString('Submitted by', $content);
        $this->assertStringContainsString($admin->name, $content);
    }

    /**
     * The rendered board table only, so assertions about "what is a column"
     * are not confused by the admin create form, the filters or the mobile
     * card list, which legitimately repeat the same words.
     */
    private function boardTable(string $html): string
    {
        $this->assertSame(
            1,
            preg_match('/<table[^>]*kaizen-board-table[^>]*>(.*?)<\/table>/s', $html, $m),
            'The board table must be present exactly once.'
        );

        return $m[0];
    }

    public function test_every_board_status_uses_the_shared_status_indicator(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);

        $this->concern($admin, KaizenConcern::STATUS_PENDING);
        $this->concern($admin, KaizenConcern::STATUS_IN_PROGRESS);
        $this->concern($admin, KaizenConcern::STATUS_IMPLEMENTED);
        $this->concern($admin, KaizenConcern::STATUS_NOT_IMPLEMENTED);
        $this->concern($admin, KaizenConcern::STATUS_OVERDUE);

        $content = $this->actingAs($admin)->get(route('admin.kaizen-concerns.index'))->assertOk()->getContent();

        foreach (['Pending', 'In Progress', 'Implemented', 'Not Implemented', 'Overdue'] as $label) {
            $this->assertStringContainsString($label, $content);
        }

        // Pending / In Progress / Not Implemented are colour-differentiated via
        // their own dot class, and Implemented is the strong green pill.
        foreach (['kaizen-dot-pending', 'kaizen-dot-progress', 'kaizen-dot-none', 'kaizen-dot-overdue', 'kaizen-status-done'] as $class) {
            $this->assertStringContainsString($class, $content, "Missing status colour class {$class}.");
        }
    }

    /* ---------------------------------------------------------------
     | 5. Submit screen is a wide, guided three-section form
     * -------------------------------------------------------------- */

    public function test_the_submit_screen_is_a_wide_three_section_form(): void
    {
        $staff = $this->user(User::ROLE_STAFF);

        $response = $this->actingAs($staff)->get(route('admin.kaizen-concerns.submit'))->assertOk();

        // Section 1 - the employee's actual input.
        $response->assertSee('Improvement Idea')
            ->assertSee('Challenge / Opportunity for Improvement')
            ->assertSee('Recommended Solution');

        // Section 2 - planning, owned by the review team.
        $response->assertSee('Planning')
            ->assertSee('Assigned Staff');

        // Section 3 - implementation and evidence, closed while pending.
        $response->assertSee('Implementation &amp; Evidence', false)
            ->assertSee('Implementation Date')
            ->assertSee('Implementation Evidence');

        // Wide enough for the three sections to breathe.
        $response->assertSee('kaizen-submit-card', false);
        $this->assertMatchesRegularExpression(
            '/\.kaizen-submit-card\s*\{[^}]*max-width/',
            file_get_contents(public_path('css/app.css')),
            'The submit form must be given a wider max-width than the default narrow form.'
        );

        // Sections 2 and 3 are visibly secondary while the idea is Pending.
        $this->assertSame(
            2,
            substr_count($response->getContent(), 'kaizen-section-secondary'),
            'Planning and Implementation sections must read as secondary.'
        );
    }

    public function test_the_submit_screen_does_not_let_an_employee_finalise_their_own_suggestion(): void
    {
        $staff = $this->user(User::ROLE_STAFF);

        $content = $this->actingAs($staff)->get(route('admin.kaizen-concerns.submit'))->assertOk()->getContent();

        foreach (['name="status"', 'name="implementation_date"', 'name="assigned_staff_id"'] as $input) {
            $this->assertStringNotContainsString($input, $content, "Employees must not post {$input}.");
        }

        // The planning fields are shown read-only so the flow is still clear.
        $this->assertStringContainsString('Pending', $content);
        $this->assertStringContainsString('Not assigned yet', $content);
        $this->assertStringContainsString('readonly disabled', $content);
    }

    /* ---------------------------------------------------------------
     | 3. Implemented state is visually unmistakable
     * -------------------------------------------------------------- */

    public function test_an_implemented_suggestion_shows_the_badge_date_and_confirmation_text(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $concern = $this->concern($admin, KaizenConcern::STATUS_IMPLEMENTED);
        $concern->update(['implementation_date' => '2026-10-05']);

        // The detail page spells the implementation date out in full, because
        // there is room to read it there.
        $this->actingAs($admin)->get(route('admin.kaizen-concerns.show', $concern))
            ->assertOk()
            ->assertSee('IMPLEMENTED')
            ->assertSee('Implemented on:')
            ->assertSee('October 5, 2026')
            ->assertSee('This improvement has been implemented.')
            ->assertSee('kaizen-implemented-banner', false);

        // The board row marks it implemented too, with a visible row tint
        // rather than relying on the Status pill colour alone. It keeps the
        // compact date because the column is narrow.
        $board = $this->actingAs($admin)->get(route('admin.kaizen-concerns.index'))->assertOk();
        $board->assertSee('kaizen-status-done', false);
        $board->assertSee('kaizen-row-implemented', false);
        $board->assertSee('Implemented on:');
        $board->assertSee('Oct 5, 2026');
    }

    public function test_a_not_implemented_suggestion_is_labelled_clearly(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $concern = $this->concern($admin, KaizenConcern::STATUS_NOT_IMPLEMENTED);

        $this->actingAs($admin)->get(route('admin.kaizen-concerns.show', $concern))
            ->assertOk()
            ->assertSee('NOT IMPLEMENTED')
            ->assertSee('This improvement suggestion was not implemented.');
    }

    public function test_the_implemented_alias_still_points_at_the_stored_completed_value(): void
    {
        // The original terminal status name is preserved so existing callers
        // (and existing records) keep working after the relabelling.
        $this->assertSame(KaizenConcern::STATUS_IMPLEMENTED, KaizenConcern::STATUS_COMPLETED);
        $this->assertSame('completed', KaizenConcern::STATUS_IMPLEMENTED);
    }

    /* ---------------------------------------------------------------
     | 4. Marking Implemented stays an admin-only finalisation
     * -------------------------------------------------------------- */

    public function test_marking_a_suggestion_implemented_fills_in_the_implementation_date(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $concern = $this->concern($admin);

        $this->actingAs($admin)->put(route('admin.kaizen-concerns.update', $concern), [
            'date_identified' => $concern->date_identified->format('Y-m-d'),
            'challenge' => $concern->challenge,
            'recommended_solution' => $concern->recommended_solution,
            'status' => KaizenConcern::STATUS_IMPLEMENTED,
        ])->assertRedirect();

        $concern->refresh();
        $this->assertSame(KaizenConcern::STATUS_IMPLEMENTED, $concern->status);
        $this->assertTrue($concern->isImplemented());
        $this->assertNotNull($concern->implementation_date);
    }

    public function test_an_explicit_implementation_date_is_not_overwritten(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $concern = $this->concern($admin);

        $this->actingAs($admin)->put(route('admin.kaizen-concerns.update', $concern), [
            'date_identified' => $concern->date_identified->format('Y-m-d'),
            'challenge' => $concern->challenge,
            'recommended_solution' => $concern->recommended_solution,
            'status' => KaizenConcern::STATUS_IMPLEMENTED,
            'implementation_date' => '2026-09-15',
        ])->assertRedirect();

        $this->assertSame('2026-09-15', $concern->fresh()->implementation_date?->format('Y-m-d'));
    }

    public function test_an_employee_cannot_mark_a_suggestion_implemented_or_edit_the_board(): void
    {
        $staff = $this->user(User::ROLE_STAFF);
        $concern = $this->concern($this->user(User::ROLE_ADMIN));

        $payload = [
            'date_identified' => $concern->date_identified->format('Y-m-d'),
            'challenge' => $concern->challenge,
            'recommended_solution' => $concern->recommended_solution,
            'status' => KaizenConcern::STATUS_IMPLEMENTED,
        ];

        $this->actingAs($staff)->put(route('admin.kaizen-concerns.update', $concern), $payload)->assertForbidden();
        $this->actingAs($staff)->get(route('admin.kaizen-concerns.edit', $concern))->assertForbidden();

        $concern->refresh();
        $this->assertSame(KaizenConcern::STATUS_PENDING, $concern->status);
        $this->assertNull($concern->implementation_date);
    }

    public function test_an_employee_cannot_mark_their_own_suggestion_implemented(): void
    {
        $staff = $this->user(User::ROLE_STAFF);

        $this->actingAs($staff)->post(route('admin.kaizen-concerns.submit.store'), [
            'date_identified' => '2026-10-05',
            'challenge' => 'My own suggestion',
            'recommended_solution' => 'Fix it this way.',
        ]);

        $concern = KaizenConcern::query()->latest('id')->firstOrFail();

        $this->actingAs($staff)->put(route('admin.kaizen-concerns.update', $concern), [
            'date_identified' => '2026-10-05',
            'challenge' => 'My own suggestion',
            'recommended_solution' => 'Fix it this way.',
            'status' => KaizenConcern::STATUS_IMPLEMENTED,
        ])->assertForbidden();

        $this->assertSame(KaizenConcern::STATUS_PENDING, $concern->fresh()->status);
    }

    public function test_a_supervisor_cannot_finalise_a_suggestion_either(): void
    {
        $supervisor = $this->user(User::ROLE_SUPERVISOR);
        $concern = $this->concern($this->user(User::ROLE_ADMIN));

        $this->actingAs($supervisor)->put(route('admin.kaizen-concerns.update', $concern), [
            'date_identified' => $concern->date_identified->format('Y-m-d'),
            'challenge' => $concern->challenge,
            'recommended_solution' => $concern->recommended_solution,
            'status' => KaizenConcern::STATUS_IMPLEMENTED,
        ])->assertForbidden();

        $this->assertSame(KaizenConcern::STATUS_PENDING, $concern->fresh()->status);
    }

    public function test_checklist_permissions_are_unchanged(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $staff = $this->user(User::ROLE_STAFF);
        $concern = $this->concern(null);

        $this->actingAs($admin)->post(route('admin.kaizen-concerns.checklist.add', $concern), [
            'title' => 'Admin item',
        ])->assertRedirect();

        $this->actingAs($staff)->post(route('admin.kaizen-concerns.checklist.add', $concern), [
            'title' => 'Staff item',
        ])->assertForbidden();

        $this->assertDatabaseHas('checklist_items', ['title' => 'Admin item']);
        $this->assertDatabaseMissing('checklist_items', ['title' => 'Staff item']);
    }

    /* ---------------------------------------------------------------
     | 5. Implementation evidence on the improvement board
     * -------------------------------------------------------------- */

    public function test_evidence_upload_and_display_on_an_implemented_suggestion(): void
    {
        Storage::fake('local');

        $admin = $this->user(User::ROLE_ADMIN);
        $concern = $this->concern($admin, KaizenConcern::STATUS_IMPLEMENTED);
        $concern->update(['implementation_date' => now()]);

        $this->actingAs($admin)->post(route('admin.kaizen-concerns.evidence.add', $concern), [
            'evidence' => UploadedFile::fake()->create('document-checklist.png', 120, 'image/png'),
        ])->assertRedirect();

        $this->assertSame(1, $concern->evidences()->count());

        $this->actingAs($admin)->get(route('admin.kaizen-concerns.show', $concern))
            ->assertOk()
            ->assertSee('Implementation Evidence')
            ->assertSee('Attach proof that this improvement was implemented.')
            ->assertSee('document-checklist.png')
            ->assertSee('Added by')
            ->assertSee('1 Evidence File')
            ->assertSee('kaizen-evidence-item', false);
    }

    public function test_evidence_is_rendered_as_cards_and_can_still_be_viewed_and_downloaded(): void
    {
        Storage::fake('local');

        $admin = $this->user(User::ROLE_ADMIN);
        $concern = $this->concern($admin, KaizenConcern::STATUS_IMPLEMENTED);

        foreach (['new-workflow.png', 'client-checklist.pdf'] as $name) {
            $this->actingAs($admin)->post(route('admin.kaizen-concerns.evidence.add', $concern), [
                'evidence' => UploadedFile::fake()->create($name, 40, 'image/png'),
            ])->assertRedirect();
        }

        $evidence = $concern->evidences()->get();

        $this->assertCount(2, $evidence);

        $content = $this->actingAs($admin)->get(route('admin.kaizen-concerns.show', $concern))
            ->assertOk()
            ->assertSee('2 Evidence Files')
            ->assertSee('new-workflow.png')
            ->assertSee('client-checklist.pdf')
            ->getContent();

        $this->assertSame(
            2,
            substr_count($content, 'kaizen-evidence-item'),
            'Each evidence file must be rendered as its own card.'
        );

        // The signed view/download links are read back out of the rendered
        // markup rather than rebuilt here, so the test exercises the real URLs
        // (and stays deterministic — rebuilding them would race the second
        // boundary in the temporary signature's expiry).
        preg_match_all('/href="([^"]*\/evidence\/\d+[^"]*)"/', $content, $matches);

        $hrefs = array_map('html_entity_decode', $matches[1]);
        $views = array_values(array_filter($hrefs, fn ($url) => ! str_contains($url, '/download')));
        $downloads = array_values(array_filter($hrefs, fn ($url) => str_contains($url, '/download')));

        $this->assertCount(2, $views, 'Each evidence file needs a View link.');
        $this->assertCount(2, $downloads, 'Each evidence file needs a Download link.');

        // The existing signed evidence routes still serve the stored file, so
        // the evidence access pattern is untouched by the new layout.
        foreach (array_merge($views, $downloads) as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_the_empty_evidence_state_is_informational_not_an_error(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $concern = $this->concern($admin);

        $this->actingAs($admin)->get(route('admin.kaizen-concerns.show', $concern))
            ->assertOk()
            ->assertSee('Implementation Evidence')
            ->assertSee('No implementation evidence attached yet.')
            ->assertDontSee('form-error');
    }

    public function test_evidence_accepts_photos_screenshots_pdfs_and_documents(): void
    {
        Storage::fake('local');

        $admin = $this->user(User::ROLE_ADMIN);
        $concern = $this->concern($admin);

        $files = [
            'photo.jpg' => 'image/jpeg',
            'screenshot.png' => 'image/png',
            'document.pdf' => 'application/pdf',
        ];

        foreach ($files as $name => $mime) {
            $this->actingAs($admin)->post(route('admin.kaizen-concerns.evidence.add', $concern), [
                'evidence' => UploadedFile::fake()->create($name, 60, $mime),
            ])->assertRedirect();
        }

        $this->assertSame(3, $concern->evidences()->count());

        $response = $this->actingAs($admin)->get(route('admin.kaizen-concerns.show', $concern))->assertOk();
        foreach (array_keys($files) as $name) {
            $response->assertSee($name);
        }
    }

    public function test_an_employee_cannot_upload_evidence_to_another_employees_suggestion(): void
    {
        Storage::fake('local');

        $owner = $this->user(User::ROLE_STAFF, 'Angeli');
        $other = $this->user(User::ROLE_STAFF, 'Marco');
        $concern = $this->concern($owner, KaizenConcern::STATUS_IMPLEMENTED, $owner);

        $this->actingAs($other)
            ->post(route('admin.kaizen-concerns.evidence.add', $concern), [
                'evidence' => UploadedFile::fake()->create('nope.pdf', 60, 'application/pdf'),
            ])
            ->assertForbidden();

        $this->assertSame(0, $concern->evidences()->count());

        // The assignee can still document their own implementation.
        $this->actingAs($owner)
            ->post(route('admin.kaizen-concerns.evidence.add', $concern), [
                'evidence' => UploadedFile::fake()->create('owner-proof.png', 60, 'image/png'),
            ])
            ->assertRedirect();

        $this->assertSame(1, $concern->evidences()->count());
    }

    /* ---------------------------------------------------------------
     | 6. Detail page: Suggestion → Progress → Implementation → Evidence
     * -------------------------------------------------------------- */

    public function test_the_detail_page_shows_the_flow_the_tag_and_the_submitted_employee(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $concern = $this->concern($admin, KaizenConcern::STATUS_PENDING);

        $response = $this->actingAs($admin)->get(route('admin.kaizen-concerns.show', $concern))->assertOk();

        // Employee Suggestion identity is preserved on the detail screen.
        $response->assertSee('Employee Suggestion')
            ->assertSee('#'.$concern->id)
            ->assertSee('Submitted by:')
            ->assertSee($admin->name);

        // The four stages of the flow are spelled out, in order.
        $content = $response->getContent();
        $previous = -1;

        foreach (['Employee Suggestion', 'Progress', 'Implementation', 'Evidence'] as $stage) {
            $position = strpos($content, 'kaizen-flow-label">'.$stage);
            $this->assertNotFalse($position, "Missing flow stage: {$stage}");
            $this->assertGreaterThan($previous, $position, "Stage {$stage} is out of order.");
            $previous = $position;
        }

        // The overall status is visible in the header, before any scrolling.
        $response->assertSee('Current Status')
            ->assertSee('kaizen-hero-status', false)
            ->assertSee('Pending');
    }

    public function test_the_detail_page_shows_a_dash_until_an_implementation_date_exists(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $concern = $this->concern($admin, KaizenConcern::STATUS_IN_PROGRESS);

        $content = $this->actingAs($admin)->get(route('admin.kaizen-concerns.show', $concern))
            ->assertOk()
            ->assertSee('Implementation Date')
            ->getContent();

        $this->assertSame(1, substr_count($content, 'kaizen-detail-impl'));
        $this->assertStringNotContainsString('kaizen-detail-impl is-implemented', $content);

        // Once implemented, the same row highlights the real date instead.
        $concern->update([
            'status' => KaizenConcern::STATUS_IMPLEMENTED,
            'implementation_date' => '2026-10-05',
        ]);

        $this->actingAs($admin)->get(route('admin.kaizen-concerns.show', $concern))
            ->assertOk()
            ->assertSee('kaizen-detail-impl is-implemented', false)
            ->assertSee('Implemented on')
            ->assertSee('October 5, 2026');
    }

    public function test_checklist_progress_is_reported_without_changing_the_kaizen_status(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $concern = $this->concern($admin, KaizenConcern::STATUS_IN_PROGRESS);

        foreach (['Draft the new checklist', 'Roll it out to the team'] as $title) {
            $this->actingAs($admin)->post(route('admin.kaizen-concerns.checklist.add', $concern), [
                'title' => $title,
            ])->assertRedirect();
        }

        // Partially complete: progress is shown, no "ready" prompt yet.
        $items = $concern->checklistItems()->orderBy('sort_order')->get();

        $this->actingAs($admin)->post(route('admin.kaizen-concerns.checklist.toggle', $concern), [
            'checklist_item_id' => $items->first()->id,
        ])->assertRedirect();

        $partial = $this->actingAs($admin)->get(route('admin.kaizen-concerns.show', $concern))
            ->assertOk()
            ->assertSee('Implementation Progress')
            ->assertSee('1 of 2 complete')
            ->assertDontSee('Ready for Implementation Confirmation');

        // Completing the checklist must NOT finalise the suggestion.
        $concern->refresh();
        $this->assertSame(KaizenConcern::STATUS_IN_PROGRESS, $concern->status);
        $this->assertNull($concern->implementation_date);

        $this->actingAs($admin)->post(route('admin.kaizen-concerns.checklist.toggle', $concern), [
            'checklist_item_id' => $items->last()->id,
        ])->assertRedirect();

        $this->actingAs($admin)->get(route('admin.kaizen-concerns.show', $concern))
            ->assertOk()
            ->assertSee('2 of 2 complete')
            ->assertSee('All checklist items completed')
            ->assertSee('Ready for Implementation Confirmation');

        $concern->refresh();
        $this->assertSame(KaizenConcern::STATUS_IN_PROGRESS, $concern->status);
        $this->assertNull($concern->implementation_date);
    }

    public function test_an_admin_can_confirm_a_suggestion_as_implemented_from_the_detail_page(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $concern = $this->concern($admin, KaizenConcern::STATUS_IN_PROGRESS);

        // The authorized action is offered...
        $this->actingAs($admin)->get(route('admin.kaizen-concerns.show', $concern))
            ->assertOk()
            ->assertSee('Mark as Implemented')
            ->assertSee('Confirm implementation');

        // ...and it records the official status plus the implementation date.
        $this->actingAs($admin)
            ->post(route('admin.kaizen-concerns.implement', $concern))
            ->assertRedirect();

        $concern->refresh();
        $this->assertSame(KaizenConcern::STATUS_IMPLEMENTED, $concern->status);
        $this->assertSame(now()->format('Y-m-d'), $concern->implementation_date?->format('Y-m-d'));

        $this->actingAs($admin)->get(route('admin.kaizen-concerns.show', $concern))
            ->assertOk()
            ->assertSee('Confirmed as implemented')
            ->assertDontSee('Mark as Implemented');
    }

    public function test_confirming_as_implemented_keeps_an_existing_implementation_date(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $concern = $this->concern($admin, KaizenConcern::STATUS_IN_PROGRESS);
        $concern->update(['implementation_date' => '2026-09-01']);

        $this->actingAs($admin)->post(route('admin.kaizen-concerns.implement', $concern))->assertRedirect();

        $this->assertSame(
            '2026-09-01',
            $concern->refresh()->implementation_date?->format('Y-m-d')
        );
    }

    public function test_confirming_as_implemented_cannot_be_repeated(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $concern = $this->concern($admin, KaizenConcern::STATUS_IMPLEMENTED);

        $this->actingAs($admin)
            ->post(route('admin.kaizen-concerns.implement', $concern))
            ->assertStatus(422);
    }

    public function test_only_admins_may_confirm_a_suggestion_as_implemented(): void
    {
        foreach ([User::ROLE_STAFF, User::ROLE_SUPERVISOR] as $role) {
            $owner = $this->user($role);
            $admin = $this->user(User::ROLE_ADMIN);
            $concern = $this->concern($admin, KaizenConcern::STATUS_IN_PROGRESS, $owner);

            // No confirmation action is offered to anyone but an admin.
            $this->actingAs($owner)->get(route('admin.kaizen-concerns.show', $concern))
                ->assertOk()
                ->assertDontSee('Mark as Implemented');

            // And posting it directly is rejected.
            $this->actingAs($owner)
                ->post(route('admin.kaizen-concerns.implement', $concern))
                ->assertForbidden();

            $this->assertSame(
                KaizenConcern::STATUS_IN_PROGRESS,
                $concern->refresh()->status,
                "A {$role} must not be able to finalise a suggestion."
            );
        }
    }

    public function test_a_client_can_never_reach_the_confirmation_action(): void
    {
        $client = $this->user(User::ROLE_CLIENT);
        $concern = $this->concern($this->user(User::ROLE_ADMIN), KaizenConcern::STATUS_IN_PROGRESS);

        // Clients keep no visibility of Kaizen records at all.
        $this->actingAs($client)->get(route('admin.kaizen-concerns.show', $concern))->assertForbidden();
        $this->actingAs($client)->post(route('admin.kaizen-concerns.implement', $concern))->assertForbidden();

        $this->assertSame(KaizenConcern::STATUS_IN_PROGRESS, $concern->refresh()->status);
    }

    public function test_a_fully_completed_and_implemented_suggestion_shows_the_summary(): void
    {
        Storage::fake('local');

        $admin = $this->user(User::ROLE_ADMIN);
        $concern = $this->concern($admin, KaizenConcern::STATUS_IN_PROGRESS);

        $this->actingAs($admin)->post(route('admin.kaizen-concerns.checklist.add', $concern), [
            'title' => 'Only task',
        ])->assertRedirect();

        $this->actingAs($admin)->post(route('admin.kaizen-concerns.checklist.toggle', $concern), [
            'checklist_item_id' => $concern->checklistItems()->first()->id,
        ])->assertRedirect();

        foreach (['proof-a.png', 'proof-b.png'] as $name) {
            $this->actingAs($admin)->post(route('admin.kaizen-concerns.evidence.add', $concern), [
                'evidence' => UploadedFile::fake()->create($name, 30, 'image/png'),
            ])->assertRedirect();
        }

        $this->actingAs($admin)->post(route('admin.kaizen-concerns.implement', $concern))->assertRedirect();

        $this->actingAs($admin)->get(route('admin.kaizen-concerns.show', $concern))
            ->assertOk()
            ->assertSee('Improvement Implemented')
            ->assertSee('This employee suggestion has been successfully implemented.')
            ->assertSee('Implemented on')
            ->assertSee('2 files');

        // The summary only appears once both conditions hold, so an
        // implemented suggestion with an open checklist does not claim
        // everything is complete.
        $concern->checklistItems()->first()->update(['completed' => false]);

        $this->actingAs($admin)->get(route('admin.kaizen-concerns.show', $concern))
            ->assertOk()
            ->assertDontSee('Improvement Implemented');
    }

    public function test_the_evidence_section_keeps_its_add_action_and_empty_state_for_viewers(): void
    {
        $staff = $this->user(User::ROLE_STAFF);
        $concern = $this->concern($this->user(User::ROLE_ADMIN), KaizenConcern::STATUS_PENDING, $staff);

        $this->actingAs($staff)->get(route('admin.kaizen-concerns.show', $concern))
            ->assertOk()
            ->assertSee('Implementation Evidence')
            ->assertSee('Attach proof that this improvement was implemented.')
            ->assertSee('Add Evidence')
            ->assertSee('No implementation evidence attached yet.')
            ->assertSee('photo, screenshot, PDF or document');
    }

    /* ---------------------------------------------------------------
     | 7. Admin Concerns and Employee Suggestions are kept apart
     * -------------------------------------------------------------- */

    /**
     * Creates a record through the real admin endpoint, so the test exercises
     * the actual Admin Concern create path rather than hand-rolling a row.
     */
    private function createAdminConcern(User $admin, string $challenge = 'Broken fan in the staff room'): KaizenConcern
    {
        $this->actingAs($admin)->post(route('admin.kaizen-concerns.store'), [
            'date_identified' => '2026-10-01',
            'challenge' => $challenge,
            'recommended_solution' => 'Replace the broken fan in the staff room.',
            'status' => KaizenConcern::STATUS_IN_PROGRESS,
        ])->assertRedirect(route('admin.kaizen-concerns.index'));

        return KaizenConcern::query()->latest('id')->firstOrFail();
    }

    public function test_an_admin_concern_is_stored_as_an_admin_concern(): void
    {
        $adminConcern = $this->createAdminConcern($this->user(User::ROLE_ADMIN));

        $this->assertSame(KaizenConcern::TYPE_ADMIN_CONCERN, $adminConcern->type);
        $this->assertTrue($adminConcern->isAdminConcern());
        $this->assertFalse($adminConcern->isEmployeeSuggestion());
    }

    public function test_the_improvement_board_shows_employee_suggestions_and_not_admin_concerns(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);

        $this->createAdminConcern($admin);

        $suggestion = $this->concern($admin, KaizenConcern::STATUS_PENDING);
        $suggestion->update(['challenge' => 'Client documents arrive without a checklist.']);

        $board = $this->actingAs($admin)->get(route('admin.kaizen-concerns.index'))->assertOk();

        // The employee suggestion is listed...
        $board->assertSee('Client documents arrive without a checklist.');

        // ...and the Admin Concern is not, even though it lives in the same table.
        $board->assertDontSee('Broken fan in the staff room');
    }

    public function test_an_admin_concern_is_still_reachable_and_labelled_correctly(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $adminConcern = $this->createAdminConcern($admin);

        // Excluding it from the board must not break its own detail or edit page.
        $this->actingAs($admin)->get(route('admin.kaizen-concerns.show', $adminConcern))
            ->assertOk()
            ->assertSee('Admin Concern')
            ->assertSee('Broken fan in the staff room');

        $this->actingAs($admin)->get(route('admin.kaizen-concerns.edit', $adminConcern))->assertOk();

        // And the pre-existing create page is untouched.
        $this->actingAs($admin)->get(route('admin.kaizen-concerns.create'))
            ->assertOk()
            ->assertSee('Create Kaizen Concern');
    }

    public function test_an_admin_concern_still_requires_an_admin_to_create_or_edit(): void
    {
        $staff = $this->user(User::ROLE_STAFF);
        $adminConcern = $this->createAdminConcern($this->user(User::ROLE_ADMIN));

        $payload = [
            'date_identified' => '2026-10-01',
            'challenge' => 'Staff raised a concern',
            'recommended_solution' => 'Something.',
            'status' => KaizenConcern::STATUS_IN_PROGRESS,
        ];

        $this->actingAs($staff)->post(route('admin.kaizen-concerns.store'), $payload)->assertForbidden();
        $this->actingAs($staff)->get(route('admin.kaizen-concerns.edit', $adminConcern))->assertForbidden();
    }

    public function test_a_row_from_before_the_type_column_existed_still_counts_as_a_suggestion(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);

        // The migration backfills these, but the board also tolerates a null
        // type so an older database never shows an unexpectedly empty list.
        $legacy = KaizenConcern::create([
            'type' => null,
            'date_identified' => now()->subDays(2),
            'challenge' => 'Legacy suggestion with no type',
            'recommended_solution' => 'Still shown on the board.',
            'status' => KaizenConcern::STATUS_PENDING,
            'created_by' => $admin->id,
        ]);

        $this->assertNull($legacy->type);

        $this->actingAs($admin)->get(route('admin.kaizen-concerns.index'))
            ->assertOk()
            ->assertSee('Legacy suggestion with no type');
    }

    /* ---------------------------------------------------------------
     | 8. The displayed status follows the implementation state
     * -------------------------------------------------------------- */

    public function test_the_displayed_status_follows_the_implementation_state(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);

        $pending = $this->concern($admin, KaizenConcern::STATUS_PENDING);
        $this->assertSame(KaizenConcern::STATUS_PENDING, $pending->effectiveStatus());
        $this->assertSame('Pending', $pending->statusLabel());

        $inProgress = $this->concern($admin, KaizenConcern::STATUS_IN_PROGRESS);
        $this->assertSame(KaizenConcern::STATUS_IN_PROGRESS, $inProgress->effectiveStatus());

        $overdue = $this->concern($admin, KaizenConcern::STATUS_OVERDUE);
        $this->assertSame(KaizenConcern::STATUS_OVERDUE, $overdue->effectiveStatus());

        // An implementation date is the proof that the work actually happened,
        // so on its own it moves the suggestion to Implemented for display.
        $implemented = $this->concern($admin, KaizenConcern::STATUS_PENDING);
        $implemented->update(['implementation_date' => '2026-10-05']);
        $implemented = $implemented->fresh();

        $this->assertSame(KaizenConcern::STATUS_IMPLEMENTED, $implemented->effectiveStatus());
        $this->assertSame('Implemented', $implemented->statusLabel());
        $this->assertTrue($implemented->isImplemented());

        // A closed "not implemented" outcome is preserved, and it wins over the
        // implementation date so the record cannot flip back to Implemented.
        $rejected = $this->concern($admin, KaizenConcern::STATUS_NOT_IMPLEMENTED);
        $rejected->update(['implementation_date' => '2026-10-05']);
        $rejected = $rejected->fresh();

        $this->assertSame(KaizenConcern::STATUS_NOT_IMPLEMENTED, $rejected->effectiveStatus());
        $this->assertSame('Not Implemented', $rejected->statusLabel());
        $this->assertFalse($rejected->isImplemented());
    }

    public function test_a_submitted_suggestion_reads_as_pending_without_any_manual_status(): void
    {
        $staff = $this->user(User::ROLE_STAFF);

        $this->actingAs($staff)->post(route('admin.kaizen-concerns.submit.store'), [
            'date_identified' => '2026-10-05',
            'challenge' => 'Fridge in the pantry is noisy',
            'recommended_solution' => 'Replace the fridge seal.',
        ])->assertRedirect();

        $concern = KaizenConcern::query()->latest('id')->firstOrFail();

        $this->assertSame(KaizenConcern::STATUS_PENDING, $concern->status);
        $this->assertSame(KaizenConcern::STATUS_PENDING, $concern->effectiveStatus());
        $this->assertFalse($concern->isImplemented());
        $this->assertNull($concern->implementation_date);

        $this->actingAs($staff)->get(route('admin.kaizen-concerns.show', $concern))
            ->assertOk()
            ->assertSee('Pending');
    }

    public function test_confirming_implementation_moves_the_suggestion_to_implemented_on_its_own(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $concern = $this->concern($admin, KaizenConcern::STATUS_IN_PROGRESS);

        $this->actingAs($admin)->post(route('admin.kaizen-concerns.implement', $concern))->assertRedirect();

        $concern = $concern->fresh();

        $this->assertSame(KaizenConcern::STATUS_IMPLEMENTED, $concern->status);
        $this->assertSame(now()->format('Y-m-d'), $concern->implementation_date?->format('Y-m-d'));
        $this->assertSame('Implemented', $concern->statusLabel());

        // The list and the detail screen agree with the confirmed state.
        $this->actingAs($admin)->get(route('admin.kaizen-concerns.index'))
            ->assertOk()
            ->assertSee('Implemented');

        $this->actingAs($admin)->get(route('admin.kaizen-concerns.show', $concern))
            ->assertOk()
            ->assertSee('Implemented on:');
    }

    public function test_the_status_does_not_have_to_be_chosen_by_hand(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $concern = $this->concern($admin, KaizenConcern::STATUS_IN_PROGRESS);

        // Saving the record without a status leaves it exactly where the
        // implementation workflow already put it.
        $this->actingAs($admin)->put(route('admin.kaizen-concerns.update', $concern), [
            'date_identified' => $concern->date_identified->format('Y-m-d'),
            'challenge' => $concern->challenge,
            'recommended_solution' => $concern->recommended_solution,
        ])->assertRedirect();

        $concern = $concern->fresh();

        $this->assertSame(KaizenConcern::STATUS_IN_PROGRESS, $concern->status);
        $this->assertSame('In Progress', $concern->statusLabel());
        $this->assertNull($concern->implementation_date);
    }

    public function test_the_edit_form_does_not_demand_a_status_choice(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $concern = $this->concern($admin, KaizenConcern::STATUS_IN_PROGRESS);

        $content = $this->actingAs($admin)->get(route('admin.kaizen-concerns.edit', $concern))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString(
            'name="status" required',
            $content,
            'The status must not be a required field.'
        );

        // The suggestion's real state is what is preselected.
        $this->assertMatchesRegularExpression(
            '/<option value="'.preg_quote(KaizenConcern::STATUS_IN_PROGRESS, '/').'"\s+selected/',
            $content
        );
    }

    /* ---------------------------------------------------------------
     | 9. Evidence stays with the suggestion it was attached to
     * -------------------------------------------------------------- */

    public function test_the_board_counts_the_evidence_on_each_suggestion(): void
    {
        Storage::fake('local');

        $admin = $this->user(User::ROLE_ADMIN);

        $withEvidence = $this->concern($admin, KaizenConcern::STATUS_IMPLEMENTED);
        $withEvidence->update([
            'challenge' => 'Client documents arrive without a checklist.',
            'implementation_date' => '2026-10-05',
        ]);

        $withoutEvidence = $this->concern($admin, KaizenConcern::STATUS_PENDING);
        $withoutEvidence->update(['challenge' => 'Broken fan in the staff room.']);

        $this->actingAs($admin)->post(route('admin.kaizen-concerns.evidence.add', $withEvidence), [
            'evidence' => UploadedFile::fake()->create('checklist-proof.png', 40, 'image/png'),
        ])->assertRedirect();

        $this->assertSame(1, $withEvidence->evidences()->count());
        $this->assertSame(0, $withoutEvidence->evidences()->count());

        $content = $this->actingAs($admin)->get(route('admin.kaizen-concerns.index'))
            ->assertOk()
            ->getContent();

        $withRow = $this->boardRow($content, 'Client documents arrive without a checklist.');
        $withoutRow = $this->boardRow($content, 'Broken fan in the staff room.');

        $this->assertStringContainsString('View Evidence', $withRow);
        $this->assertStringContainsString('1 file attached', $withRow);

        // The count belongs to the row that owns the file, not to its neighbour.
        $this->assertStringContainsString('No evidence', $withoutRow);
        $this->assertStringNotContainsString('View Evidence', $withoutRow);
    }

    /**
     * The single board row containing $needle, so an assertion about one
     * suggestion's evidence cannot be satisfied by a neighbouring row.
     */
    private function boardRow(string $html, string $needle): string
    {
        foreach (explode('<tr', $this->boardTable($html)) as $row) {
            if (str_contains($row, $needle)) {
                return $row;
            }
        }

        $this->fail("No board row containing \"{$needle}\".");
    }

    /* ---------------------------------------------------------------
     | 10. The dedicated Admin Concerns board
     | -------------------------------------------------------------- */

    public function test_the_improvement_board_lists_employee_suggestions_only(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);

        // One of each, created through the real endpoints so the types are real.
        $adminConcern = $this->createAdminConcern($admin, 'Admin raised: photocopier jams');

        $this->actingAs($admin)->post(route('admin.kaizen-concerns.submit.store'), [
            'date_identified' => '2026-10-02',
            'challenge' => 'Staff raised: the front door sticks',
            'recommended_solution' => 'Oil the hinges monthly.',
        ])->assertRedirect();

        $suggestion = KaizenConcern::query()
            ->where('type', KaizenConcern::TYPE_EMPLOYEE_SUGGESTION)
            ->latest('id')
            ->firstOrFail();

        // (1) + (2): each list shows its own type and only its own type.
        $this->actingAs($admin)->get(route('admin.kaizen-concerns.index'))
            ->assertOk()
            ->assertSee('Staff raised: the front door sticks')
            ->assertDontSee('Admin raised: photocopier jams');

        // (3): the Admin Concerns board shows the admin concern, not the suggestion.
        $this->actingAs($admin)->get(route('admin.kaizen-concerns.admin-concerns.index'))
            ->assertOk()
            ->assertSee('Admin raised: photocopier jams')
            ->assertDontSee('Staff raised: the front door sticks');

        $this->assertTrue($adminConcern->isAdminConcern());
        $this->assertTrue($suggestion->isEmployeeSuggestion());
    }

    public function test_the_admin_concerns_board_has_its_own_columns(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $this->createAdminConcern($admin);

        $table = $this->boardTable(
            $this->actingAs($admin)->get(route('admin.kaizen-concerns.admin-concerns.index'))
                ->assertOk()
                ->getContent()
        );

        foreach (['Admin Concern', 'Submitted By', 'Assigned To', 'Status', 'Target Date', 'Implementation', 'Evidence', 'Actions'] as $heading) {
            $this->assertStringContainsString('>'.$heading.'<', $table, "The Admin Concerns board must keep the \"{$heading}\" column.");
        }
    }

    public function test_the_admin_concerns_board_shows_the_assignment_and_reuses_the_workflow(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $staff = $this->user(User::ROLE_STAFF, 'Angeli');

        $this->actingAs($admin)->post(route('admin.kaizen-concerns.store'), [
            'date_identified' => '2026-10-01',
            'challenge' => 'Reconcile the client receipts folder',
            'recommended_solution' => 'Rebuild the folder structure.',
            'status' => KaizenConcern::STATUS_IN_PROGRESS,
            'assigned_staff_id' => $staff->id,
        ])->assertRedirect();

        $concern = KaizenConcern::query()->latest('id')->firstOrFail();

        $row = $this->boardRow(
            $this->actingAs($admin)->get(route('admin.kaizen-concerns.admin-concerns.index'))
                ->assertOk()
                ->getContent(),
            'Reconcile the client receipts folder'
        );

        // Assignment, status, target date and evidence all read the same shared
        // relations they always did.
        $this->assertStringContainsString($staff->name, $row);
        $this->assertStringContainsString('In Progress', $row);
        $this->assertStringContainsString('No evidence', $row);
        $this->assertStringNotContainsString('Unassigned', $row);

        // The existing implementation workflow still applies to an Admin Concern.
        $this->actingAs($admin)->post(route('admin.kaizen-concerns.implement', $concern))->assertRedirect();

        $concern = $concern->fresh();
        $this->assertSame(KaizenConcern::STATUS_IMPLEMENTED, $concern->status);
        $this->assertSame(now()->format('Y-m-d'), $concern->implementation_date?->format('Y-m-d'));

        $this->actingAs($admin)->get(route('admin.kaizen-concerns.admin-concerns.index'))
            ->assertOk()
            ->assertSee('kaizen-row-implemented', false);
    }

    public function test_admin_concerns_are_never_counted_on_the_improvement_suggestions_board(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);

        $this->createAdminConcern($admin, 'Admin raised: aircon leaking');
        $this->createAdminConcern($admin, 'Admin raised: Wi-Fi drops in the back office');

        $content = $this->actingAs($admin)->get(route('admin.kaizen-concerns.index'))->assertOk()->getContent();

        $this->assertStringNotContainsString('Admin raised: aircon leaking', $content);
        $this->assertStringNotContainsString('Admin raised: Wi-Fi drops in the back office', $content);

        // The Improvement Suggestions heading counts suggestions only.
        $this->assertStringContainsString('0 suggestions', $content);

        $adminBoard = $this->actingAs($admin)->get(route('admin.kaizen-concerns.admin-concerns.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('2 concerns', $adminBoard);
    }

    public function test_both_boards_survive_side_by_side_without_moving_or_duplicating_records(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);

        $adminConcern = $this->createAdminConcern($admin, 'Admin raised: shredder is jammed');
        $suggestion = $this->concern($admin, KaizenConcern::STATUS_PENDING);
        $suggestion->update(['challenge' => 'Desk drawer will not open']);

        $before = KaizenConcern::withTrashed()->count();

        $this->actingAs($admin)->get(route('admin.kaizen-concerns.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.kaizen-concerns.admin-concerns.index'))->assertOk();

        // (4) + (5): viewing either board mutates nothing.
        $this->assertSame($before, KaizenConcern::withTrashed()->count());
        $this->assertSame(KaizenConcern::TYPE_ADMIN_CONCERN, $adminConcern->fresh()->type);
        $this->assertSame(KaizenConcern::TYPE_EMPLOYEE_SUGGESTION, $suggestion->fresh()->type);

        // Neither record moved table: both are still the same kaizen rows.
        $this->assertDatabaseHas('kaizen_concerns', [
            'id' => $adminConcern->id,
            'type' => KaizenConcern::TYPE_ADMIN_CONCERN,
        ]);
        $this->assertDatabaseHas('kaizen_concerns', [
            'id' => $suggestion->id,
            'type' => KaizenConcern::TYPE_EMPLOYEE_SUGGESTION,
        ]);
    }

    public function test_the_admin_concerns_board_keeps_evidence_with_the_concern_that_owns_it(): void
    {
        Storage::fake('local');

        $admin = $this->user(User::ROLE_ADMIN);

        $withEvidence = $this->createAdminConcern($admin, 'Admin raised: new laptops not delivered');
        $withoutEvidence = $this->createAdminConcern($admin, 'Admin raised: whiteboard is missing');

        $this->actingAs($admin)->post(route('admin.kaizen-concerns.evidence.add', $withEvidence), [
            'evidence' => UploadedFile::fake()->create('delivery-note.png', 40, 'image/png'),
        ])->assertRedirect();

        $this->assertSame(1, $withEvidence->evidences()->count());
        $this->assertSame(0, $withoutEvidence->evidences()->count());

        $content = $this->actingAs($admin)->get(route('admin.kaizen-concerns.admin-concerns.index'))
            ->assertOk()
            ->getContent();

        $withRow = $this->boardRow($content, 'Admin raised: new laptops not delivered');
        $withoutRow = $this->boardRow($content, 'Admin raised: whiteboard is missing');

        $this->assertStringContainsString('View Evidence', $withRow);
        $this->assertStringContainsString('1 file attached', $withRow);
        $this->assertStringContainsString('No evidence', $withoutRow);
    }

    public function test_a_legacy_record_with_no_type_is_never_treated_as_an_admin_concern(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);

        // Rows from before the type column existed are backfilled, but the
        // application must never assume one way or the other for a null type.
        $legacy = KaizenConcern::create([
            'type' => null,
            'date_identified' => now()->subDays(4),
            'challenge' => 'Legacy record with no type',
            'recommended_solution' => 'Still listed as a suggestion.',
            'status' => KaizenConcern::STATUS_PENDING,
            'created_by' => $admin->id,
        ]);

        $this->assertNull($legacy->fresh()->type);

        // It stays on the suggestion side and never appears as an Admin Concern.
        $this->actingAs($admin)->get(route('admin.kaizen-concerns.index'))
            ->assertOk()
            ->assertSee('Legacy record with no type');

        $this->actingAs($admin)->get(route('admin.kaizen-concerns.admin-concerns.index'))
            ->assertOk()
            ->assertDontSee('Legacy record with no type');

        // And it was not converted behind our back.
        $this->assertNull($legacy->fresh()->type);
    }

    public function test_only_admins_may_open_the_admin_concerns_board(): void
    {
        foreach ([User::ROLE_STAFF, User::ROLE_SUPERVISOR, User::ROLE_CLIENT] as $role) {
            $this->actingAs($this->user($role))
                ->get(route('admin.kaizen-concerns.admin-concerns.index'))
                ->assertForbidden();
        }

        $this->actingAs($this->user(User::ROLE_ADMIN))
            ->get(route('admin.kaizen-concerns.admin-concerns.index'))
            ->assertOk();
    }

    public function test_the_two_boards_keep_their_own_filters(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);

        $this->createAdminConcern($admin, 'Admin raised: printer out of toner');

        $this->actingAs($admin)->get(route('admin.kaizen-concerns.index', ['q' => 'toner']))
            ->assertOk()
            ->assertDontSee('Admin raised: printer out of toner');

        $this->actingAs($admin)->get(route('admin.kaizen-concerns.admin-concerns.index', ['q' => 'toner']))
            ->assertOk()
            ->assertSee('Admin raised: printer out of toner');

        // Each board's filter form and Clear link point back at itself.
        $content = $this->actingAs($admin)->get(route('admin.kaizen-concerns.admin-concerns.index', ['q' => 'toner']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(
            route('admin.kaizen-concerns.admin-concerns.index'),
            $content
        );
    }

    public function test_the_admin_concerns_board_is_linked_from_the_navigation_for_admins_only(): void
    {
        $adminNav = $this->actingAs($this->user(User::ROLE_ADMIN))
            ->get(route('admin.kaizen-concerns.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(
            route('admin.kaizen-concerns.admin-concerns.index'),
            $adminNav
        );

        $staffNav = $this->actingAs($this->user(User::ROLE_STAFF))
            ->get(route('admin.kaizen-concerns.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString(
            route('admin.kaizen-concerns.admin-concerns.index'),
            $staffNav
        );
    }

    public function test_the_admin_concerns_route_is_not_shadowed_by_the_concern_id_route(): void
    {
        // The literal segment has to win over /kaizen-concerns/{concern}.
        $this->actingAs($this->user(User::ROLE_ADMIN))
            ->get(route('admin.kaizen-concerns.admin-concerns.index'))
            ->assertOk()
            ->assertSee('Admin Concerns');
    }

    /* ---------------------------------------------------------------
     | 11. The compact Create Concern modal (UI only)
     | -------------------------------------------------------------- */

    public function test_the_create_concern_modal_opens_from_the_admin_concerns_board(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);

        $content = $this->actingAs($admin)
            ->get(route('admin.kaizen-concerns.admin-concerns.index'))
            ->assertOk()
            ->getContent();

        // The board opens the modal in place rather than navigating away.
        $this->assertStringContainsString('data-bs-target="#createConcernModal"', $content);
        $this->assertStringContainsString('id="createConcernModal"', $content);

        // Posting to the same endpoint as the full-page form.
        $this->assertStringContainsString(route('admin.kaizen-concerns.store'), $content);

        // Scoped internal scroll, not a page-level scroll.
        $this->assertStringContainsString('concern-modal', $content);
    }

    /**
     * The Create Concern modal's own <form> block.
     *
     * Scoped because the board's filter form also posts a `status` field, so
     * asserting against the whole page could match the filter instead of the
     * modal's Status select.
     */
    private function createConcernModalForm(string $html): string
    {
        $this->assertSame(
            1,
            preg_match('/<form[^>]*concern-modal-form.*?<\/form>/s', $html, $m),
            'The Create Concern modal form must be present exactly once.'
        );

        return $m[0];
    }

    public function test_the_create_concern_modal_keeps_every_required_field(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);

        $page = $this->actingAs($admin)
            ->get(route('admin.kaizen-concerns.admin-concerns.index'))
            ->assertOk()
            ->getContent();

        $html = $this->createConcernModalForm($page);

        // Every field store() validates must still be in the form, with the
        // same tag, the same name, the same input type and the same required
        // flag. tag => input type (null when the tag is the type).
        $required = [
            'challenge' => ['textarea', null],
            'recommended_solution' => ['textarea', null],
            'date_identified' => ['input', 'date'],
            'status' => ['select', null],
        ];

        foreach ($required as $name => [$tag, $type]) {
            $pattern = '/<'.$tag.'\b[^>]*\bname="'.$name.'"[^>]*>/i';
            $this->assertSame(1, preg_match($pattern, $html, $m), "The \"{$name}\" {$tag} must be present in the modal.");

            // Checked on the captured tag so attribute order cannot matter.
            $tagMarkup = $m[0];
            $this->assertStringContainsString('required', $tagMarkup, "The \"{$name}\" {$tag} must still be required.");

            if ($type !== null) {
                $this->assertStringContainsString('type="'.$type.'"', $tagMarkup, "The \"{$name}\" input must still be type=\"{$type}\".");
            }
        }

        // Optional fields must survive too, still optional.
        $optional = [
            'notes' => ['textarea', null],
            'target_date' => ['input', 'date'],
            'assigned_staff_id' => ['select', null],
        ];

        foreach ($optional as $name => [$tag, $type]) {
            $pattern = '/<'.$tag.'\b[^>]*\bname="'.$name.'"[^>]*>/i';
            $this->assertSame(1, preg_match($pattern, $html, $m), "The \"{$name}\" {$tag} must be present in the modal.");
            $this->assertStringNotContainsString('required', $m[0], "The \"{$name}\" {$tag} must stay optional.");
        }

        // Maxlength limits unchanged.
        $this->assertStringContainsString('name="challenge" rows="3" maxlength="5000"', $html);
        $this->assertStringContainsString('name="recommended_solution" rows="3" maxlength="5000"', $html);
        $this->assertStringContainsString('name="notes" rows="2" maxlength="2000"', $html);

        // The CSRF token, the endpoint and both actions are present.
        $this->assertStringContainsString('name="_token"', $html);
        $this->assertStringContainsString(route('admin.kaizen-concerns.store'), $html);
        $this->assertStringContainsString('Create Concern', $html);
        $this->assertStringContainsString('Cancel', $html);
    }

    public function test_the_create_concern_modal_still_creates_an_admin_concern(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);

        $this->actingAs($admin)->post(route('admin.kaizen-concerns.store'), [
            'date_identified' => '2026-10-03',
            'challenge' => 'Modal raised: the scanner is offline',
            'recommended_solution' => 'Replace the cable and re-pair.',
            'status' => KaizenConcern::STATUS_PENDING,
        ])->assertRedirect(route('admin.kaizen-concerns.index'));

        $concern = KaizenConcern::query()->latest('id')->firstOrFail();

        $this->assertTrue($concern->isAdminConcern());
        $this->assertSame('Modal raised: the scanner is offline', $concern->challenge);

        // And it shows up on the Admin Concerns board, not the suggestion board.
        $this->actingAs($admin)->get(route('admin.kaizen-concerns.admin-concerns.index'))
            ->assertOk()
            ->assertSee('Modal raised: the scanner is offline');
    }

    public function test_a_validation_failure_reopens_the_modal_and_keeps_the_typed_values(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);

        $this->actingAs($admin)
            ->from(route('admin.kaizen-concerns.admin-concerns.index'))
            ->post(route('admin.kaizen-concerns.store'), [
                'date_identified' => '2026-10-03',
                'challenge' => '',
                'recommended_solution' => 'Something.',
                'status' => KaizenConcern::STATUS_PENDING,
            ])
            ->assertRedirect(route('admin.kaizen-concerns.admin-concerns.index'))
            ->assertSessionHasErrors('challenge');

        $this->assertSame(0, KaizenConcern::query()->count());

        // Returning to the board reopens the modal and repopulates what was typed.
        $content = $this->actingAs($admin)
            ->get(route('admin.kaizen-concerns.admin-concerns.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('bootstrap.Modal.getOrCreateInstance', $content);
        $this->assertStringContainsString('Something.', $content);
    }

    public function test_the_create_concern_modal_is_admin_only_and_the_page_form_still_works(): void
    {
        // No staff member can reach the create page or post a concern.
        $this->actingAs($this->user(User::ROLE_STAFF))
            ->get(route('admin.kaizen-concerns.create'))
            ->assertForbidden();

        $admin = $this->user(User::ROLE_ADMIN);

        // The full-page fallback keeps every field too.
        $this->actingAs($admin)->get(route('admin.kaizen-concerns.create'))
            ->assertOk()
            ->assertSee('Create Kaizen Concern')
            ->assertSee('name="challenge"', false)
            ->assertSee('name="recommended_solution"', false)
            ->assertSee('name="assigned_staff_id"', false)
            ->assertSee('name="target_date"', false)
            ->assertSee('name="date_identified"', false)
            ->assertSee('name="status"', false)
            ->assertSee('name="notes"', false);
    }

    public function test_employee_suggestion_creation_is_unaffected_by_the_modal(): void
    {
        $staff = $this->user(User::ROLE_STAFF);

        $this->actingAs($staff)->post(route('admin.kaizen-concerns.submit.store'), [
            'date_identified' => '2026-10-04',
            'challenge' => 'Staff raised: the kettle leaks',
            'recommended_solution' => 'Replace the seal.',
        ])->assertRedirect();

        $suggestion = KaizenConcern::query()->latest('id')->firstOrFail();

        $this->assertTrue($suggestion->isEmployeeSuggestion());
        $this->assertSame(KaizenConcern::STATUS_PENDING, $suggestion->status);

        // The employee form is unchanged and the modal did not leak onto it.
        $this->actingAs($staff)->get(route('admin.kaizen-concerns.submit'))
            ->assertOk()
            ->assertDontSee('id="createConcernModal"', false);

        // Only the Improvement Suggestions board lists it.
        $this->actingAs($staff)->get(route('admin.kaizen-concerns.index'))
            ->assertOk()
            ->assertSee('Staff raised: the kettle leaks');
    }
}
