<?php

namespace Humweb\Notifications\Traits;

use Humweb\Notifications\Models\NotificationSubscription;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

trait Subscribable
{
    /**
     * Subscribe the user to a given notification type on a specific channel.
     */
    public function subscribe(string $type, string $channel): ?NotificationSubscription
    {
        if ($this->isSubscribedTo($type, $channel)) {
            return $this->subscriptions()->where('type', $type)->where('channel', $channel)->first();
        }

        return $this->subscriptions()->create([
            'type' => $type,
            'channel' => $channel,
        ]);
    }

    /**
     * Unsubscribe the user from a given notification type on a specific channel.
     */
    public function unsubscribe(string $type, string $channel): bool
    {
        return (bool) $this->subscriptions()->where('type', $type)->where('channel', $channel)->delete();
    }

    /**
     * Check if the user is subscribed to a given notification type on a specific channel.
     */
    public function isSubscribedTo(string $type, string $channel): bool
    {
        return $this->subscriptions()->where('type', $type)->where('channel', $channel)->exists();
    }

    /**
     * Get all channel names the user is subscribed to for a given notification type.
     */
    public function getSubscribedChannels(string $type): Collection
    {
        return $this->subscriptions()->where('type', $type)->pluck('channel');
    }

    /**
     * Unsubscribe the user from all channels for a given notification type.
     */
    public function unsubscribeFromType(string $type): bool
    {
        return (bool) $this->subscriptions()->where('type', $type)->delete();
    }

    /**
     * Unsubscribe the user from all notifications (all types and all channels).
     */
    public function unsubscribeFromAll(): bool
    {
        return (bool) $this->subscriptions()->delete();
    }

    /**
     * Get all notification subscriptions for the user.
     */
    public function subscriptions(): HasMany
    {
        $subscriptionModel = $this->getNotificationSubscriptionModel();

        return $this->hasMany($subscriptionModel, 'user_id');
    }

    /**
     * Get the model class name that is used for notification subscriptions.
     */
    public function getNotificationSubscriptionModel(): string
    {
        return config('notification-subscriptions.subscription_model', NotificationSubscription::class);
    }
}
