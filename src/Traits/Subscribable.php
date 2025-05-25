<?php

namespace Humweb\Notifications\Traits;

use Humweb\Notifications\Models\NotificationSubscription;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

trait Subscribable
{
    /**
     * Subscribe the user to a given notification type on a specific channel,
     * optionally with digest preferences.
     *
     * @param string $type The notification type (e.g., 'comment:created').
     * @param string $channel The channel (e.g., 'mail', 'database').
     * @param string $digestInterval Digest interval ('immediate', 'daily', 'weekly'). Defaults to 'immediate'.
     * @param string|null $digestAtTime Time for daily/weekly digests (e.g., '09:00:00').
     * @param string|null $digestAtDay Day for weekly digests (e.g., 'monday').
     * @return Model The NotificationSubscription model instance.
     */
    public function subscribe(string $type, string $channel, string $digestInterval = 'immediate', ?string $digestAtTime = null, ?string $digestAtDay = null): Model
    {
        // Normalize digest_at_day to lowercase if provided
        if ($digestAtDay !== null) {
            $digestAtDay = strtolower($digestAtDay);
        }

        // If interval is immediate, nullify time and day
        if ($digestInterval === 'immediate') {
            $digestAtTime = null;
            $digestAtDay = null;
        } elseif ($digestInterval === 'daily') {
            $digestAtDay = null; // Ensure day is null for daily
        }

        $subscription = $this->subscriptions()
            ->where('type', $type)
            ->where('channel', $channel)
            ->first();

        $subscriptionData = [
            'type' => $type,
            'channel' => $channel,
            'digest_interval' => $digestInterval,
            'digest_at_time' => $digestAtTime,
            'digest_at_day' => $digestAtDay,
        ];

        if ($subscription) {
            // Update existing subscription with new digest preferences
            $subscription->update($subscriptionData);
            return $subscription;
        }

        // Create new subscription
        return $this->subscriptions()->create($subscriptionData);
    }

    /**
     * Unsubscribe the user from a given notification type on a specific channel.
     */
    public function unsubscribe(string $type, string $channel): bool
    {
        return (bool) $this->subscriptions()->where('type', $type)->where('channel', $channel)->delete();
    }

    /**
     * Check if the user is subscribed to a given notification type on a specific channel,
     * regardless of digest preference.
     */
    public function isSubscribedTo(string $type, string $channel): bool
    {
        return $this->subscriptions()->where('type', $type)->where('channel', $channel)->exists();
    }

    /**
     * Get all subscription details (including digest preferences) for a given notification type and channel.
     *
     * @param string $type
     * @param string $channel
     * @return Model|null The NotificationSubscription model or null if not found.
     */
    public function getSubscriptionDetails(string $type, string $channel): ?Model
    {
        return $this->subscriptions()
            ->where('type', $type)
            ->where('channel', $channel)
            ->first();
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
