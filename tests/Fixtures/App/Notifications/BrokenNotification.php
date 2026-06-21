<?php

namespace SajjadHossain\Doctor\Tests\Fixtures\App\Notifications;

use Illuminate\Notifications\Notification;

class BrokenNotification extends Notification
{
    public function via(object $notifiable): array
    {
        return ['mail', 'slack'];
    }

    public function toSlack(object $notifiable): array
    {
        return [];
    }
}
