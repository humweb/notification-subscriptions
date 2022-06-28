<?php

namespace Humweb\Notifications\Traits;

use Humweb\Notifications\Models\NotificationSubscription;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 *
 */
trait HasNotificationSubscriptions
{
    public function notificationSubscriptions(): HasMany
    {
        return $this->hasMany(NotificationSubscription::class, 'user_id');
    }

    public function notificationSubscriptionExists($type): bool
    {
        return $this->notificationSubscriptions()
            ->ofType($type)
            ->exists();
    }

    public function subscribe($type)
    {
        return $this->notificationSubscriptions()
            ->updateOrCreate(['type' => $type]);
    }

    public function unsubscribe($type)
    {
        return $this->notificationSubscriptions()
            ->where('type', $type)
            ->delete();
    }

    public function unsubscribeFromAll()
    {
        return $this->notificationSubscriptions()->delete();
    }
}
