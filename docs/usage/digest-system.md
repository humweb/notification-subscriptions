# Notification Digest System

This package provides a robust system for delivering notifications in a summarized digest format (e.g., daily or weekly) instead of immediately.

## Core Components

### 1. `pending_notifications` Table

When a notification is triggered for a user who has opted for a digest delivery for that notification type and channel, the notification isn't sent immediately. Instead, its details are stored in the `pending_notifications` table.

This table typically stores:

-   User ID
-   Notification Type
-   Channel
-   The fully qualified class name of the original notification.
-   The data (constructor arguments) required to instantiate the original notification.
-   Timestamps.

### 2. `notifications:send-digests` Artisan Command

The package includes an Artisan command `notifications:send-digests` to process and send due digests. This command should be scheduled to run periodically (e.g., every 5 or 15 minutes) in your `app/Console/Kernel.php` file:

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    // ...
    $schedule->command('notifications:send-digests')->everyFifteenMinutes();
    // ...
}
```

When this command runs, it:

-   Queries the `notification_subscriptions` table for subscriptions that are due for a digest (based on `digest_interval`, `digest_at_time`, `digest_at_day`, and `last_digest_sent_at`).
-   For each due subscription, it fetches the corresponding items from the `pending_notifications` table.
-   If items exist, it instantiates and sends a new digest notification (using the class configured in `digest_notification_class`).
-   Deletes the processed `pending_notifications`.
-   Updates `last_digest_sent_at` on the `NotificationSubscription` record.

### 3. Digest Notification Class (e.g., `UserNotificationDigest`)

You need to create a Laravel Notification class that will be responsible for formatting and sending the actual digest. The class name for this is specified in `config/notification-subscriptions.php` under `digest_notification_class`.

This digest notification class will receive two arguments in its constructor:

1.  `string $channel`: The channel the digest is for (e.g., 'mail', 'database').
2.  `Illuminate\Support\Collection $pendingNotificationsData`: A collection of the pending notification items retrieved from the `pending_notifications` table.

Here is an example structure (also found in [Configuration](./../configuration.md#4-create-a-digest-notification-class)):

```php
// app/Notifications/UserNotificationDigest.php
namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Collection;

class UserNotificationDigest extends Notification implements ShouldQueue
{
    use Queueable;

    public string $channel;
    public Collection $pendingNotificationsData;

    public function __construct(string $channel, Collection $pendingNotificationsData)
    {
        $this->channel = $channel;
        $this->pendingNotificationsData = $pendingNotificationsData;
    }

    public function via($notifiable): array
    {
        // Send the digest via the channel it was originally intended for
        return [$this->channel];
    }

    public function toMail($notifiable): MailMessage
    {
        $mailMessage = (new MailMessage)
            ->subject('Your Notification Digest');

        if ($this->pendingNotificationsData->isEmpty()) {
            $mailMessage->line('You have no new notifications in this digest period.');
            return $mailMessage;
        }

        $mailMessage->line('Here is a summary of your notifications:');

        foreach ($this->pendingNotificationsData as $item) {
            // Customize how each item in the digest is displayed
            // $item['class'] is the original notification class
            // $item['data'] contains the original constructor arguments for that notification
            // $item['created_at'] is when the original notification was triggered
            $mailMessage->line("--- ({$item['created_at']->format('M d, H:i')}) ---");
            $mailMessage->line("Type: {$item['class']}"); // Example
            // You might want to load $item['data'] into the original notification class
            // and call a ->toDigestMail() method on it, or format data directly.
            $dataString = implode(', ', array_map(fn($k, $v) => "$k: " . (is_object($v) || is_array($v) ? json_encode($v) : $v), array_keys($item['data']), $item['data']));
            $mailMessage->line("Details: {$dataString}");
        }
        return $mailMessage;
    }

    public function toArray($notifiable): array
    {
        return [
            'message' => 'You have new notifications in your digest.',
            'count' => $this->pendingNotificationsData->count(),
            'items' => $this->pendingNotificationsData->map(function ($item) {
return [
                    'original_class' => $item['class'],
                    'data' => $item['data'],
                    'triggered_at' => $item['created_at']->toIso8601String(),
                ];
            })->all(),
        ];
    }
}
```

## How it Works Together

1.  A user subscribes to `comment:created` notifications for the `mail` channel with a `daily` digest preference set for `09:00:00`.
2.  An event occurs that triggers a `NewComment` notification (which uses `DispatchesNotifications` trait).
3.  `DispatchesNotifications::dispatch()` checks the user's subscription.
4.  Since it's a `daily` preference, the `NewComment` notification's class name and constructor data are serialized and stored in the `pending_notifications` table for that user, type, and channel.
5.  The scheduled `notifications:send-digests` command runs.
6.  At or after 9:00 AM the next day, the command finds this user's subscription is due for a digest.
7.  It retrieves all pending notification items for this user, type (`comment:created`), and channel (`mail`).
8.  It instantiates `App\Notifications\UserNotificationDigest` (or your configured class) with the `mail` channel and the collection of pending items.
9.  The `UserNotificationDigest` formats these items (e.g., into an email) and sends it.
10. The processed items are deleted from `pending_notifications`.
11. `last_digest_sent_at` is updated for the user's subscription to `comment:created` via `mail`.
