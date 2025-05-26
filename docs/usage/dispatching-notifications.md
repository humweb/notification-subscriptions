# Dispatching Notifications

If you've set up your Notification classes with the `DispatchesNotifications` and `ChecksSubscription` traits (recommended, see [Configuration](./../configuration.md#3-prepare-your-notification-classes-optional-but-recommended)):

```php
use App\Notifications\NewComment;

$comment = // ... your comment model ...
$userToNotify = // ... the user who should receive this (if subscribed) ...

// This static dispatch method handles everything:
// - Checks if users are subscribed to 'comment:created'
// - If 'immediate' for a channel, sends via that channel (respecting via() from ChecksSubscription)
// - If 'daily' or 'weekly', stores it in 'pending_notifications' table for the digest command
NewComment::dispatch($comment);
```

The `DispatchesNotifications::dispatch()` method will find all users subscribed to the notification's `subscriptionType()`. For each user:

-   If they have an "immediate" subscription on any channel for this type, the notification will be sent immediately (the `ChecksSubscription::via()` method on your notification will ensure it only uses the specific immediate channels).
-   If they have "daily" or "weekly" subscriptions, the notification details are stored in the `pending_notifications` table. The `notifications:send-digests` command will later process these.

If you are **not** using the `DispatchesNotifications` trait, you'll need to implement this logic yourself:

1.  Identify users to notify.
2.  For each user, check their subscription for the notification type and channel.
3.  If "immediate", send it.
4.  If "digest", store it in `pending_notifications` (see `Humweb\Notifications\Models\PendingNotification` model).
