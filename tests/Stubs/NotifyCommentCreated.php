<?php

namespace Humweb\Notifications\Tests\Stubs;

use Humweb\Notifications\Contracts\SubscribableNotification;
use Humweb\Notifications\Traits\ChecksSubscription;
use Humweb\Notifications\Traits\DispatchesNotifications;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NotifyCommentCreated extends Notification implements SubscribableNotification
{
    use Queueable;
    use DispatchesNotifications;
    use ChecksSubscription;

    public $comment;

    /**
     * @param $comment
     */
    public function __construct($comment)
    {
        $this->comment = $comment;
    }

    public static function subscriptionType(): string
    {
        return 'comment:created';
    }
}
