<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminConfidentialityAcknowledged;
use App\Models\Notification;
use App\Models\TechnicalIssue;
use App\Models\TechnicalIssueAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class TechnicalIssueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('supabase');
    }

    private function admin(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'confidentiality_acknowledged_at' => now(),
            'confidentiality_ack_version' => EnsureAdminConfidentialityAcknowledged::CURRENT_VERSION,
        ]);
    }

    private function supervisor(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_SUPERVISOR,
            'confidentiality_acknowledged_at' => now(),
            'confidentiality_ack_version' => EnsureAdminConfidentialityAcknowledged::CURRENT_VERSION,
        ]);
    }

    private function staff(string $name = 'Staff Member'): User
    {
        return User::factory()->create([
            'role' => User::ROLE_STAFF,
            'name' => $name,
            'confidentiality_acknowledged_at' => now(),
            'confidentiality_ack_version' => EnsureAdminConfidentialityAcknowledged::CURRENT_VERSION,
        ]);
    }

    private function client(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_CLIENT,
            'name' => 'Test Client',
            'business_name' => 'Test Client Business',
            'approved_at' => now(),
        ]);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Billing totals show the wrong amount',
            'category' => TechnicalIssue::CATEGORY_BILLING,
            'description' => 'When I open a billing statement the totals column is off by a few pesos.',
            'page_url' => '/daily-journal',
        ], $overrides);
    }

    /** @test */
    public function staff_can_submit_a_technical_issue()
    {
        $staff = $this->staff('Maria Santos');

        $this->actingAs($staff)
            ->post(route('admin.technical-issues.store'), $this->validPayload())
            ->assertRedirect();

        $this->assertDatabaseCount('technical_issues', 1);

        $issue = TechnicalIssue::first();
        $this->assertSame('ISS-00001', $issue->reference);
        $this->assertSame($staff->id, $issue->user_id);
        $this->assertSame(User::ROLE_STAFF, $issue->user_role);
        $this->assertSame(TechnicalIssue::STATUS_OPEN, $issue->status);
        $this->assertSame(TechnicalIssue::PRIORITY_MEDIUM, $issue->priority);
        $this->assertSame('/daily-journal', $issue->page_url);
    }

    /** @test */
    public function issue_references_are_sequential()
    {
        $staff = $this->staff();

        TechnicalIssue::factory()->create([
            'user_id' => $staff->id,
            'user_role' => User::ROLE_STAFF,
            'title' => 'Reference seed',
            'category' => TechnicalIssue::CATEGORY_OTHER,
            'description' => 'Seed record',
        ]);

        $this->actingAs($staff)
            ->post(route('admin.technical-issues.store'), $this->validPayload(['title' => 'Second report']))
            ->assertRedirect();

        $this->assertSame('ISS-00002', TechnicalIssue::latest('id')->first()->reference);
    }

    /** @test */
    public function privileged_fields_cannot_be_forced_through_mass_assignment()
    {
        $staff = $this->staff();
        $admin = $this->admin();

        $this->actingAs($staff)
            ->post(route('admin.technical-issues.store'), $this->validPayload([
                'status' => TechnicalIssue::STATUS_CLOSED,
                'updated_by' => $admin->id,
                'user_id' => $admin->id,
            ]))
            ->assertRedirect();

        $issue = TechnicalIssue::first();
        $this->assertSame($staff->id, $issue->user_id);
        $this->assertNull($issue->updated_by);
        $this->assertSame(TechnicalIssue::STATUS_OPEN, $issue->status);
    }

    /** @test */
    public function required_fields_are_validated()
    {
        $staff = $this->staff();

        $this->actingAs($staff)
            ->post(route('admin.technical-issues.store'), [])
            ->assertSessionHasErrors(['title', 'category', 'description']);

        $this->assertDatabaseCount('technical_issues', 0);
    }

    /** @test */
    public function invalid_category_and_priority_are_rejected()
    {
        $staff = $this->staff();

        $this->actingAs($staff)
            ->post(route('admin.technical-issues.store'), $this->validPayload([
                'category' => 'not_a_category',
                'priority' => 'critical',
            ]))
            ->assertSessionHasErrors(['category', 'priority']);

        $this->assertDatabaseCount('technical_issues', 0);
    }

    /** @test */
    public function offsite_page_url_is_scrubbed()
    {
        $staff = $this->staff();

        $this->actingAs($staff)
            ->post(route('admin.technical-issues.store'), $this->validPayload([
                'page_url' => 'https://evil.example.com/phishing',
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('technical_issues', [
            'id' => TechnicalIssue::first()->id,
            'page_url' => null,
        ]);
    }

    /** @test */
    public function screenshots_are_uploaded_and_recorded()
    {
        $staff = $this->staff();

        $response = $this->actingAs($staff)
            ->post(route('admin.technical-issues.store'), $this->validPayload([
                'screenshots' => [
                    UploadedFile::fake()->image('receipt.png', 32, 32),
                    UploadedFile::fake()->image('totals.webp', 32, 32),
                ],
            ]))
            ->assertRedirect();

        $issue = TechnicalIssue::first();
        $this->assertSame(2, $issue->attachments()->count());

        foreach ($issue->attachments as $attachment) {
            Storage::disk('local')->assertExists($attachment->path);
            $this->assertNotNull($attachment->mime_type);
            $this->assertSame($staff->id, $attachment->uploaded_by);
        }

        $this->assertFalse($issue->attachments->contains(fn ($a) => ! $a->isImage()));
    }

    /** @test */
    public function executable_and_non_image_files_are_rejected()
    {
        $staff = $this->staff();

        $this->actingAs($staff)
            ->post(route('admin.technical-issues.store'), $this->validPayload([
                'screenshots' => [
                    UploadedFile::fake()->create('shell.php', 10),
                ],
            ]))
            ->assertSessionHasErrors('screenshots.0');

        $this->actingAs($staff)
            ->post(route('admin.technical-issues.store'), $this->validPayload([
                'screenshots' => [
                    UploadedFile::fake()->create('notes.txt', 10),
                ],
            ]))
            ->assertSessionHasErrors('screenshots.0');

        $this->assertDatabaseCount('technical_issue_attachments', 0);
    }

    /** @test */
    public function reporter_can_view_their_own_report()
    {
        $staff = $this->staff();
        $issue = TechnicalIssue::factory()->create(['user_id' => $staff->id, 'user_role' => User::ROLE_STAFF]);

        $this->actingAs($staff)
            ->get(route('admin.technical-issues.show', $issue))
            ->assertOk()
            ->assertSee($issue->title);
    }

    /** @test */
    public function staff_cannot_view_another_staffs_report()
    {
        $reporter = $this->staff('Reporter');
        $other = $this->staff('Other Person');
        $issue = TechnicalIssue::factory()->create(['user_id' => $reporter->id, 'user_role' => User::ROLE_STAFF]);

        $this->actingAs($other)
            ->get(route('admin.technical-issues.show', $issue))
            ->assertForbidden();
    }

    /** @test */
    public function staff_index_only_lists_their_own_reports()
    {
        $reporter = $this->staff('Reporter');
        $other = $this->staff('Other Person');

        $mine = TechnicalIssue::factory()->create(['user_id' => $reporter->id, 'user_role' => User::ROLE_STAFF, 'title' => 'My private issue']);
        TechnicalIssue::factory()->create(['user_id' => $other->id, 'user_role' => User::ROLE_STAFF, 'title' => 'Someone elses issue']);

        $this->actingAs($reporter)
            ->get(route('admin.technical-issues.index'))
            ->assertOk()
            ->assertSee($mine->title)
            ->assertDontSee('Someone elses issue');
    }

    /** @test */
    public function admin_and_supervisor_can_view_any_report()
    {
        $staff = $this->staff();
        $issue = TechnicalIssue::factory()->create(['user_id' => $staff->id, 'user_role' => User::ROLE_STAFF]);

        $this->actingAs($this->admin())->get(route('admin.technical-issues.show', $issue))->assertOk();
        $this->actingAs($this->supervisor())->get(route('admin.technical-issues.show', $issue))->assertOk();

        $this->actingAs($this->admin())
            ->get(route('admin.technical-issues.index'))
            ->assertOk()
            ->assertSee($issue->title);
    }

    /** @test */
    public function staff_cannot_manage_reports()
    {
        $staff = $this->staff();
        $issue = TechnicalIssue::factory()->create(['user_id' => $staff->id, 'user_role' => User::ROLE_STAFF]);

        $this->actingAs($staff)
            ->patch(route('admin.technical-issues.update', $issue), [
                'status' => TechnicalIssue::STATUS_RESOLVED,
                'priority' => TechnicalIssue::PRIORITY_URGENT,
            ])
            ->assertForbidden();

        $this->actingAs($staff)
            ->post(route('admin.technical-issues.notes.store', $issue), ['body' => 'Closed from the client side.'])
            ->assertForbidden();

        $this->assertDatabaseHas('technical_issues', ['id' => $issue->id, 'status' => TechnicalIssue::STATUS_OPEN]);
        $this->assertDatabaseCount('technical_issue_notes', 0);
    }

    /** @test */
    public function supervisor_can_update_status_and_priority()
    {
        $staff = $this->staff();
        $supervisor = $this->supervisor();
        $issue = TechnicalIssue::factory()->create(['user_id' => $staff->id, 'user_role' => User::ROLE_STAFF]);

        $this->actingAs($supervisor)
            ->patch(route('admin.technical-issues.update', $issue), [
                'status' => TechnicalIssue::STATUS_RESOLVED,
                'priority' => TechnicalIssue::PRIORITY_URGENT,
            ])
            ->assertRedirect();

        $issue->refresh();
        $this->assertSame(TechnicalIssue::STATUS_RESOLVED, $issue->status);
        $this->assertSame(TechnicalIssue::PRIORITY_URGENT, $issue->priority);
        $this->assertSame($supervisor->id, $issue->updated_by);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $staff->id,
            'type' => 'technical_issue_status',
            'group_key' => "technical-issue-status:{$issue->id}",
        ]);
    }

    /** @test */
    public function manager_can_add_an_internal_note()
    {
        $staff = $this->staff();
        $supervisor = $this->supervisor();
        $issue = TechnicalIssue::factory()->create(['user_id' => $staff->id, 'user_role' => User::ROLE_STAFF]);

        $this->actingAs($supervisor)
            ->post(route('admin.technical-issues.notes.store', $issue), ['body' => 'Investigating the statement totals now.'])
            ->assertRedirect();

        $this->assertDatabaseHas('technical_issue_notes', [
            'technical_issue_id' => $issue->id,
            'user_id' => $supervisor->id,
            'body' => 'Investigating the statement totals now.',
        ]);

        $issue->refresh();
        $this->assertSame($supervisor->id, $issue->updated_by);
    }

    /** @test */
    public function managers_are_notified_when_a_report_is_submitted()
    {
        $admin = $this->admin();
        $supervisor = $this->supervisor();
        $staff = $this->staff();

        $this->actingAs($staff)
            ->post(route('admin.technical-issues.store'), $this->validPayload())
            ->assertRedirect();

        $issue = TechnicalIssue::first();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $admin->id,
            'type' => 'technical_issue',
            'group_key' => "technical-issue-submitted:{$issue->id}",
            'link' => route('admin.technical-issues.show', $issue),
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $supervisor->id,
            'type' => 'technical_issue',
            'group_key' => "technical-issue-submitted:{$issue->id}",
        ]);
    }

    /** @test */
    public function attachment_is_only_accessible_to_authorized_users()
    {
        $reporter = $this->staff('Reporter');
        $other = $this->staff('Other Person');
        $issue = TechnicalIssue::factory()->create(['user_id' => $reporter->id, 'user_role' => User::ROLE_STAFF]);

        $attachment = $issue->attachments()->create([
            'uploaded_by' => $reporter->id,
            'original_name' => 'totals.png',
            'path' => 'technical-issue-attachments/totals.png',
            'mime_type' => 'image/png',
            'size' => 1024,
        ]);
        Storage::disk('local')->put($attachment->path, 'fake-image-bytes');

        $signed = URL::temporarySignedRoute(
            'admin.technical-issues.attachments.view',
            now()->addMinutes(30),
            ['issue' => $issue->id, 'attachment' => $attachment->id]
        );

        $this->actingAs($reporter)->get($signed)->assertOk();
        $this->actingAs($other)->get($signed)->assertForbidden();
    }

    /** @test */
    public function attachment_urls_must_be_signed()
    {
        $reporter = $this->staff();
        $issue = TechnicalIssue::factory()->create(['user_id' => $reporter->id, 'user_role' => User::ROLE_STAFF]);

        $attachment = $issue->attachments()->create([
            'uploaded_by' => $reporter->id,
            'original_name' => 'totals.png',
            'path' => 'technical-issue-attachments/totals.png',
            'mime_type' => 'image/png',
            'size' => 1024,
        ]);

        $this->actingAs($reporter)
            ->get(route('admin.technical-issues.attachments.view', [$issue, $attachment]))
            ->assertForbidden();
    }

    /** @test */
    public function guests_are_redirected_to_login()
    {
        $this->get(route('admin.technical-issues.index'))->assertRedirect();
        $this->get(route('admin.technical-issues.create'))->assertRedirect();
    }

    /** @test */
    public function clients_cannot_access_issue_reporting()
    {
        $client = $this->client();

        $this->actingAs($client)
            ->get(route('admin.technical-issues.index'))
            ->assertForbidden();
    }

    /** @test */
    public function migrations_create_the_expected_tables_and_columns()
    {
        $this->assertTrue(Schema::hasTable('technical_issues'));
        $this->assertTrue(Schema::hasTable('technical_issue_attachments'));
        $this->assertTrue(Schema::hasTable('technical_issue_notes'));

        $this->assertTrue(Schema::hasColumns('technical_issues', [
            'id', 'reference', 'user_id', 'user_role', 'title', 'category',
            'description', 'page_url', 'priority', 'status', 'updated_by',
            'created_at', 'updated_at',
        ]));

        $this->assertTrue(Schema::hasColumns('technical_issue_attachments', [
            'id', 'technical_issue_id', 'uploaded_by', 'original_name', 'path',
            'mime_type', 'size', 'created_at', 'updated_at',
        ]));

        $this->assertTrue(Schema::hasColumns('technical_issue_notes', [
            'id', 'technical_issue_id', 'user_id', 'body', 'created_at', 'updated_at',
        ]));
    }

    /** @test */
    public function download_route_returns_file_with_nosniff_headers()
    {
        $staff = $this->staff();
        $issue = TechnicalIssue::factory()->create(['user_id' => $staff->id, 'user_role' => User::ROLE_STAFF]);

        $attachment = $issue->attachments()->create([
            'uploaded_by' => $staff->id,
            'original_name' => 'totals.png',
            'path' => 'technical-issue-attachments/totals.png',
            'mime_type' => 'image/jpeg',
            'size' => 1024,
        ]);
        Storage::disk('local')->put($attachment->path, file_get_contents(base_path('public/images/logo-icon.png')) ?: 'fake');

        $signed = URL::temporarySignedRoute(
            'admin.technical-issues.attachments.download',
            now()->addMinutes(30),
            ['issue' => $issue->id, 'attachment' => $attachment->id]
        );

        $response = $this->actingAs($staff)->get($signed)->assertOk();

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Content-Type', 'image/jpeg');
    }
}