<?php

namespace Humweb\Notifications\Traits;

use Humweb\Notifications\Models\NotificationSubscription;
use Humweb\Notifications\Models\PendingNotification;
use Illuminate\Support\Facades\Notification as LaravelNotification;

trait DispatchesNotifications
{
    /**
     * Dispatch the notification to subscribers, respecting digest preferences.
     *
     * @return void
     */
    public static function dispatch(...$arguments)
    {
        $notificationInstance = new static(...$arguments);
        $notificationType = static::subscriptionType();
        $notificationClass = get_class($notificationInstance);

        // Get all users who have any kind of subscription to this notification type.
        // The subscribers() method applies the notification's own filter() if it exists.
        $potentialRecipients = $notificationInstance->subscribers();

        $immediateRecipientsByChannel = [];

        foreach ($potentialRecipients as $recipient) {
            if (! $recipient || ! $recipient->id) {
                continue;
            }

            // Fetch all subscriptions this user has for this specific notification type
            $subscriptions = NotificationSubscription::where('user_id', $recipient->id)
                ->where('type', $notificationType)
                ->get();

            if ($subscriptions->isEmpty()) {
                // This might happen if subscribers() had a broader filter that didn't perfectly align
                // with concrete subscription records, or if a user was unsubscribed between calls.
                continue;
            }

            foreach ($subscriptions as $subscription) {
                if ($subscription->digest_interval === 'immediate') {
                    // Collect for immediate sending, grouped by channel
                    // The actual sending will respect the notification's via() for that user and channel
                    if (!isset($immediateRecipientsByChannel[$subscription->channel])) {
                        $immediateRecipientsByChannel[$subscription->channel] = collect();
                    }
                    // Ensure user is added only once per channel for this batch
                    if (!$immediateRecipientsByChannel[$subscription->channel]->contains(fn($u) => $u->id === $recipient->id)) {
                         $immediateRecipientsByChannel[$subscription->channel]->push($recipient);
                    }
                } else {
                    // Store for digest
                    PendingNotification::create([
                        'user_id' => $recipient->id,
                        'notification_type' => $notificationType,
                        'channel' => $subscription->channel, // Store the channel this digest is for
                        'notification_class' => $notificationClass,
                        'notification_data' => $arguments, // Store the original constructor arguments
                    ]);
                }
            }
        }

        // Send immediate notifications
        // We need to ensure that LaravelNotification::send respects the specific channels determined here.
        // The default Notification::send($users, $instance) will call $instance->via($user) for each user.
        // If $instance->via($user) (from ChecksSubscription) returns multiple channels, it will send to all.
        // We need to ensure only the intended *immediate* channels are used.

        // A more robust way for immediate sending with specific channels:
        foreach ($immediateRecipientsByChannel as $channel => $recipients) {
            if ($recipients->isNotEmpty()) {
                 // Temporarily override the viaChannels for the notification instance per send batch
                 // This is a bit hacky. A cleaner way might involve a custom SendQueuedNotifications job
                 // or modifying how Notification::send works, or ensuring `via()` can be told which single channel to use.

                // For now, let's assume the notification's via() method, when called by Laravel, 
                // will correctly determine only the channels the user is immediately subscribed to.
                // The `ChecksSubscription::via` method already filters by subscribed channels.
                // So, if a user is in $immediateRecipientsByChannel, it means they have an immediate
                // subscription for that $channel for $notificationType.
                // The default $notificationInstance->via($recipient) should work correctly if it only includes
                // channels with 'immediate' subscriptions based on our logic.
                // Let's make sure `ChecksSubscription::via` considers `digest_interval`

                // Re-thinking: The current `ChecksSubscription::via` doesn't know about digest_interval.
                // We should only pass users to LaravelNotification::send who are meant for immediate, and 
                // `ChecksSubscription::via` should be updated to ONLY return channels that are 'immediate'.

                // Simpler approach: Collect all unique users for immediate send, and let their
                // (soon to be updated) via() method sort out the channels.
            }
        }
        
        // Consolidate all unique users who need an immediate notification on AT LEAST one channel.
        $allImmediateUsers = collect($immediateRecipientsByChannel)
            ->flatMap(fn($usersCollection) => $usersCollection)
            ->unique(fn($user) => $user->id);

        if ($allImmediateUsers->isNotEmpty()) {
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
