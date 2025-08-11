# Notification Subscriptions for Laravel

[![Tests](https://github.com/humweb/notification-subscriptions/actions/workflows/run-tests.yml/badge.svg)](https://github.com/humweb/notification-subscriptions/actions/workflows/run-tests.yml)
[![codecov](https://codecov.io/gh/humweb/notification-subscriptions/graph/badge.svg)](https://codecov.io/gh/humweb/notification-subscriptions)
[![Code Style](https://github.com/humweb/notification-subscriptions/actions/workflows/fix-php-code-style-issues.yml/badge.svg)](https://github.com/humweb/notification-subscriptions/actions/workflows/fix-php-code-style-issues.yml)
[![PHPStan](https://github.com/humweb/notification-subscriptions/actions/workflows/phpstan.yml/badge.svg)](https://github.com/humweb/notification-subscriptions/actions/workflows/phpstan.yml)

Notification Subscriptions allows your users to subscribe to certain notifications in your application, with support for per-channel preferences and notification digests (daily/weekly summaries).

**For full documentation, please see the [docs/index.md](./docs/index.md) page.**

## Quick Links

-   [Installation](./docs/installation.md)
-   [Configuration](./docs/configuration.md)
-   [Usage](./docs/usage/subscribable-trait.md)
-   [Usage](./docs/usage/subscribable-trait.md)
    -   [Digest System (with structured builder)](./docs/usage/digest-system.md)
-   [License](./docs/license.md)

## Installation

You can install the package via composer:

```bash
composer require humweb/notification-subscriptions
```

You can publish and run the migrations with:

```bash
php artisan vendor:publish --tag="notification-subscriptions-migrations"
php artisan migrate
```

This will create two tables:

-   `notification_subscriptions`: Stores user subscriptions, including channel and digest preferences.
-   `pending_notifications`: Temporarily stores notifications that are scheduled for digest delivery.

You can publish the config file with:

```bash
php artisan vendor:publish --tag="notification-subscriptions-config"
```

This will create a `config/notification-subscriptions.php` file.

## Setup

### 1. Prepare Your User Model

Add the `Humweb\\Notifications\\Traits\\Subscribable` trait to your `User` model (or any model you want to make subscribable).

```php
namespace App\\Models;

use Humweb\\Notifications\\Traits\\Subscribable;
use Illuminate\\Foundation\\Auth\\User as Authenticatable;
use Illuminate\\Notifications\\Notifiable; // Usually already present

class User extends Authenticatable
{
    use Notifiable, Subscribable; // Add Subscribable here

    // ... rest of your User model
}
```

### 2. Configure Notification Types

Open the `config/notification-subscriptions.php` file. This is where you define all the notification types users can subscribe to, their available channels, and digest settings.

```php
<?php

use Humweb\\Notifications\\Models\\NotificationSubscription;
// Define your User model path
// use App\\Models\\User;

return [
    // Typically App\\Models\\User::class
    'user_model' => \\Humweb\\Notifications\\Database\\Stubs\\User::class, // Example, change to your User model
    'subscription_model' => NotificationSubscription::class,
    'table_name' => 'notification_subscriptions',
    'pending_notifications_table_name' => 'pending_notifications', // Table for digest items

    // Default notification class for digests
    'digest_notification_class' => \\Humweb\\Notifications\\Notifications\\UserNotificationDigest::class,

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
            'class' => App\\Notifications\\AppUpdatesNotification::class, // Optional: FQCN of your Laravel Notification
            'channels' => [
                ['name' => 'mail', 'label' => 'Email'],
                ['name' => 'database', 'label' => 'Site Notification'],
            ]
        ],
        'comment:created' => [
            'label' => 'New Comments',
            'description' => 'Get notified about new comments on your content.',
            'class' => App\\Notifications\\NewComment::class, // Example
            'channels' => [
                ['name' => 'mail', 'label' => 'Email'],
                ['name' => 'database', 'label' => 'Site Notification'],
            ]
        ],
        // Add more notification types...
    ],
];
```

#### Key Configuration Options:

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

### 3. Prepare Your Notification Classes (Optional but Recommended)

For seamless integration, especially with digest preferences, your Laravel Notification classes can use two traits provided by this package:

-   `Humweb\\Notifications\\Traits\\DispatchesNotifications`: Adds a static `dispatch()` method to your notification. This method automatically handles checking user subscriptions and either sends the notification immediately or queues it for a digest.
-   `Humweb\\Notifications\\Traits\\ChecksSubscription`: Provides the `via()` method. When a notification is sent (either directly or via the `dispatch()` method from `DispatchesNotifications`), this `via()` method ensures it only goes out through channels the user is _immediately_ subscribed to. Non-immediate preferences are handled by the digest system.

```php
namespace App\\Notifications;

use Humweb\\Notifications\\Contracts\\SubscribableNotification;
use Humweb\\Notifications\\Traits\\ChecksSubscription;
use Humweb\\Notifications\\Traits\\DispatchesNotifications;
use Illuminate\\Bus\\Queueable;
use Illuminate\\Notifications\\Notification;
// use Illuminate\\Contracts\\Queue\\ShouldQueue; // If you want to queue it

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
        return (new \\Illuminate\\Notifications\\Messages\\MailMessage)
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

Implement the `Humweb\\Notifications\\Contracts\\SubscribableNotification` interface, which requires a static `subscriptionType()` method. This method should return the string key that identifies this notification in your `notification-subscriptions.php` config file.

### 4. Create a Digest Notification Class

You need to create a notification class that will be responsible for sending the digest. The class name is specified in `config/notification-subscriptions.php` under `digest_notification_class`.

This class will receive two arguments in its constructor: the channel it's being sent for, and a collection of pending notification data.

```php
// app/Notifications/UserNotificationDigest.php
namespace App\\Notifications;

use Illuminate\\Bus\\Queueable;
use Illuminate\\Notifications\\Notification;
use Illuminate\\Contracts\\Queue\\ShouldQueue;
use Illuminate\\Notifications\\Messages\\MailMessage;
use Illuminate\\Support\\Collection;

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
            $mailMessage->line(\"--- ({$item['created_at']->format('M d, H:i')}) ---\");
            $mailMessage->line(\"Type: {$item['class']}\"); // Example
            // You might want to load $item['data'] into the original notification class
            // and call a ->toDigestMail() method on it, or format data directly.
            $dataString = implode(', ', array_map(fn($k, $v) => \"$k: \" . (is_object($v) || is_array($v) ? json_encode($v) : $v), array_keys($item['data']), $item['data']));
            $mailMessage->line(\"Details: {$dataString}\");
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

### 5. Schedule the Digest Command

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

## Usage

### Managing Subscriptions

The `Subscribable` trait adds several methods to your User model:

#### Subscribing (with Digest Options)

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

#### Unsubscribing from a Type and Channel

```php
$user->unsubscribe('app:updates', 'mail');
```

#### Checking Subscription Status

```php
if ($user->isSubscribedTo('app:updates', 'mail')) {
    // User is subscribed (could be immediate or digest)
}
```

#### Getting Full Subscription Details

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

#### Other Subscription Management Methods

-   `$user->getSubscribedChannels(string $type)`: Get channel names for a type (any digest preference).
-   `$user->unsubscribeFromType(string $type)`: Unsubscribe from all channels/digest settings for a type.
-   `$user->unsubscribeFromAll()`: Unsubscribe from everything.
-   `$user->subscriptions`: Eloquent relation to get all `NotificationSubscription` models.

### Dispatching Notifications

If you've set up your Notification classes with the `DispatchesNotifications` and `ChecksSubscription` traits:

```php
use App\\Notifications\\NewComment;

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

### Listing Available Notification Types & Channels

Retrieve configured types and channels (e.g., for a settings UI):

```php
use Humweb\\Notifications\\Facades\\NotificationSubscriptions;

$types = NotificationSubscriptions::getSubscribableNotificationTypes();
$availableDigestIntervals = NotificationSubscriptions::getDigestIntervals(); // Get configured digest intervals

// $types will be an array like in your config
// $availableDigestIntervals will be like ['immediate' => 'Immediate', ...]
```

## Frontend Example (Vue/Inertia)

Here's an example of how you might build a notification settings page using Vue and Inertia.

**Controller (`NotificationSubscriptionController.php` - example):**

You would typically create a controller to handle fetching settings and updating them.

```php
<?php

namespace App\\Http\\Controllers;

use Illuminate\\Http\\Request;
use Illuminate\\Support\\Facades\\Auth;
use Humweb\\Notifications\\Facades\\NotificationSubscriptions as NotificationSettingsFacade;
use Inertia\\Inertia;

class NotificationSubscriptionController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $notificationTypes = NotificationSettingsFacade::getSubscribableNotificationTypes();
        $availableDigestIntervals = NotificationSettingsFacade::getDigestIntervals();

        $settings = [];
        foreach ($notificationTypes as $typeKey => $typeDetails) {
            $channels = [];
            foreach ($typeDetails['channels'] as $channelConfig) {
                $subscription = $user->getSubscriptionDetails($typeKey, $channelConfig['name']);
                $channels[] = [
                    'name' => $channelConfig['name'],
                    'label' => $channelConfig['label'],
                    'subscribed' => (bool) $subscription,
                    'digest_interval' => $subscription->digest_interval ?? 'immediate',
                    'digest_at_time' => $subscription->digest_at_time ? substr($subscription->digest_at_time, 0, 5) : '09:00', // HH:MM
                    'digest_at_day' => $subscription->digest_at_day ?? 'monday',
                ];
            }
            $settings[$typeKey] = [
                'label' => $typeDetails['label'],
                'description' => $typeDetails['description'],
                'channels' => $channels,
            ];
        }

        return Inertia::render('Profile/NotificationSettings', [
            'notificationSettings' => $settings,
            'availableDigestIntervals' => $availableDigestIntervals,
            // Example days of the week
            'availableDaysOfWeek' => [
                'monday' => 'Monday', 'tuesday' => 'Tuesday', 'wednesday' => 'Wednesday',
                'thursday' => 'Thursday', 'friday' => 'Friday', 'saturday' => 'Saturday', 'sunday' => 'Sunday'
            ],
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'type' => 'required|string',
            'channel' => 'required|string',
            'subscribed' => 'required|boolean',
            'digest_interval' => 'required|string|in:' . implode(',', array_keys(NotificationSettingsFacade::getDigestIntervals())),
            'digest_at_time' => 'nullable|required_if:digest_interval,daily|required_if:digest_interval,weekly|date_format:H:i',
            'digest_at_day' => 'nullable|required_if:digest_interval,weekly|string|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
        ]);

        $user = Auth::user();

        if ($request->subscribed) {
            $user->subscribe(
                $request->type,
                $request->channel,
                $request->digest_interval,
                $request->digest_at_time ? $request->digest_at_time . ':00' : null, // Append seconds
                $request->digest_at_day
            );
        } else {
            $user->unsubscribe($request->type, $request->channel);
        }

        return back()->with('success', 'Notification settings updated.');
    }
}
```

**Vue Component (`resources/js/Pages/Profile/NotificationSettings.vue` - example):**

```html
<template>
    <div>
        <h1>Notification Settings</h1>

        <div
            v-for="(type, typeKey) in notificationSettings"
            :key="typeKey"
            class="mb-8 p-4 border rounded"
        >
            <h2 class="text-xl font-semibold">{{ type.label }}</h2>
            <p class="text-sm text-gray-600 mb-3">{{ type.description }}</p>

            <div
                v-for="channel in type.channels"
                :key="channel.name"
                class="mb-6 p-3 border-l-4 rounded"
            >
                <h3 class="text-lg">
                    {{ channel.label }} ({{ channel.name }})
                </h3>

                <div class="mt-2">
                    <label class="flex items-center">
                        <input
                            type="checkbox"
                            :checked="channel.subscribed"
                            @change="toggleSubscription(typeKey, channel)"
                            class="form-checkbox h-5 w-5 text-blue-600"
                        />
                        <span class="ml-2 text-gray-700">Subscribed</span>
                    </label>
                </div>

                <div v-if="channel.subscribed" class="mt-3 space-y-2 pl-4">
                    <div>
                        <label
                            :for="`interval-${typeKey}-${channel.name}`"
                            class="block text-sm font-medium text-gray-700"
                            >Delivery Preference:</label
                        >
                        <select
                            :id="`interval-${typeKey}-${channel.name}`"
                            v.model="channel.digest_interval"
                            @change="updateSubscription(typeKey, channel)"
                            class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md"
                        >
                            <option
                                v-for="(label, key) in availableDigestIntervals"
                                :key="key"
                                :value="key"
                            >
                                {{ label }}
                            </option>
                        </select>
                    </div>

                    <div
                        v-if="channel.digest_interval === 'daily' || channel.digest_interval === 'weekly'"
                    >
                        <label
                            :for="`time-${typeKey}-${channel.name}`"
                            class="block text-sm font-medium text-gray-700"
                            >Time:</label
                        >
                        <input
                            type="time"
                            :id="`time-${typeKey}-${channel.name}`"
                            v.model="channel.digest_at_time"
                            @change="updateSubscription(typeKey, channel)"
                            class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"
                        />
                    </div>

                    <div v-if="channel.digest_interval === 'weekly'">
                        <label
                            :for="`day-${typeKey}-${channel.name}`"
                            class="block text-sm font-medium text-gray-700"
                            >Day of the Week:</label
                        >
                        <select
                            :id="`day-${typeKey}-${channel.name}`"
                            v.model="channel.digest_at_day"
                            @change="updateSubscription(typeKey, channel)"
                            class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md"
                        >
                            <option
                                v-for="(label, key) in availableDaysOfWeek"
                                :key="key"
                                :value="key"
                            >
                                {{ label }}
                            </option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
    import { useForm } from "@inertiajs/vue3";
    import { watch } from "vue";

    const props = defineProps({
        notificationSettings: Object,
        availableDigestIntervals: Object,
        availableDaysOfWeek: Object,
    });

    // Use a reactive form for settings to easily watch for changes.
    // This is a simplified approach; for complex forms, consider structuring 'form.data' more directly
    // or using multiple forms.
    const form = useForm({
        type: "",
        channel: "",
        subscribed: false,
        digest_interval: "immediate",
        digest_at_time: "09:00",
        digest_at_day: "monday",
    });

    function updateSubscription(typeKey, channel) {
        form.type = typeKey;
        form.channel = channel.name;
        form.subscribed = channel.subscribed; // Assumed to be true if we are updating digest prefs
        form.digest_interval = channel.digest_interval;
        form.digest_at_time = channel.digest_at_time;
        form.digest_at_day = channel.digest_at_day;

        // Normalize time for backend if it's just HH:MM
        let timeToSend = channel.digest_at_time;
        if (timeToSend && timeToSend.length === 5) {
            // HH:MM
            // The backend validation expects H:i, so this should be fine.
            // The controller appends ':00' if needed for subscribe method.
        }

        form.post(route("notifications.subscriptions.store"), {
            // Assuming you have this route
            preserveScroll: true,
            onSuccess: () => {
                // Maybe show a toast
            },
            onError: (errors) => {
                console.error("Error updating subscription:", errors);
                // Revert optimistic updates if necessary or show error messages
            },
        });
    }

    function toggleSubscription(typeKey, channel) {
        channel.subscribed = !channel.subscribed; // Optimistic update

        if (!channel.subscribed) {
            // If unsubscribing, also set digest to immediate as a default
            channel.digest_interval = "immediate";
        }
        updateSubscription(typeKey, channel);
    }
</script>
```

Make sure to define the route `notifications.subscriptions.store` in your `routes/web.php` (or `api.php`) pointing to your controller's `store` method.

## Testing

```bash
composer test
```

or with coverage:

```bash
XDEBUG_MODE=coverage ./vendor/bin/pest --coverage
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](.github/CONTRIBUTING.md) for details.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
