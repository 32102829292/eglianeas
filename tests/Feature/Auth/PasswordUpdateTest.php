<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_pin_can_be_updated_from_the_security_screen(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_STAFF]);

        $response = $this
            ->actingAs($user)
            ->from('/security')
            ->post('/security/pin', [
                'current_password' => 'password',
                'pin' => '5678',
                'pin_confirmation' => '5678',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/security');

        $this->assertTrue(Hash::check('5678', $user->refresh()->pin));
        $this->assertNotNull($user->pin_set_at);
    }

    public function test_correct_password_must_be_provided_to_set_pin(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_STAFF]);

        $response = $this
            ->actingAs($user)
            ->from('/security')
            ->post('/security/pin', [
                'current_password' => 'wrong-password',
                'pin' => '5678',
                'pin_confirmation' => '5678',
            ]);

        $response
            ->assertSessionHasErrors('current_password')
            ->assertRedirect('/security');

        $this->assertNull($user->fresh()->pin);
    }
}
