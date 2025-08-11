# Configuration

## 1. Prepare Your User Model

Add the `Humweb\Notifications\Traits\Subscribable` trait to your `User` model (or any model you want to make subscribable).

```php
namespace App\Models;

use Humweb\Notifications\Traits\Subscribable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable; // Usually already present

class User extends Authenticatable
{
    use Notifiable, Subscribable; // Add Subscribable here

    // ... rest of your User model
}
```

## 2. Configure Notification Types

Open the `config/notification-subscriptions.php` file. This is where you define all the notification types users can subscribe to, their available channels, and digest settings.

```php
<?php

use Humweb\Notifications\Models\NotificationSubscription;
// Define your User model path
// use App\Models\User;

return [
    // Typically App\Models\User::class
    'user_model' => \Humweb\Notifications\Database\Stubs\User::class, // Example, change to your User model
    'subscription_model' => NotificationSubscription::class,
    'table_name' => 'notification_subscriptions',
    'pending_notifications_table_name' => 'pending_notifications', // Table for digest items

    // Default notification class for digests
    'digest_notification_class' => \Humweb\Notifications\Notifications\UserNotificationDigest::class,

    // Digest email subject and markdown view
    'digest_subject' => 'Your Notification Digest',
    'digest_markdown_view' => 'notification-subscriptions::digest',

    // Available digest intervals for users to choose from
    'digest_intervals' => [
        'immediate' => 'Immediate',
        'daily' => 'Daily Digest',
        'weekly' => 'Weekly Digest',
    ],

    'notifications' => [
        'app:updates' => [
            'label' => 'Application Updates',
            'description' => 'Receive notifications about new features and important updates.',
            'class' => App\Notifications\AppUpdatesNotification::class, // Optional: FQCN of your Laravel Notification
            'channels' => [
                ['name' => 'mail', 'label' => 'Email'],
                ['name' => 'database', 'label' => 'Site Notification'],
            ]
        ],
        'comment:created' => [
            'label' => 'New Comments',
            'description' => 'Get notified about new comments on your content.',
            'class' => App\Notifications\NewComment::class, // Example
            'channels' => [
                ['name' => 'mail', 'label' => 'Email'],
                ['name' => 'database', 'label' => 'Site Notification'],
            ]
        ],
        // Add more notification types...
    ],
];
```

### Key Configuration Options:

-   **`user_model`**: The class name of your User model.
-   **`subscription_model`**: The Eloquent model for storing subscriptions (defaults to `Humweb\Notifications\Models\NotificationSubscription`).
-   **`table_name`**: The database table for subscriptions (defaults to `notification_subscriptions`).
-   **`pending_notifications_table_name`**: Database table for storing notifications pending digest (defaults to `pending_notifications`).
-   **`digest_notification_class`**: The default Laravel Notification class to use for sending digests. You should create this class. It will receive the channel and a collection of pending notification data.
-   **`digest_intervals`**: An associative array defining the available digest periods users can select (e.g., `['immediate' => 'Immediate', 'daily' => 'Daily Digest']`). Keys are used internally, values are for display.
-   **`notifications`**: An array where each key is a unique string identifying a notification type (e.g., `app:updates`, `comment:created`).
    -   **`label`**: A human-readable name for the notification.
    -   **`description`**: A more detailed explanation.
    -   **`class`**: (Optional) The FQCN of the corresponding Laravel Notification class. Useful for reference or dynamic dispatch.
    -   **`channels`**: An array of available delivery channels. Each channel item should be an array with:
        -   `name`: The technical identifier (e.g., 'mail', 'database').
        -   `label`: A human-readable name for the UI.

## 3. Prepare Your Notification Classes (Optional but Recommended)

For seamless integration, especially with digest preferences, your Laravel Notification classes can use two traits provided by this package:

-   `Humweb\Notifications\Traits\DispatchesNotifications`: Adds a static `dispatch()` method to your notification. This method automatically handles checking user subscriptions and either sends the notification immediately or queues it for a digest.
-   `Humweb\Notifications\Traits\ChecksSubscription`: Provides the `via()` method. When a notification is sent (either directly or via the `dispatch()` method from `DispatchesNotifications`), this `via()` method ensures it only goes out through channels the user is _immediately_ subscribed to. Non-immediate preferences are handled by the digest system.

```php
namespace App\Notifications;

use Humweb\Notifications\Contracts\SubscribableNotification;
use Humweb\Notifications\Traits\ChecksSubscription;
use Humweb\Notifications\Traits\DispatchesNotifications;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
// use Illuminate\Contracts\Queue\ShouldQueue; // If you want to queue it

class NewComment extends Notification implements SubscribableNotification //, ShouldQueue
{
    use Queueable, DispatchesNotifications, ChecksSubscription;

    public $comment;

    // Your notification constructor
    public function __construct($comment)
    {
        $this->comment = $comment;
    }

    // Required by SubscribableNotification
    // Must match a key in your config/notification-subscriptions.php 'notifications' array
    public static function subscriptionType(): string
    {
        return 'comment:created';
    }

    // Standard Laravel Notification methods
    // The via() method is supplied by ChecksSubscription trait.
    // It will automatically filter channels based on immediate user subscriptions.

    public function toMail($notifiable)
    {
        return (new \Illuminate\Notifications\Messages\MailMessage)
                    ->line('A new comment was added on your post: ' . $this->comment->post_title)
                    ->action('View Comment', url('/posts/' . $this->comment->post_id . '#comment-' . $this->comment->id))
                    ->line('Thank you for using our application!');
    }

    public function toArray($notifiable)
    {
        return [
            'comment_id' => $this->comment->id,
            'comment_body' => $this->comment->body,
            // ... other data
        ];
    }
}
```

Implement the `Humweb\Notifications\Contracts\SubscribableNotification` interface, which requires a static `subscriptionType()` method. This method should return the string key that identifies this notification in your `notification-subscriptions.php` config file.

## 4. Create a Digest Notification Class

You need to create a notification class that will be responsible for sending the digest. The class name is specified in `config/notification-subscriptions.php` under `digest_notification_class`.

This class will receive two arguments in its constructor: the channel it's being sent for, and a collection of pending notification data.

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

## 5. Schedule the Digest Command

The package includes an Artisan command `notifications:send-digests` to process and send due digests. You should schedule this command to run periodically (e.g., every 5 or 15 minutes) in your `app/Console/Kernel.php` file:

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    // ...
    $schedule->command('notifications:send-digests')->everyFifteenMinutes();
    // ...
}
```
