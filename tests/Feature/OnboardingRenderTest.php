<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnboardingRenderTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::create([
            'name' => 'Tour '.$role,
            'email' => 'tour-'.$role.uniqid().'@example.com',
            'password' => bcrypt('secret'),
            'role' => $role,
            'email_verified_at' => now(),
        ]);
    }

    public function test_admin_sees_admin_tour_with_own_steps_only(): void
    {
        $this->actingAs($this->user(User::ROLE_ADMIN))
            ->blade('<x-onboarding />')
            ->assertSee('data-eas-onboarding', false)
            ->assertSee('data-role="admin"', false)
            ->assertSee('View, search, and manage client records from this section.')
            ->assertSee('Track which BIR forms each client needs and their current status.')
            ->assertDontSee('Your Billing Statements');
    }

    public function test_staff_sees_admin_tour_with_own_role(): void
    {
        $this->actingAs($this->user(User::ROLE_STAFF))
            ->blade('<x-onboarding />')
            ->assertSee('data-role="staff"', false)
            ->assertSee('Manage Clients')
            ->assertSee('View, search, and manage client records from this section.')
            ->assertDontSee('Your Billing Statements');
    }

    public function test_client_sees_client_tour_with_own_steps_only(): void
    {
        $this->actingAs($this->user(User::ROLE_CLIENT))
            ->blade('<x-onboarding />')
            ->assertSee('data-role="client"', false)
            ->assertSee('Welcome to Egliane Accounting Services')
            ->assertSee('Your Billing Statements')
            ->assertDontSee('Manage Clients')
            ->assertDontSee('View, search, and manage client records from this section.');
    }

    public function test_guest_gets_no_onboarding_markup(): void
    {
        $this->blade('<x-onboarding />')
            ->assertDontSee('data-eas-onboarding', false);
    }
}