<?php

namespace Humweb\Notifications\Traits;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

trait ChecksSubscription
{
    abstract public static function subscriptionType(): string;

    /**
     * Get the notification's delivery channels, filtered by user's immediate subscriptions.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        $subscriptionType = static::subscriptionType();
        $availableChannels = $this->getNotificationChannelsFromConfig($subscriptionType);
        $subscribedChannels = [];

        if (! method_exists($notifiable, 'getSubscriptionDetails')) {
            // If the notifiable doesn't use the Subscribable trait (or similar)
            // then it cannot have digest preferences. Fallback to all available channels for type.
            // Or, decide to send nothing if strict subscription is required.
            // For now, let's assume if it can't get details, it shouldn't receive through this system.
            Log::warning("[ChecksSubscription] Notifiable ID: {$notifiable->id} does not use Subscribable trait for notification type: {$subscriptionType}. No channels returned.");

            return [];
        }

        foreach ($availableChannels as $channelConfig) {
            $channelName = $channelConfig['name'];
            $subscriptionDetails = $notifiable->getSubscriptionDetails($subscriptionType, $channelName);

            if ($subscriptionDetails && $subscriptionDetails->digest_interval === 'immediate') {
                $subscribedChannels[] = $channelName;
            }
        }

        //        Log::info("[ChecksSubscription] via() for Notifiable ID: {$notifiable->id}, Type: {$subscriptionType}, Determined Channels: " . implode(', ', $subscribedChannels));
        return $subscribedChannels;
    }

    protected function getNotificationChannelsFromConfig(string $type): array
    {
        // Check if notification type is configured
        $notificationsConfig = config('notification-subscriptions.notifications', []);
        if (! isset($notificationsConfig[$type])) {
            return []; // Type not configured, so no channels.
        }

        // Get all configured channels for this notification type
        $channels = $notificationsConfig[$type]['channels'] ?? [];

        if (empty($channels)) {
            return []; // No channels configured for this type.
        }

        // Ensure it's an array of arrays (channel configurations)
        if (! is_array(Arr::first($channels))) {
            // This might indicate a misconfiguration, but for robustness, handle it.
            // Or throw an exception / log an error.
            // For now, if it's not an array of arrays, assume misconfiguration and return empty.
            Log::error("[ChecksSubscription] Misconfiguration: 'channels' for type '{$type}' is not an array of arrays.");

            return [];
        }

        return $channels; // Return the array of channel configuration arrays
    }
}
