<?php

namespace Humweb\Notifications\Traits;

use Humweb\Notifications\Models\NotificationSubscription;
use Illuminate\Support\Facades\Notification;

trait DispatchesNotifications
{
    /**
     * @param ...$arguments
     *
     * @return void
     */
    public static function dispatch(...$arguments)
    {
        Notification::send(
            static::subscribers(),
            new static(...$arguments)
        );
    }

    public static function subscribers()
    {
        return NotificationSubscription::ofType(static::subscriptionType())
            ->get()
            ->map(fn (NotificationSubscription $notificationSubscription) => (
                $notificationSubscription->user
            ))
            ->unique();
    }
}
