<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminConfidentialityAcknowledged;
use App\Models\ActivityLog;
use App\Models\Announcement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AnnouncementsPageTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, string $label): User
    {
        return User::create([
            'name' => $label,
            'email' => strtolower($role).uniqid().'@example.com',
            'password' => bcrypt('secret'),
            'role' => $role,
            'email_verified_at' => now(),
            'confidentiality_acknowledged_at' => now(),
            'confidentiality_ack_version' => EnsureAdminConfidentialityAcknowledged::CURRENT_VERSION,
        ]);
    }

    private function admin(): User
    {
        return $this->user(User::ROLE_ADMIN, 'Announcements Admin');
    }

    private function staff(): User
    {
        return $this->user(User::ROLE_STAFF, 'Announcements Staff');
    }

    private function announcement(User $by, array $overrides = []): Announcement
    {
        return Announcement::create(array_merge([
            'title' => 'Annual maintenance schedule',
            'body' => 'Our office will be closed on the last Friday of the month for scheduled maintenance.',
            'image_path' => null,
            'posted_by' => $by->id,
            'posted_at' => now()->subDay(),
        ], $overrides));
    }

    public function test_admin_can_open_the_announcements_page(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->get('/admin/announcements');

        $response->assertOk();
        $response->assertSee('Communication');
        $response->assertSee('Announcements');
        $response->assertSee('Share important updates, reminders, and news with your clients.');
    }

    public function test_staff_can_open_the_announcements_page(): void
    {
        $staff = $this->staff();

        $response = $this->actingAs($staff)->get('/admin/announcements');

        $response->assertOk();
        $response->assertSee('Announcements');
        $response->assertSee('Create an announcement');
    }

    public function test_composer_card_includes_all_new_ui_pieces(): void
    {
        $admin = $this->admin();
        $this->announcement($admin);

        $response = $this->actingAs($admin)->get('/admin/announcements');

        $response->assertOk();
        $response->assertSee('Create an announcement');
        $response->assertSee('Publish an update that will appear on your public feed.');
        $response->assertSee('Content');
        $response->assertSee('Cover image');
        $response->assertSee('Title', false);
        $response->assertSee('Optional', false);
        $response->assertSee('Message');
        $response->assertSee('Choose an image');
        $response->assertSee('JPG, PNG, or WebP &middot; Maximum 5 MB', false);
        $response->assertSee('Live preview');
        $response->assertSee('Preview');
        $response->assertSee('Post announcement');
        $response->assertSee('Clear draft');
        $response->assertSee('data-submit-label="Posting…"', false);
    }

    public function test_new_announcement_button_only_shows_when_announcements_exist(): void
    {
        $admin = $this->admin();

        $empty = $this->actingAs($admin)->get('/admin/announcements');
        $empty->assertOk();
        $empty->assertDontSee('New announcement');

        $this->announcement($admin);

        $with = $this->actingAs($admin)->get('/admin/announcements');
        $with->assertOk();
        $with->assertSee('New announcement');
    }

    public function test_existing_announcement_is_rendered_with_actions(): void
    {
        $admin = $this->admin();
        $ann = $this->announcement($admin, ['title' => 'Quarterly tax reminders']);

        $response = $this->actingAs($admin)->get('/admin/announcements');

        $response->assertOk();
        $response->assertSee('Quarterly tax reminders');
        $response->assertSee('Our office will be closed on the last Friday of the month for scheduled maintenance.');
        $response->assertSee('Posted');
        $response->assertSee($admin->name);
        $response->assertSee('Recent announcements');
        $response->assertSee('method="POST"', false);

        $deleteForm = '<form method="POST" action="'.route('admin.announcements.destroy', $ann).'"';
        $response->assertSee($deleteForm, false);
    }

    public function test_empty_state_is_shown_when_no_announcements_exist(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->get('/admin/announcements');

        $response->assertOk();
        $response->assertSee('No announcements yet.');
    }

    public function test_message_validation_still_works(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->post('/admin/announcements', [
            'title' => 'Empty message',
            'body' => '',
        ]);

        $response->assertSessionHasErrors('body');
        $this->assertDatabaseMissing('announcements', ['title' => 'Empty message']);
    }

    public function test_image_validation_still_rejects_non_images(): void
    {
        $admin = $this->admin();
        Storage::fake('supabase');

        $response = $this->actingAs($admin)->post('/admin/announcements', [
            'title' => 'Bad file',
            'body' => 'This should fail validation.',
            'image' => UploadedFile::fake()->create('notes.txt', 20),
        ]);

        $response->assertSessionHasErrors('image');
    }

    public function test_announcement_still_posts_and_redirects_with_status(): void
    {
        $admin = $this->admin();
        Storage::fake('supabase');

        $this->actingAs($admin)
            ->withHeaders(['Referer' => route('admin.announcements.index')])
            ->post('/admin/announcements', [
                'title' => 'Server maintenance',
                'body' => 'Scheduled downtime this weekend.',
                'image' => UploadedFile::fake()->image('cover.png', 400, 260),
            ])
            ->assertRedirect(route('admin.announcements.index'))
            ->assertSessionHas('status', 'Announcement posted.');

        $this->assertDatabaseHas('announcements', [
            'title' => 'Server maintenance',
            'body' => 'Scheduled downtime this weekend.',
        ]);

        $ann = Announcement::where('title', 'Server maintenance')->first();
        $this->assertNotNull($ann);
        $this->assertNotNull($ann->image_path);
        Storage::disk('supabase')->assertExists($ann->image_path);
        $this->assertSame($admin->id, $ann->posted_by);

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $admin->id,
            'action' => 'settings.announcement_posted',
        ]);
    }

    public function test_announcement_can_still_be_deleted(): void
    {
        $admin = $this->admin();
        $ann = $this->announcement($admin);

        $this->actingAs($admin)
            ->withHeaders(['Referer' => route('admin.announcements.index')])
            ->delete(route('admin.announcements.destroy', $ann))
            ->assertRedirect(route('admin.announcements.index'))
            ->assertSessionHas('status', 'Announcement removed.');

        $this->assertDatabaseMissing('announcements', ['id' => $ann->id]);

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $admin->id,
            'action' => 'settings.announcement_deleted',
        ]);
    }
}