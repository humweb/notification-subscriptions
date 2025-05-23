<?php

namespace Humweb\Notifications\Traits;

use Humweb\Notifications\Models\NotificationSubscription;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait Subscribable
{
    /**
     * Subscribe the user to a given notification type.
     *
     * @param  string  $type
     * @return NotificationSubscription|null
     */
    public function subscribe(string $type): ?NotificationSubscription
    {
        if ($this->isSubscribedTo($type)) {
            return $this->subscriptions()->where('type', $type)->first();
        }

        return $this->subscriptions()->create(['type' => $type]);
    }

    /**
     * Unsubscribe the user from a given notification type.
     *
     * @param  string  $type
     * @return bool
     */
    public function unsubscribe(string $type): bool
    {
        return $this->subscriptions()->where('type', $type)->delete();
    }

    /**
     * Check if the user is subscribed to a given notification type.
     *
     * @param  string  $type
     * @return bool
     */
    public function isSubscribedTo(string $type): bool
    {
        return $this->subscriptions()->where('type', $type)->exists();
    }

    /**
     * Unsubscribe the user from all notifications.
     *
     * @return bool
     */
    public function unsubscribeFromAll(): bool
    {
        return $this->subscriptions()->delete();
    }

    /**
     * Get all notification subscriptions for the user.
     *
     * @return HasMany
     */
    public function subscriptions(): HasMany
    {
        $subscriptionModel = $this->getNotificationSubscriptionModel();
        return $this->hasMany($subscriptionModel, 'user_id'); // Assuming 'user_id' is the foreign key
    }

    /**
     * Get the model that is used for notification subscriptions.
     *
     * @return string
     */
    public function getNotificationSubscriptionModel(): string
    {
        return config('notification-subscriptions.subscription_model', NotificationSubscription::class);
    }
} 
