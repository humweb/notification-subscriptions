<?php

namespace Humweb\Notifications\Tests\Stubs;

use Humweb\Notifications\Contracts\SubscribableNotification;
use Humweb\Notifications\Traits\ChecksSubscription;
use Humweb\Notifications\Traits\DispatchesNotifications;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NotifyCommentReply extends Notification implements SubscribableNotification
{
    use Queueable;
    use DispatchesNotifications;
    use ChecksSubscription;

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
        return 'comment:replied';
    }
}
