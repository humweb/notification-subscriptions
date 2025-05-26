# Subscribable Trait Usage

The `Subscribable` trait adds several methods to your User model for managing subscriptions.

## Managing Subscriptions

### Subscribing (with Digest Options)

To subscribe a user to a specific notification type, channel, and optionally specify digest preferences:

```php
$user = Auth::user();

// Subscribe to 'app:updates' via 'mail', receive immediately (default)
$user->subscribe('app:updates', 'mail');

// Subscribe to 'comment:created' via 'database', receive daily at 9:00 AM
$user->subscribe('comment:created', 'database', 'daily', '09:00:00');

// Subscribe to 'newsletter:marketing' via 'mail', receive weekly on Mondays at 8:30 AM
$user->subscribe('newsletter:marketing', 'mail', 'weekly', '08:30:00', 'monday');
```

**Parameters for `subscribe()`:**

1.  `string $type`: The notification type key (e.g., `comment:created`).
2.  `string $channel`: The channel name (e.g., `mail`, `database`).
3.  `string $digestInterval = 'immediate'`: Optional. The digest preference.
    -   `'immediate'`: Send as soon as it occurs.
    -   `'daily'`: Include in a daily digest.
    -   `'weekly'`: Include in a weekly digest.
        (These keys should match those defined in `config('notification-subscriptions.digest_intervals')`).
4.  `?string $digestAtTime = null`: Optional. For 'daily' or 'weekly' digests, the time of day (HH:MM:SS or HH:MM) to send the digest.
5.  `?string $digestAtDay = null`: Optional. For 'weekly' digests, the day of the week (e.g., 'monday', 'tuesday') to send the digest.

If the user is already subscribed to that specific type and channel, their digest preferences will be updated.

### Unsubscribing from a Type and Channel

```php
$user->unsubscribe('app:updates', 'mail');
```

### Checking Subscription Status

```php
if ($user->isSubscribedTo('app:updates', 'mail')) {
    // User is subscribed (could be immediate or digest)
}
```

### Getting Full Subscription Details

To get the full details of a subscription, including digest preferences:

```php
$details = $user->getSubscriptionDetails('comment:created', 'database');

if ($details) {
    echo "Interval: " . $details->digest_interval;    // 'immediate', 'daily', 'weekly'
    echo "Time: " . $details->digest_at_time;       // e.g., '09:00:00' or null
    echo "Day: " . $details->digest_at_day;         // e.g., 'monday' or null
    echo "Last Digest Sent: " . $details->last_digest_sent_at; // Carbon instance or null
}
```

This returns a `NotificationSubscription` model instance or `null`.

### Other Subscription Management Methods

-   `$user->getSubscribedChannels(string $type)`: Get channel names for a type (any digest preference).
-   `$user->unsubscribeFromType(string $type)`: Unsubscribe from all channels/digest settings for a type.
-   `$user->unsubscribeFromAll()`: Unsubscribe from everything.
-   `$user->subscriptions`: Eloquent relation to get all `NotificationSubscription` models.

### Listing Available Notification Types & Channels

Retrieve configured types and channels (e.g., for a settings UI):

```php
use Humweb\Notifications\Facades\NotificationSubscriptions;

$types = NotificationSubscriptions::getSubscribableNotificationTypes();
$availableDigestIntervals = NotificationSubscriptions::getDigestIntervals(); // Get configured digest intervals

// $types will be an array like in your config
// $availableDigestIntervals will be like ['immediate' => 'Immediate', ...]
```
