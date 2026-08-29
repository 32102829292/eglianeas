<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class PushNotification extends Notification
{
    public function __construct(
        public string $title,
        public string $body,
        public string $url = '/',
    ) {}

    public function via($notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush($notifiable, $notification): WebPushMessage
    {
        return (new WebPushMessage())
            ->title($this->title)
            ->body($this->body)
            ->icon('/pwa-icons/icon-192.png')
            ->badge('/pwa-icons/icon-32.png')
            ->data(['url' => $this->url])
            ->vibrate([200, 100, 200]);
    }
}
