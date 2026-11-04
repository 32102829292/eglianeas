<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminConfidentialityAcknowledged;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ClientCertificateOfRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function internal(string $role, string $label): User
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

    private function client(string $name = 'COR Client'): User
    {
        $client = User::create([
            'name' => $name,
            'email' => 'cor-client'.uniqid().'@example.com',
            'password' => bcrypt('secret'),
            'role' => User::ROLE_CLIENT,
            'email_verified_at' => now(),
            'approved_at' => now(),
            'confidentiality_acknowledged_at' => now(),
            'confidentiality_ack_version' => EnsureAdminConfidentialityAcknowledged::CURRENT_VERSION,
        ]);

        $client->getClientProfile()->update([
            'status' => 'current',
            'business_type' => 'Corporation',
        ]);

        return $client;
    }

    public function test_admin_can_upload_a_certificate_of_registration(): void
    {
        Storage::fake('local');

        $admin = $this->internal(User::ROLE_ADMIN, 'COR Admin');
        $client = $this->client();

        $this->actingAs($admin)
            ->post(route('admin.clients.cor.upload', $client), [
                'certificate_of_registration' => UploadedFile::fake()->create('certificate.pdf', 120, 'application/pdf'),
            ])
            ->assertRedirect(route('admin.clients.show', $client))
            ->assertSessionHas('status', 'Certificate of Registration uploaded.');

        $profile = $client->fresh()->profile;

        $this->assertNotNull($profile->cor_document_path);
        $this->assertSame('certificate.pdf', $profile->cor_document_name);
        $this->assertSame('application/pdf', $profile->cor_document_mime);
        $this->assertNotNull($profile->cor_document_uploaded_at);
        Storage::disk('local')->assertExists($profile->cor_document_path);

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $admin->id,
            'action' => 'admin.client_cor_uploaded',
        ]);
    }

    public function test_supervisor_can_upload_but_staff_cannot(): void
    {
        Storage::fake('local');

        $client = $this->client();

        $this->actingAs($this->internal(User::ROLE_STAFF, 'COR Staff'))
            ->post(route('admin.clients.cor.upload', $client), [
                'certificate_of_registration' => UploadedFile::fake()->create('staff.pdf', 80, 'application/pdf'),
            ])
            ->assertForbidden();

        $this->assertNull($client->fresh()->profile->cor_document_path);

        $this->actingAs($this->internal(User::ROLE_SUPERVISOR, 'COR Supervisor'))
            ->post(route('admin.clients.cor.upload', $client), [
                'certificate_of_registration' => UploadedFile::fake()->create('supervisor.pdf', 80, 'application/pdf'),
            ])
            ->assertRedirect(route('admin.clients.show', $client));

        $this->assertNotNull($client->fresh()->profile->cor_document_path);
    }

    public function test_replacing_the_certificate_removes_the_previous_private_file(): void
    {
        Storage::fake('local');

        $admin = $this->internal(User::ROLE_ADMIN, 'COR Replacer');
        $client = $this->client();

        $this->actingAs($admin)->post(route('admin.clients.cor.upload', $client), [
            'certificate_of_registration' => UploadedFile::fake()->create('first.pdf', 100, 'application/pdf'),
        ]);

        $firstPath = $client->fresh()->profile->cor_document_path;

        $this->actingAs($admin)
            ->post(route('admin.clients.cor.upload', $client), [
                'certificate_of_registration' => UploadedFile::fake()->create('second.png', 100, 'image/png'),
            ])
            ->assertRedirect(route('admin.clients.show', $client))
            ->assertSessionHas('status', 'Certificate of Registration replaced.');

        $profile = $client->fresh()->profile;

        $this->assertNotSame($firstPath, $profile->cor_document_path);
        $this->assertSame('second.png', $profile->cor_document_name);
        $this->assertSame('image/png', $profile->cor_document_mime);
        Storage::disk('local')->assertMissing($firstPath);
        Storage::disk('local')->assertExists($profile->cor_document_path);
    }

    public function test_certificate_rejects_unsupported_file_types(): void
    {
        Storage::fake('local');

        $client = $this->client();

        $this->actingAs($this->internal(User::ROLE_ADMIN, 'COR Validator'))
            ->post(route('admin.clients.cor.upload', $client), [
                'certificate_of_registration' => UploadedFile::fake()->create('payload.php', 80, 'application/x-php'),
            ])
            ->assertSessionHasErrors('certificate_of_registration');

        $this->assertNull($client->fresh()->profile->cor_document_path);
    }

    public function test_admin_and_supervisor_can_view_and_download_the_certificate_but_staff_cannot(): void
    {
        Storage::fake('local');

        $client = $this->client();
        $admin = $this->internal(User::ROLE_ADMIN, 'COR Viewer');
        $supervisor = $this->internal(User::ROLE_SUPERVISOR, 'COR Viewer Supervisor');
        $staff = $this->internal(User::ROLE_STAFF, 'COR Viewer Staff');

        $this->actingAs($admin)->post(route('admin.clients.cor.upload', $client), [
            'certificate_of_registration' => UploadedFile::fake()->create('viewable.pdf', 100, 'application/pdf'),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.clients.cor.view', $client))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->actingAs($supervisor)
            ->get(route('admin.clients.cor.download', $client))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->actingAs($staff)->get(route('admin.clients.cor.view', $client))->assertForbidden();
        $this->actingAs($staff)->get(route('admin.clients.cor.download', $client))->assertForbidden();
    }

    public function test_client_list_and_profile_show_certificate_status_and_actions(): void
    {
        Storage::fake('local');

        $admin = $this->internal(User::ROLE_ADMIN, 'COR List Admin');
        $withCertificate = $this->client('With Certificate');
        $withoutCertificate = $this->client('Without Certificate');

        $html = $this->actingAs($admin)
            ->get(route('admin.clients.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Certificate of Registration', $html);
        $this->assertStringContainsString('Not uploaded', $html);

        $this->actingAs($admin)->post(route('admin.clients.cor.upload', $withCertificate), [
            'certificate_of_registration' => UploadedFile::fake()->create('listed.pdf', 100, 'application/pdf'),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.clients.index'))
            ->assertOk()
            ->assertSee('Available')
            ->assertSee(route('admin.clients.cor.view', $withCertificate), false);

        $this->actingAs($admin)
            ->get(route('admin.clients.show', $withoutCertificate))
            ->assertOk()
            ->assertSee('No Certificate of Registration uploaded.')
            ->assertSee(route('admin.clients.cor.upload', $withoutCertificate), false);
    }

    public function test_staff_see_certificate_status_but_not_private_actions(): void
    {
        Storage::fake('local');

        $admin = $this->internal(User::ROLE_ADMIN, 'COR Staff View Admin');
        $staff = $this->internal(User::ROLE_STAFF, 'COR Staff View');
        $client = $this->client();

        $this->actingAs($admin)->post(route('admin.clients.cor.upload', $client), [
            'certificate_of_registration' => UploadedFile::fake()->create('private.pdf', 100, 'application/pdf'),
        ]);

        $this->actingAs($staff)
            ->get(route('admin.clients.show', $client))
            ->assertOk()
            ->assertSee('Available')
            ->assertDontSee(route('admin.clients.cor.view', $client), false)
            ->assertDontSee(route('admin.clients.cor.download', $client), false)
            ->assertDontSee(route('admin.clients.cor.upload', $client), false);
    }
}
