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
        $notification = new static(...$arguments);

        Notification::send(
            $notification->subscribers(),
            $notification
        );
    }

    public function subscribers()
    {
        return NotificationSubscription::ofType(static::subscriptionType())
            ->when(method_exists($this, 'filter'), function ($query) {
                $this->filter($query);
            })
            ->get()
            ->map(fn (NotificationSubscription $notificationSubscription) => (
                $notificationSubscription->user
            ))
            ->unique();
    }
}
