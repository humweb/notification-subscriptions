<?php

namespace Humweb\Notifications\Traits;

use Humweb\Notifications\Models\NotificationSubscription;
use Humweb\Notifications\Models\PendingNotification;
use Illuminate\Support\Facades\Notification as LaravelNotification;
use Illuminate\Support\Facades\Log;

trait DispatchesNotifications
{
    /**
     * Dispatch the notification to subscribers, respecting digest preferences.
     *
     * @return void
     */
    public static function dispatch(...$arguments)
    {
        Log::info('[DispatchesNotifications] dispatch called for ' . static::class);
        $notificationInstance = new static(...$arguments);
        $notificationType = static::subscriptionType();
        $notificationClass = get_class($notificationInstance);

        // Get all users who have any kind of subscription to this notification type.
        // The subscribers() method applies the notification's own filter() if it exists.
        $potentialRecipients = $notificationInstance->subscribers();
        $subscribers = $potentialRecipients;
        Log::info('[DispatchesNotifications] Subscribers for type ' . $notificationType . ': ' . $subscribers->pluck('id')->implode(', '));

        $immediateRecipientsByChannel = [];
        $uniqueUsersSentImmediate = collect();

        foreach ($subscribers as $recipient) {
            if (! $recipient || ! $recipient->id) {
                continue;
            }

            Log::info('[DispatchesNotifications] Processing user ID: ' . $recipient->id . ' for type ' . $notificationType);
            // Fetch all subscriptions this user has for this specific notification type
            $subscriptions = NotificationSubscription::where('user_id', $recipient->id)
                ->where('type', $notificationType)
                ->get();
            Log::info('[DispatchesNotifications] User ID: ' . $recipient->id . ' has ' . $subscriptions->count() . ' subscriptions for this type.');

            if ($subscriptions->isEmpty()) {
                // This might happen if subscribers() had a broader filter that didn't perfectly align
                // with concrete subscription records, or if a user was unsubscribed between calls.
                continue;
            }

            foreach ($subscriptions as $subscription) {
                Log::info('[DispatchesNotifications] Checking subscription ID: ' . $subscription->id . ' for User ID: ' . $recipient->id . ' - Channel: ' . $subscription->channel . ', Interval: ' . $subscription->digest_interval);
                if ($subscription->digest_interval === 'immediate') {
                    // Collect user for immediate sending, grouped by actual subscribed channel
                    if (!isset($immediateRecipientsByChannel[$subscription->channel])) {
                        $immediateRecipientsByChannel[$subscription->channel] = collect();
                    }
                    // Ensure user is added only once per channel for this batch to $immediateRecipientsByChannel
                    if (!$immediateRecipientsByChannel[$subscription->channel]->contains(fn($u) => $u->id === $recipient->id)) {
                         $immediateRecipientsByChannel[$subscription->channel]->push($recipient);
                         Log::info('[DispatchesNotifications] Added User ID: ' . $recipient->id . ' to immediate send list for channel: ' . $subscription->channel);
                    }
                    // The actual send will happen once at the end for all unique users collected.
                } else {
                    Log::info('[DispatchesNotifications] Storing for DIGEST notification for type ' . $notificationType . ' to User ID: ' . $recipient->id . ' for channel ' . $subscription->channel);
                    PendingNotification::create([
                        'user_id' => $recipient->id,
                        'notification_type' => $notificationType,
                        'channel' => $subscription->channel,
                        'notification_class' => $notificationClass,
                        'notification_data' => $arguments,
                    ]);
                }
            }
        }

        // Consolidate all unique users who need an immediate notification on AT LEAST one channel.
        $allImmediateUsers = collect($immediateRecipientsByChannel)
            ->flatMap(fn($usersCollection) => $usersCollection)
            ->unique(fn($user) => $user->id);

        if ($allImmediateUsers->isNotEmpty()) {
            Log::info('[DispatchesNotifications] Sending immediate notifications to User IDs: ' . $allImmediateUsers->pluck('id')->implode(', ') . ' for notification type: ' . $notificationType);
            LaravelNotification::send($allImmediateUsers, $notificationInstance);
        }
    }

    public function subscribers()
    {
        // This method should ideally return a collection of User models.
        // It is used to get potential recipients before checking their specific digest preferences.
        $query = NotificationSubscription::query()
            ->where('type', static::subscriptionType());

        if (method_exists($this, 'filter')) {
            // The filter method is on the Notification class itself, and it receives the NotificationSubscription query builder.
            $this->filter($query);
        }

        // Get distinct user_ids first, then load users to avoid issues with ->unique() on large datasets if non-standard User model primary keys were used.
        $userIds = $query->distinct()->pluck('user_id');
        
        // Fetch users based on the configured user model.
        $userModelClass = config('notification-subscriptions.user_model', config('auth.providers.users.model'));
        if (!$userModelClass || !class_exists($userModelClass)) {
            // Fallback or error if user model is not properly configured
            return collect(); 
        }
        
        return $userModelClass::whereIn((new $userModelClass)->getKeyName(), $userIds)->get();
    }
}
