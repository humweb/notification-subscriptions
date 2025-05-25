<?php

namespace Humweb\Notifications\Tests\Stubs;

use Humweb\Notifications\Contracts\SubscribableNotification;
use Humweb\Notifications\Traits\DispatchesNotifications; // Assuming it might use this for static dispatch
use Humweb\Notifications\Traits\ChecksSubscription; // Assuming it might use this for via()
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NotifyEventNew extends Notification implements SubscribableNotification
{
    use Queueable, ChecksSubscription, DispatchesNotifications;

    public $event;

    public function __construct($event)
    {
        $this->event = $event;
    }

    public static function subscriptionType(): string
    {
        return 'event:new';
    }

    // The via() method will be provided by ChecksSubscription if users are notified directly.
    // If using static dispatch from DispatchesNotifications, the channels are determined by ChecksSubscription
    // on each resolved notifiable.
} 
