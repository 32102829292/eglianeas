<?php

namespace Tests\Feature;

use App\Mail\VerificationCodeMail;
use App\Models\ActivityLog;
use App\Models\User;
use App\Models\VerificationCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class ForgotPinTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->afterApplicationCreated(function () {
            Cache::flush();
        });
    }

    private function verifiedClient(string $pin = '1234'): User
    {
        return User::create([
            'name' => 'PIN Recovery Tester',
            'email' => 'pinrecover'.uniqid().'@gmail.com',
            'password' => Hash::make('unused'),
            'pin' => Hash::make($pin),
            'pin_set_at' => now(),
            'role' => User::ROLE_CLIENT,
            'email_verified_at' => now(),
        ]);
    }

    public function test_forgot_pin_screen_can_be_rendered(): void
    {
        $this->get(route('forgot-pin'))
            ->assertOk()
            ->assertSee('Forgot your PIN');
    }

    public function test_code_is_sent_for_existing_account_with_generic_message(): void
    {
        Mail::fake();

        $client = $this->verifiedClient();

        $this->post(route('forgot-pin.send'), ['email' => $client->email])
            ->assertRedirect(route('forgot-pin.verify'))
            ->assertSessionHas('status', 'If an account exists with that email, a verification code has been sent.');

        Mail::assertSent(VerificationCodeMail::class);

        $this->assertDatabaseHas('verification_codes', [
            'user_id' => $client->id,
            'used_at' => null,
        ]);
    }

    public function test_unknown_email_still_shows_generic_message_without_sending(): void
    {
        Mail::fake();

        $this->post(route('forgot-pin.send'), ['email' => 'nobody'.uniqid().'@gmail.com'])
            ->assertRedirect(route('forgot-pin.verify'))
            ->assertSessionHas('status', 'If an account exists with that email, a verification code has been sent.');

        Mail::assertNotSent(VerificationCodeMail::class);
        $this->assertDatabaseCount('verification_codes', 0);
    }

    public function test_verifying_with_wrong_code_is_rejected(): void
    {
        Mail::fake();

        $client = $this->verifiedClient();
        $this->post(route('forgot-pin.send'), ['email' => $client->email]);

        $this->post(route('forgot-pin.verify.post'), [
            'email' => $client->email,
            'code' => '000000',
        ])
            ->assertSessionHasErrors('code');

        $this->assertDatabaseMissing('verification_codes', [
            'user_id' => $client->id,
            'used_at' => null,
            'attempts' => 0,
        ]);
    }

    public function test_verifying_with_expired_code_is_rejected(): void
    {
        Mail::fake();

        $client = $this->verifiedClient();
        $this->post(route('forgot-pin.send'), ['email' => $client->email]);

        $record = VerificationCode::query()->where('user_id', $client->id)->latest()->firstOrFail();
        $record->update(['expires_at' => now()->subMinute()]);

        $sentCode = $this->captureSentCode();

        $this->post(route('forgot-pin.verify.post'), [
            'email' => $client->email,
            'code' => $sentCode,
        ])
            ->assertSessionHasErrors('code');
    }

    public function test_full_flow_resets_pin_and_new_pin_can_log_in(): void
    {
        Mail::fake();

        $client = $this->verifiedClient('1111');

        $this->post(route('forgot-pin.send'), ['email' => $client->email]);

        $sentCode = $this->captureSentCode();

        $this->post(route('forgot-pin.verify.post'), [
            'email' => $client->email,
            'code' => $sentCode,
        ])
            ->assertRedirect(route('forgot-pin.reset'))
            ->assertSessionHas('status', 'Code verified. Now set your new PIN.');

        $this->post(route('forgot-pin.reset.post'), [
            'pin' => '9876',
            'pin_confirmation' => '9876',
        ])
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'Your PIN has been reset. You can now log in with your new PIN.');

        $fresh = $client->fresh();
        $this->assertTrue(Hash::check('9876', $fresh->pin));
        $this->assertFalse(Hash::check('1111', $fresh->pin));

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $client->id,
            'action' => 'auth.pin_reset',
        ]);
    }

    public function test_new_pin_can_be_used_to_log_in(): void
    {
        Mail::fake();

        $client = $this->verifiedClient('1111');

        $this->post(route('forgot-pin.send'), ['email' => $client->email]);
        $this->post(route('forgot-pin.verify.post'), [
            'email' => $client->email,
            'code' => $this->captureSentCode(),
        ]);
        $this->post(route('forgot-pin.reset.post'), [
            'pin' => '9876',
            'pin_confirmation' => '9876',
        ]);

        $this->post(route('login.pin'), [
            'email' => $client->email,
            'pin' => '9876',
        ])
            ->assertRedirect(route('client.dashboard'));
    }

    public function test_repeated_code_requests_are_rate_limited(): void
    {
        Mail::fake();

        $client = $this->verifiedClient();
        $email = $client->email;

        RateLimiter::clear('forgot-pin:'.$email);

        for ($i = 0; $i < 3; $i++) {
            $this->post(route('forgot-pin.send'), ['email' => $email])
                ->assertRedirect(route('forgot-pin.verify'));
        }

        $this->post(route('forgot-pin.send'), ['email' => $email])
            ->assertSessionHasErrors('email');
    }

    private function captureSentCode(): string
    {
        $sentCode = null;

        Mail::assertSent(VerificationCodeMail::class, function (VerificationCodeMail $mail) use (&$sentCode) {
            $sentCode = $mail->code;

            return true;
        });

        return (string) $sentCode;
    }
}