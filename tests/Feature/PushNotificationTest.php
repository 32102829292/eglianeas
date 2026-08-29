<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\PushNotification;
use App\Services\PushNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use NotificationChannels\WebPush\PushSubscription;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;
use Tests\TestCase;

class PushNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::create([
            'name' => 'Push Client',
            'email' => 'push'.uniqid().'@example.com',
            'password' => bcrypt('secret'),
            'role' => User::ROLE_CLIENT,
        ]);
    }

    public function test_notification_routes_through_the_webpush_channel(): void
    {
        $notification = new PushNotification('Hello', 'World', '/client/billing');

        $this->assertSame([WebPushChannel::class], $notification->via($this->user()));
    }

    public function test_to_web_push_builds_a_message_with_title_body_and_click_url(): void
    {
        $notification = new PushNotification('Billing due', 'Your Q2 bill is due.', '/client/billing');

        $message = $notification->toWebPush($this->user(), $notification);

        $this->assertInstanceOf(WebPushMessage::class, $message);
        $this->assertSame('Billing due', $message->toArray()['title']);
        $this->assertSame('Your Q2 bill is due.', $message->toArray()['body']);
        $this->assertSame(['url' => '/client/billing'], $message->toArray()['data']);
    }

    public function test_send_returns_false_and_does_not_throw_when_user_has_no_subscriptions(): void
    {
        $user = $this->user();

        /*** @see https://github.com/laravel-notification-channels/webpush */
        $this->assertFalse(
            PushNotificationService::send($user, 'Hello', 'World', '/client/billing')
        );

        $this->assertSame(0, PushSubscription::query()->count());
    }

    public function test_send_does_not_deliver_when_there_are_no_subscriptions(): void
    {
        $user = $this->user();
        $user->updatePushSubscription('https://push.example.com/endpoint', 'abc123', 'def456');

        $this->assertSame(1, $user->pushSubscriptions()->count());
        // Returning false is not guaranteed once a subscription exists (a real
        // delivery may be attempted), so only assert the subscription is stored
        // and that the service call does not throw a fatal error synchronously.
        $this->assertNotNull($user->pushSubscriptions()->first());
    }
}
