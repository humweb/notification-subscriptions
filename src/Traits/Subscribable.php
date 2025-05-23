<?php

namespace Humweb\Notifications\Traits;

use Humweb\Notifications\Models\NotificationSubscription;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

trait Subscribable
{
    /**
     * Subscribe the user to a given notification type on a specific channel.
     *
     * @param  string  $type
     * @param  string  $channel
     * @return NotificationSubscription|null
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
     *
     * @param  string  $type
     * @param  string  $channel
     * @return bool
     */
    public function unsubscribe(string $type, string $channel): bool
    {
        return (bool) $this->subscriptions()->where('type', $type)->where('channel', $channel)->delete();
    }

    /**
     * Check if the user is subscribed to a given notification type on a specific channel.
     *
     * @param  string  $type
     * @param  string  $channel
     * @return bool
     */
    public function isSubscribedTo(string $type, string $channel): bool
    {
        return $this->subscriptions()->where('type', $type)->where('channel', $channel)->exists();
    }
    
    /**
     * Get all channel names the user is subscribed to for a given notification type.
     *
     * @param  string  $type
     * @return \Illuminate\Support\Collection
     */
    public function getSubscribedChannels(string $type): Collection
    {
        return $this->subscriptions()->where('type', $type)->pluck('channel');
    }

    /**
     * Unsubscribe the user from all channels for a given notification type.
     *
     * @param  string  $type
     * @return bool
     */
    public function unsubscribeFromType(string $type): bool
    {
        return (bool) $this->subscriptions()->where('type', $type)->delete();
    }

    /**
     * Unsubscribe the user from all notifications (all types and all channels).
     *
     * @return bool
     */
    public function unsubscribeFromAll(): bool
    {
        return (bool) $this->subscriptions()->delete();
    }

    /**
     * Get all notification subscriptions for the user.
     *
     * @return HasMany
     */
    public function subscriptions(): HasMany
    {
        $subscriptionModel = $this->getNotificationSubscriptionModel();
        return $this->hasMany($subscriptionModel, 'user_id');
    }

    /**
     * Get the model class name that is used for notification subscriptions.
     *
     * @return string
     */
    public function getNotificationSubscriptionModel(): string
    {
        return config('notification-subscriptions.subscription_model', NotificationSubscription::class);
    }
} 
