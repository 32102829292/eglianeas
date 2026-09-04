<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Models\VerificationCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_verification_screen_can_be_rendered(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this
            ->withSession(['verification_user_id' => $user->id])
            ->get('/verify-account');

        $response->assertOk();
    }

    public function test_email_can_be_verified_with_code(): void
    {
        $user = User::factory()->unverified()->create();
        VerificationCode::issue($user, '123456');

        $response = $this
            ->withSession(['verification_user_id' => $user->id])
            ->post('/verify-account', ['code' => '123456']);

        $response->assertSessionHasNoErrors();
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $response->assertRedirect($user->getDashboardRoute());
    }

    public function test_email_is_not_verified_with_invalid_code(): void
    {
        $user = User::factory()->unverified()->create();
        VerificationCode::issue($user, '123456');

        $response = $this
            ->withSession(['verification_user_id' => $user->id])
            ->post('/verify-account', ['code' => '000000']);

        $response->assertSessionHasErrors('code');
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
        $this->assertGuest();
    }
}
