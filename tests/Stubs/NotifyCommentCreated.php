<?php

namespace Humweb\Notifications\Tests\Stubs;

use Humweb\Notifications\Contracts\SubscribableNotification;
use Humweb\Notifications\Traits\DispatchesNotifications;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NotifyCommentCreated extends Notification implements SubscribableNotification
{
    use Queueable;
    use DispatchesNotifications;


    public $comment;

    /**
     * @param $comment
     */
    public function __construct($comment)
    {
        $this->comment   = $comment;
    }


    public static function subscriptionType(): string
    {
        return 'comment.created';
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
        return [];
    }

}
