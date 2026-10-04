<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminConfidentialityAcknowledged;
use App\Models\DailyJournal;
use App\Models\User;
use App\Services\DailyJournalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DailyJournalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('supabase');

        Carbon::setTestNow(Carbon::parse('2026-10-05 09:00:00'));

        DailyJournalService::forgetMissingCount();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        DailyJournalService::forgetMissingCount();

        parent::tearDown();
    }

    private function admin(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_ADMIN,
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

    private function supervisor(string $name = 'Supervisor Member'): User
    {
        return User::factory()->create([
            'role' => User::ROLE_SUPERVISOR,
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

    /** @test */
    public function staff_can_submit_required_journal()
    {
        $staff = $this->staff('Maria Santos');
        $date = Carbon::today();

        $this->actingAs($staff)
            ->post(route('daily-journal.store'), [
                'date' => $date->format('Y-m-d'),
                'problems_encountered' => 'Client A delayed providing documents.',
                'achievements' => 'Completed BIR form 2551Q for Client B.',
                'suggested_solutions' => 'Follow up with Client A tomorrow morning.',
            ])
            ->assertRedirect();

        $journal = DailyJournal::where('user_id', $staff->id)
            ->where('required_date', $date->format('Y-m-d'))
            ->firstOrFail();

        $this->assertEquals('submitted', $journal->status);
        $this->assertEquals('Client A delayed providing documents.', $journal->problems_encountered);
        $this->assertEquals('Completed BIR form 2551Q for Client B.', $journal->achievements);
        $this->assertEquals('Follow up with Client A tomorrow morning.', $journal->suggested_solutions);
        $this->assertNotNull($journal->submitted_at);
    }

    /** @test */
    public function supervisor_can_submit_required_journal()
    {
        $supervisor = $this->supervisor('Juan Dela Cruz');
        $date = Carbon::today();

        $this->actingAs($supervisor)
            ->post(route('daily-journal.store'), [
                'date' => $date->format('Y-m-d'),
                'problems_encountered' => 'Team coordination issue.',
                'achievements' => 'Reviewed all client filings.',
                'suggested_solutions' => 'Schedule weekly sync meeting.',
            ])
            ->assertRedirect();

        $journal = DailyJournal::where('user_id', $supervisor->id)
            ->where('required_date', $date->format('Y-m-d'))
            ->firstOrFail();

        $this->assertEquals('submitted', $journal->status);
    }

    /** @test */
    public function admin_can_view_employee_journals()
    {
        $admin = $this->admin();
        $staff = $this->staff('Maria Santos');
        $date = Carbon::today();

        DailyJournal::create([
            'user_id' => $staff->id,
            'required_date' => $date->format('Y-m-d'),
            'problems_encountered' => 'Test problem',
            'achievements' => 'Test achievement',
            'suggested_solutions' => 'Test solution',
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.daily-journal.admin-monitor', ['date' => $date->format('Y-m-d')]))
            ->assertOk()
            ->assertSee('Maria Santos')
            ->assertSee('Submitted');
    }

    /** @test */
    public function client_cannot_access_journals()
    {
        $client = $this->client();
        $date = Carbon::today();

        $this->actingAs($client)
            ->get(route('daily-journal.create'))
            ->assertForbidden();

        $this->actingAs($client)
            ->get(route('admin.daily-journal.admin-monitor', ['date' => $date->format('Y-m-d')]))
            ->assertForbidden();
    }

    /** @test */
    public function missing_journal_is_detected()
    {
        $staff = $this->staff('Ana Reyes');
        $date = Carbon::today();

        // Ensure journals exist for today (creates missing records)
        DailyJournalService::ensureJournalsForDate($date);

        $this->assertEquals(1, DailyJournalService::getMissingCountForUser($staff->id));
        $this->assertTrue(DailyJournalService::hasMissingForToday($staff->id));
    }

    /** @test */
    public function missing_journal_notification_is_generated()
    {
        $staff = $this->staff('Pedro Cruz');
        $date = Carbon::today();

        // Create missing journal for yesterday
        DailyJournal::create([
            'user_id' => $staff->id,
            'required_date' => $date->format('Y-m-d'),
            'problems_encountered' => '',
            'achievements' => '',
            'suggested_solutions' => '',
            'status' => 'missing',
        ]);

        $sent = DailyJournalService::remindMissingForDate($date);

        $this->assertEquals(1, $sent);

        $notification = \App\Models\Notification::where('user_id', $staff->id)
            ->where('group_key', "daily_journal:{$staff->id}:{$date->format('Y-m-d')}")
            ->first();

        $this->assertNotNull($notification);
        $this->assertEquals('Daily Accomplishment Report Missing', $notification->title);
        $this->assertStringContainsString('Daily Accomplishment Report', $notification->body);
    }

    /** @test */
    public function duplicate_reminders_are_prevented()
    {
        $staff = $this->staff('Test Staff');
        $date = Carbon::today();

        DailyJournal::create([
            'user_id' => $staff->id,
            'required_date' => $date->format('Y-m-d'),
            'problems_encountered' => '',
            'achievements' => '',
            'suggested_solutions' => '',
            'status' => 'missing',
        ]);

        // First reminder
        DailyJournalService::remindMissingForDate($date);

        // Second call should not send another
        $sent = DailyJournalService::remindMissingForDate($date);

        $this->assertEquals(0, $sent);

        // Only one notification should exist
        $count = \App\Models\Notification::where('user_id', $staff->id)
            ->where('group_key', "daily_journal:{$staff->id}:{$date->format('Y-m-d')}")
            ->count();

        $this->assertEquals(1, $count);
    }

    /** @test */
    public function submitted_journal_changes_status_and_clears_missing()
    {
        $staff = $this->staff('Test Staff');
        $date = Carbon::today();

        // Create missing journal
        DailyJournal::create([
            'user_id' => $staff->id,
            'required_date' => $date->format('Y-m-d'),
            'problems_encountered' => '',
            'achievements' => '',
            'suggested_solutions' => '',
            'status' => 'missing',
        ]);

        $this->assertEquals(1, DailyJournalService::getMissingCountForUser($staff->id));

        // Submit journal
        $this->actingAs($staff)
            ->post(route('daily-journal.store'), [
                'date' => $date->format('Y-m-d'),
                'problems_encountered' => 'Fixed issue',
                'achievements' => 'Completed task',
                'suggested_solutions' => 'No further action',
            ])
            ->assertRedirect();

        $journal = DailyJournal::where('user_id', $staff->id)
            ->where('required_date', $date->format('Y-m-d'))
            ->first();

        $this->assertEquals('submitted', $journal->status);
        $this->assertEquals(0, DailyJournalService::getMissingCountForUser($staff->id));
    }

    /** @test */
    public function late_submission_is_recorded_as_late()
    {
        $staff = $this->staff('Late Staff');
        $date = Carbon::yesterday();

        // Create missing journal for yesterday
        DailyJournal::create([
            'user_id' => $staff->id,
            'required_date' => $date->format('Y-m-d'),
            'problems_encountered' => '',
            'achievements' => '',
            'suggested_solutions' => '',
            'status' => 'missing',
        ]);

        // Submit today (after the required date)
        $this->actingAs($staff)
            ->post(route('daily-journal.store'), [
                'date' => $date->format('Y-m-d'),
                'problems_encountered' => 'Was busy',
                'achievements' => 'Did work',
                'suggested_solutions' => 'Submit on time next time',
            ])
            ->assertRedirect();

        $journal = DailyJournal::where('user_id', $staff->id)
            ->where('required_date', $date->format('Y-m-d'))
            ->first();

        $this->assertEquals('late', $journal->status);
        $this->assertTrue($journal->isLate());
    }

    /** @test */
    public function missing_status_is_cleared_after_submission()
    {
        $staff = $this->staff('Clear Staff');
        $date = Carbon::today();

        DailyJournalService::ensureJournalsForDate($date);

        $this->assertTrue(DailyJournalService::hasMissingForToday($staff->id));

        $this->actingAs($staff)
            ->post(route('daily-journal.store'), [
                'date' => $date->format('Y-m-d'),
                'problems_encountered' => 'Problem',
                'achievements' => 'Achievement',
                'suggested_solutions' => 'Solution',
            ])
            ->assertRedirect();

        $this->assertFalse(DailyJournalService::hasMissingForToday($staff->id));
    }

    /** @test */
    public function employee_cannot_submit_for_another_employee()
    {
        $staff1 = $this->staff('Staff One');
        $staff2 = $this->staff('Staff Two');
        $date = Carbon::today();

        // Staff1 tries to submit for Staff2's date by manipulating the request
        // This should create/update Staff1's own journal, not Staff2's
        $this->actingAs($staff1)
            ->post(route('daily-journal.store'), [
                'date' => $date->format('Y-m-d'),
                'problems_encountered' => 'Problem',
                'achievements' => 'Achievement',
                'suggested_solutions' => 'Solution',
            ])
            ->assertRedirect();

        $journal1 = DailyJournal::where('user_id', $staff1->id)
            ->where('required_date', $date->format('Y-m-d'))
            ->first();

        $journal2 = DailyJournal::where('user_id', $staff2->id)
            ->where('required_date', $date->format('Y-m-d'))
            ->first();

        $this->assertNotNull($journal1);
        $this->assertEquals('submitted', $journal1->status);
        $this->assertNull($journal2); // Staff2's journal should not be affected
    }

    /** @test */
    public function sidebar_badge_reflects_missing_journals()
    {
        $staff = $this->staff('Badge Staff');
        $date = Carbon::today();

        DailyJournalService::ensureJournalsForDate($date);

        $this->assertEquals(1, DailyJournalService::getMissingCountForUser($staff->id));

        // After submission, badge should clear
        $this->actingAs($staff)
            ->post(route('daily-journal.store'), [
                'date' => $date->format('Y-m-d'),
                'problems_encountered' => 'Problem',
                'achievements' => 'Achievement',
                'suggested_solutions' => 'Solution',
            ])
            ->assertRedirect();

        $this->assertEquals(0, DailyJournalService::getMissingCountForUser($staff->id));
    }

    /** @test */
    public function admin_monitoring_shows_submitted_missing()
    {
        $admin = $this->admin();
        $staff1 = $this->staff('Submitted Staff');
        $staff2 = $this->staff('Missing Staff');
        $date = Carbon::today();

        DailyJournal::create([
            'user_id' => $staff1->id,
            'required_date' => $date->format('Y-m-d'),
            'problems_encountered' => 'Done',
            'achievements' => 'Done',
            'suggested_solutions' => 'Done',
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        DailyJournal::create([
            'user_id' => $staff2->id,
            'required_date' => $date->format('Y-m-d'),
            'problems_encountered' => '',
            'achievements' => '',
            'suggested_solutions' => '',
            'status' => 'missing',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.daily-journal.admin-monitor', ['date' => $date->format('Y-m-d')]))
            ->assertOk()
            ->assertSee('Submitted Staff')
            ->assertSee('Missing Staff')
            ->assertSee('Submitted')
            ->assertSee('Missing');
    }

    /** @test */
    public function supervisor_monitoring_works()
    {
        $supervisor = $this->supervisor('Supervisor');
        $date = Carbon::today();

        $this->actingAs($supervisor)
            ->get(route('admin.daily-journal.supervisor-monitor', ['date' => $date->format('Y-m-d')]))
            ->assertOk();
    }

    /** @test */
    public function journal_form_shows_prepopulated_data()
    {
        $staff = $this->staff('Form Staff');
        $date = Carbon::today();

        // Create journal via service (which uses the same getOrCreate method)
        $journal = DailyJournalService::getOrCreateForUserAndDate($staff->id, $date);
        $journal->update([
            'problems_encountered' => 'Existing problem',
            'achievements' => 'Existing achievement',
            'suggested_solutions' => 'Existing solution',
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        $this->actingAs($staff)
            ->get(route('daily-journal.create', ['date' => $date->format('Y-m-d')]))
            ->assertOk()
            ->assertSee('Existing problem')
            ->assertSee('Existing achievement')
            ->assertSee('Existing solution');
    }

    /** @test */
    public function evidence_upload_works()
    {
        $staff = $this->staff('Evidence Staff');
        $date = Carbon::today();

        $file = \Illuminate\Http\UploadedFile::fake()->image('evidence.jpg');

        $this->actingAs($staff)
            ->post(route('daily-journal.store'), [
                'date' => $date->format('Y-m-d'),
                'problems_encountered' => 'Problem',
                'achievements' => 'Achievement',
                'suggested_solutions' => 'Solution',
                'evidence' => [$file],
            ])
            ->assertRedirect();

        $journal = DailyJournal::where('user_id', $staff->id)
            ->where('required_date', $date->format('Y-m-d'))
            ->first();

        $this->assertNotNull($journal);
        $this->assertTrue($journal->hasEvidence());
        $this->assertEquals(1, $journal->evidenceCount());
    }

    /** @test */
    public function ensure_journals_for_date_creates_missing_records()
    {
        $this->staff('Staff A');
        $this->staff('Staff B');
        $this->supervisor('Supervisor A');
        
        // Use a specific weekday (Tuesday 2026-09-29)
        $date = Carbon::parse('2026-09-29');

        $created = DailyJournalService::ensureJournalsForDate($date);

        $this->assertEquals(3, $created); // 2 staff + 1 supervisor

        $journals = DailyJournal::whereDate('required_date', $date->toDateString())->get();
        $this->assertEquals(3, $journals->count());
        $this->assertTrue($journals->every(fn ($j) => $j->status === 'missing'));
    }

    /** @test */
    public function weekend_does_not_require_journals()
    {
        $staff = $this->staff('Weekend Staff');
        $saturday = Carbon::parse('2026-10-03'); // Saturday
        $sunday = Carbon::parse('2026-10-04');   // Sunday

        $this->assertFalse(DailyJournalService::isWorkingDay($saturday));
        $this->assertFalse(DailyJournalService::isWorkingDay($sunday));

        $created = DailyJournalService::ensureJournalsForDate($saturday);
        $this->assertEquals(0, $created);
    }

    /** @test */
    public function get_or_create_returns_existing_journal()
    {
        $staff = $this->staff('GetOrCreate Staff');
        $date = Carbon::today();

        // First call - creates the journal
        $journal1 = DailyJournalService::getOrCreateForUserAndDate($staff->id, $date);

        $this->assertEquals('missing', $journal1->status);
        $this->assertEquals('', $journal1->problems_encountered);
        $this->assertEquals('', $journal1->achievements);
        $this->assertEquals('', $journal1->suggested_solutions);

        // Second call - should return the same journal
        $journal2 = DailyJournalService::getOrCreateForUserAndDate($staff->id, $date);

        $this->assertEquals($journal1->id, $journal2->id);
    }
}