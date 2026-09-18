<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * PIN login correctness (Bug: "invalid even for the correct PIN").
 *
 * These pin the behaviours that protect the bug: the PIN stays a plain 4-digit
 * string end-to-end (so leading zeros like "0123" are never lost or coerced),
 * it is stored only as a bcrypt hash, only the owning account authenticates,
 * and a changed PIN immediately supersedes the old one.
 */
class PinLoginTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $overrides = []): User
    {
        return User::create(array_merge([
            'name' => 'PIN Test User',
            'email' => 'pin'.uniqid().'@example.com',
            'password' => bcrypt('secret123'),
            'role' => User::ROLE_ADMIN,
            'email_verified_at' => now(),
            'confidentiality_acknowledged_at' => now(),
        ], $overrides));
    }

    private function tryPin(string $email, string $pin): \Illuminate\Testing\TestResponse
    {
        return $this->post(route('login.pin'), [
            'email' => $email,
            'pin' => $pin,
        ]);
    }

    public function test_correct_pin_authenticates_the_user(): void
    {
        $user = $this->user(['pin' => Hash::make('1234'), 'pin_set_at' => now()]);

        $this->tryPin($user->email, '1234')
            ->assertRedirect($user->getDashboardRoute())
            ->assertSessionHasNoErrors();

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'action' => 'auth.login_pin',
        ]);
    }

    public function test_incorrect_pin_is_rejected(): void
    {
        $user = $this->user(['pin' => Hash::make('1234'), 'pin_set_at' => now()]);

        $this->tryPin($user->email, '9999')
            ->assertSessionHasErrors('pin');

        $this->assertGuest();
    }

    public function test_leading_zero_pin_0123_authenticates(): void
    {
        $user = $this->user(['pin' => Hash::make('0123'), 'pin_set_at' => now()]);

        $this->tryPin($user->email, '0123')
            ->assertRedirect($user->getDashboardRoute())
            ->assertSessionHasNoErrors();

        $this->assertAuthenticatedAs($user);
    }

    public function test_updated_pin_works_and_old_pin_is_invalid(): void
    {
        $user = $this->user(['pin' => Hash::make('1234'), 'pin_set_at' => now()]);

        $this->actingAs($user)
            ->post(route('security.pin'), [
                'current_password' => 'secret123',
                'pin' => '2468',
                'pin_confirmation' => '2468',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('2468', $user->fresh()->pin));
        $this->assertFalse(Hash::check('1234', $user->fresh()->pin));

        Auth::logout();
        $this->tryPin($user->email, '2468')->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($user);

        Auth::logout();
        $this->tryPin($user->email, '1234')->assertSessionHasErrors('pin');
        $this->assertGuest();
    }

    public function test_pin_is_stored_hashed_not_in_plaintext(): void
    {
        $user = $this->user(['pin' => Hash::make('0123'), 'pin_set_at' => now()]);

        $raw = $user->fresh()->getAttributes()['pin'];

        $this->assertStringStartsWith('$2', $raw);
        $this->assertTrue(Hash::check('0123', $raw));
        $this->assertNotSame('0123', $raw);
    }

    public function test_only_the_owning_user_is_authenticated_by_their_pin(): void
    {
        $userA = $this->user(['pin' => Hash::make('1234'), 'pin_set_at' => now()]);
        $userB = $this->user(['pin' => Hash::make('9876'), 'pin_set_at' => now()]);

        $this->tryPin($userB->email, '1234')->assertSessionHasErrors('pin');
        $this->assertGuest();

        $this->tryPin($userB->email, '9876')->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($userB);
        $this->assertNotSame($userA->id, Auth::id());
    }

    public function test_invalid_pin_authenticates_no_one(): void
    {
        $this->user(['pin' => Hash::make('1234'), 'pin_set_at' => now()]);

        $this->tryPin('nobody@example.com', '0000')->assertSessionHasErrors('pin');
        $this->assertGuest();
    }

    public function test_change_pin_requires_current_pin_and_logs_activity(): void
    {
        $user = $this->user(['pin' => Hash::make('1111'), 'pin_set_at' => now()]);

        $this->actingAs($user)
            ->post(route('security.pin'), [
                'current_password' => '6543',
                'pin' => '2222',
                'pin_confirmation' => '2222',
            ])
            ->assertSessionHasErrors('current_password');

        $this->actingAs($user)
            ->post(route('security.pin'), [
                'current_password' => 'secret123',
                'pin' => '2222',
                'pin_confirmation' => '2222',
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('2222', $user->fresh()->pin));
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'action' => 'security.pin_set',
        ]);
        $this->assertSame(1, ActivityLog::where('user_id', $user->id)->where('action', 'security.pin_set')->count());
    }
}