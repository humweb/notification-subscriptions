<?php

namespace Humweb\Notifications\Tests\Stubs;

use Humweb\Notifications\Contracts\SubscribableNotification;
use Humweb\Notifications\Traits\DispatchesNotifications;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NotifyFilteredComment extends Notification implements SubscribableNotification
{
    use Queueable;
    use DispatchesNotifications;

    public $reply;

    /**
     * @param $reply
     */
    public function __construct($reply)
    {
        $this->reply = $reply;
    }

    public static function subscriptionType(): string
    {
        return 'comment:filtered';
    }

    public function filter($query)
    {
        $query->where('user_id', $this->reply['user_id']);
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     *
     * @return array
     */
    public function via($notifiable)
    {
        return ['mail'];
    }
}
