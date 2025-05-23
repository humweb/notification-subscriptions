<?php

namespace Humweb\Notifications\Traits;

trait ChecksSubscription
{
    /**
     * Get the notification's delivery channels, filtered by user's subscriptions.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        // Check if notification type is configured
        $notifications = config('notification-subscriptions.notifications', []);
        if (!isset($notifications[static::subscriptionType()])) {
            return [];
        }

        // Get configured channels for this notification type
        $configuredChannels = collect($notifications[static::subscriptionType()]['channels'] ?? [])
            ->pluck('name')
            ->all();

        // Filter channels based on user's subscriptions
        $subscribedChannels = [];
        foreach ($configuredChannels as $channel) {
            if ($notifiable->isSubscribedTo(static::subscriptionType(), $channel)) {
                $subscribedChannels[] = $channel;
            }
        }

        return $subscribedChannels;
    }
} 
