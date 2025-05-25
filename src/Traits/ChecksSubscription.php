<?php

namespace Humweb\Notifications\Traits;

use Humweb\Notifications\Models\NotificationSubscription;

trait ChecksSubscription
{
    /**
     * Get the notification's delivery channels, filtered by user's immediate subscriptions.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        $notificationType = static::subscriptionType();

        // Check if notification type is configured
        $notificationsConfig = config('notification-subscriptions.notifications', []);
        if (! isset($notificationsConfig[$notificationType])) {
            return []; // Type not configured, so no channels.
        }

        // Get all configured channel names for this notification type
        $configuredChannelNames = collect($notificationsConfig[$notificationType]['channels'] ?? [])
            ->pluck('name')
            ->all();

        if (empty($configuredChannelNames)) {
            return []; // No channels configured for this type.
        }

        // Fetch the user's subscriptions for this specific type that are set to 'immediate'
        $immediateSubscriptions = NotificationSubscription::where('user_id', $notifiable->id)
            ->where('type', $notificationType)
            ->where('digest_interval', 'immediate')
            ->whereIn('channel', $configuredChannelNames) // Only consider channels configured for this notification
            ->pluck('channel')
            ->all();

        return $immediateSubscriptions;
    }
}
