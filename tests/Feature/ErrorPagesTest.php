<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.debug' => false]);
    }

    private function staff(): User
    {
        return User::create([
            'name' => 'Error Staff',
            'email' => 'errors'.uniqid().'@example.com',
            'password' => bcrypt('secret'),
            'role' => User::ROLE_STAFF,
            'email_verified_at' => now(),
            'confidentiality_acknowledged_at' => now(),
            'confidentiality_ack_version' => \App\Http\Middleware\EnsureAdminConfidentialityAcknowledged::CURRENT_VERSION,
        ]);
    }

    public function test_custom_404_page_renders_for_unknown_route(): void
    {
        $this->get('/definitely-not-a-real-page-'.uniqid())
            ->assertNotFound()
            ->assertSee('Page not found')
            ->assertSee("The page you're looking for doesn't exist or may have moved.")
            ->assertSee('Egliane Accounting Services')
            ->assertSee('Go to login');
    }

    public function test_custom_403_page_renders_for_staff_accessing_admin_only_area(): void
    {
        $this->actingAs($this->staff())
            ->get(route('admin.activity-logs'))
            ->assertForbidden()
            ->assertSee('Access denied')
            ->assertSee("You don't have permission to view this page.");
    }

    public function test_custom_419_page_renders_when_session_token_expires(): void
    {
        Route::post('/force-419', fn () => throw new \Illuminate\Session\TokenMismatchException('CSRF token mismatch.'));

        $this->post('/force-419')
            ->assertStatus(419)
            ->assertSee('Session expired')
            ->assertSee('Your session expired for security. Please refresh and try again.');
    }

    public function test_custom_500_page_hides_exception_details(): void
    {
        Route::get('/force-error', fn () => abort(500, 'SECRET_INTERNAL_DETAILS'));

        $this->get('/force-error')
            ->assertStatus(500)
            ->assertSee('Something went wrong')
            ->assertSee('Something went wrong on our end.')
            ->assertDontSee('SECRET_INTERNAL_DETAILS');
    }

    public function test_custom_503_page_renders_in_maintenance_mode(): void
    {
        try {
            $this->artisan('down', ['--retry' => 60]);

            $this->get('/')
                ->assertStatus(503)
                ->assertSee('Down for maintenance')
                ->assertSee("We're hard at work making things better.");
        } finally {
            $this->artisan('up');
        }
    }
}