# Notification Subscriptions for Laravel

[//]: # "[![Latest Version on Packagist](https://img.shields.io/packagist/v/humweb/notification-subscriptions.svg?style=flat-square)](https://packagist.org/packages/humweb/notification-subscriptions)"

[![Tests](https://github.com/humweb/notification-subscriptions/actions/workflows/run-tests.yml/badge.svg)](https://github.com/humweb/notification-subscriptions/actions/workflows/run-tests.yml)
[![Code Style](https://github.com/humweb/notification-subscriptions/actions/workflows/php-cs-fixer.yml/badge.svg)](https://github.com/humweb/notification-subscriptions/actions/workflows/php-cs-fixer.yml)
[![PHPStan](https://github.com/humweb/notification-subscriptions/actions/workflows/phpstan.yml/badge.svg)](https://github.com/humweb/notification-subscriptions/actions/workflows/phpstan.yml)

[//]: # "[![Total Downloads](https://img.shields.io/packagist/dt/humweb/notification-subscriptions.svg?style=flat-square)](https://packagist.org/packages/humweb/notification-subscriptions)"

Notification Subscriptions allows your users to subscribe to certain notifications in your application.

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

This will create the `notification_subscriptions` table (or the table name you configure).

You can publish the config file with:

```bash
php artisan vendor:publish --tag="notification-subscriptions-config"
```

This will create a `config/notification-subscriptions.php` file.

## Setup

### 1. Prepare Your User Model

Add the `Humweb\Notifications\Traits\Subscribable` trait to your `User` model (or any model you want to make subscribable).

```php
namespace App\Models;

use Humweb\Notifications\Traits\Subscribable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable, Subscribable; // Add Subscribable here

    // ... rest of your User model
}
```

### 2. Configure Notification Types

Open the `config/notification-subscriptions.php` file. This is where you define all the notification types users can subscribe to.

```php
<?php

use Humweb\Notifications\Models\NotificationSubscription;

return [
    'user_model' => App\Models\User::class,
    'subscription_model' => NotificationSubscription::class,
    'table_name' => 'notification_subscriptions',

    'notifications' => [
        'app:updates' => [
            'label' => 'Application Updates',
            'description' => 'Receive notifications about new features and important updates to the application.',
        ],
        'newsletter:marketing' => [
            'label' => 'Marketing Newsletter',
            'description' => 'Get occasional updates about our products, special offers, and news.',
        ],
        // Example: A notification type that might be tied to a specific Laravel Notification class
        'comment:created' => [
            'label'       => 'New Comments on Your Posts',
            'description' => 'Get notified every time a user comments on one of your posts.',
            // You might add your own keys here if needed, e.g., 'notification_class' => App\Notifications\CommentCreated::class
        ],
    ],
];
```

**Key configuration options:**

-   `user_model`: The class name of your User model.
-   `subscription_model`: The Eloquent model for storing subscriptions.
-   `table_name`: The database table for subscriptions.
-   `notifications`: An array where each key is a unique string identifying a notification type (e.g., `app:updates`).
    -   `label`: A human-readable name for the notification, useful for displaying in user settings.
    -   `description`: A more detailed explanation of the notification.
    -   `class`: (Optional) The fully qualified class name of the Laravel Notification that corresponds to this type. This is useful for reference or if your dispatching logic needs to look up the class based on the type string. (e.g., `App\Notifications\CommentCreated::class`).

<br />

## Usage

Once set up, you can manage user subscriptions easily:

### Subscribing to a Notification Type and Channel

To subscribe a user to a specific notification type and channel:

```php
$user->subscribe('news:updates', 'mail');
$user->subscribe('product:offers', 'sms');
```

If the user is already subscribed to that type and channel, this method will not create a duplicate entry.

### Unsubscribing from a Notification Type and Channel

To unsubscribe a user:

```php
$user->unsubscribe('news:updates', 'mail');
```

### Checking Subscription Status for a Type and Channel

To check if a user is subscribed:

```php
if ($user->isSubscribedTo('news:updates', 'mail')) {
    // User is subscribed to news updates via email
}
```

### Getting Subscribed Channels for a Type

To get a collection of all channel names a user is subscribed to for a given notification type:

```php
$subscribedChannels = $user->getSubscribedChannels('app:updates');
// Returns collect(['mail', 'database']) if subscribed to both
```

### Unsubscribing from All Channels for a Type

To unsubscribe a user from all channels for a particular notification type (e.g., stop receiving 'app:updates' on any channel):

```php
$user->unsubscribeFromType('app:updates');
```

### Unsubscribing from All Notifications

To unsubscribe a user from all notification types and all channels:

```php
$user->unsubscribeFromAll();
```

### Listing User's Subscriptions

To get all of a user's notification subscriptions (returns a collection of `NotificationSubscription` models):

```php
$subscriptions = $user->subscriptions; // Eloquent HasMany relation

foreach ($subscriptions as $subscription) {
    echo "Type: " . $subscription->type . ", Channel: " . $subscription->channel;
}
```

### Listing Available Notification Types & Channels

You can retrieve the configured notification types and their available channels from the facade:

```php
use Humweb\Notifications\Facades\NotificationSubscriptions;

$types = NotificationSubscriptions::getSubscribableNotificationTypes();

foreach ($types as $typeKey => $typeDetails) {
    echo "Type: " . $typeDetails['label'];
    echo "Description: " . $typeDetails['description'];
    if (isset($typeDetails['channels'])) {
        echo "Available Channels:";
        foreach ($typeDetails['channels'] as $channel) {
            echo $channel['label'] . " (" . $channel['name'] . ")";
        }
    }
}
```

## Integrating with Laravel Notifications

This package primarily manages the subscription preferences. When sending a Laravel Notification, you would first check if the user is subscribed to the relevant type before dispatching the notification.

### Basic Approach

```php
use App\Notifications\NewAppUpdate; // Your actual Laravel Notification class

$user = User::find(1);
$updateInfo = /* ... some data ... */;

// Let's say your NewAppUpdate notification corresponds to the 'app:updates' type
if ($user->isSubscribedTo('app:updates')) {
    $user->notify(new NewAppUpdate($updateInfo));
}
```

### Using the ChecksSubscription Trait (Recommended)

For a more integrated approach, this package provides the `ChecksSubscription` trait that automatically filters notification channels based on user subscriptions. This means you can use Laravel's standard `notify()` method and the trait will ensure notifications are only sent via channels the user has subscribed to.

```php
namespace App\Notifications;

use Humweb\Notifications\Contracts\SubscribableNotification;
use Humweb\Notifications\Traits\ChecksSubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CommentCreated extends Notification implements SubscribableNotification
{
    use Queueable, ChecksSubscription;

    protected $comment;

    public function __construct($comment)
    {
        $this->comment = $comment;
    }

    public static function subscriptionType(): string
    {
        return 'comment:created'; // Must match your config key
    }

    // The via() method is provided by ChecksSubscription trait
    // It will automatically filter channels based on user's subscriptions
}
```

With this approach:

-   If a user is subscribed to 'comment:created' via 'mail' only, they'll receive emails
-   If subscribed via both 'mail' and 'database', they'll receive both
-   If not subscribed to any channel, they won't receive the notification at all
-   If the notification type is not configured, no notification will be sent

This makes your notifications automatically respect user preferences without additional checks:

```php
// Just use notify() as normal - subscriptions are checked automatically
$user->notify(new CommentCreated($comment));
```

### Custom Subscription Type Method

If your notification class itself needs to know its corresponding subscription type (perhaps for more complex scenarios or if you want to encapsulate the logic), you could define a static method on your Notification class:

```php
// In your App\Notifications\NewAppUpdate.php (example)
public static function getSubscriptionType(): string
{
    return 'app:updates';
}

// Then, when checking:
if ($user->isSubscribedTo(App\Notifications\NewAppUpdate::getSubscriptionType())) {
    $user->notify(new NewAppUpdate($updateInfo));
}
```

This approach keeps the string type DRY if you refer to it in multiple places.

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](https://github.com/spatie/.github/blob/main/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

-   [ryun](https://github.com/humweb)
-   [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## Frontend Management (Optional - Example with Inertia/Vue)

This package provides the backend logic. If you want a UI for users to manage their subscriptions, you'll need to build it in your application. Here's a conceptual example using Inertia.js and Vue.

**1. Controller Methods:**

Create a controller (e.g., `NotificationSubscriptionController`) to handle showing and updating settings:

```php
// app/Http/Controllers/NotificationSubscriptionController.php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Humweb\Notifications\Facades\NotificationSubscriptions as NotificationSubscriptionsManager;
use Illuminate\Validation\Rule;

class NotificationSubscriptionController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $definedTypes = NotificationSubscriptionsManager::getSubscribableNotificationTypes();

        $subscriptionsData = collect($definedTypes)->map(function ($typeDetails, $typeKey) use ($user) {
            $configuredChannels = $typeDetails['channels'] ?? [];

            $channels = collect($configuredChannels)->map(function ($channelConfig) use ($user, $typeKey) {
                return [
                    'name' => $channelConfig['name'],
                    'label' => $channelConfig['label'],
                    'subscribed' => $user ? $user->isSubscribedTo($typeKey, $channelConfig['name']) : false,
                ];
            })->all();

            return [
                'type' => $typeKey,
                'label' => $typeDetails['label'] ?? $typeKey,
                'description' => $typeDetails['description'] ?? '',
                'channels' => $channels,
            ];
        })->values()->all();

        return Inertia::render('Profile/NotificationSettings', [
            'subscriptionsData' => $subscriptionsData,
        ]);
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        $definedTypes = NotificationSubscriptionsManager::getSubscribableNotificationTypes();

        $request->validate([
            'type' => ['required', 'string', Rule::in(array_keys($definedTypes))],
            'channel' => ['required', 'string'], // Add validation for allowed channels for type
            'subscribed' => ['required', 'boolean'],
        ]);

        $type = $request->input('type');
        $channelName = $request->input('channel');
        $subscribed = $request->input('subscribed');

        // Validate channel is valid for the type
        $typeConfig = $definedTypes[$type] ?? null;
        if (!$typeConfig || !collect($typeConfig['channels'] ?? [])->contains('name', $channelName)) {
            return back()->withErrors(['channel' => 'Invalid channel for the notification type.'])->withInput();
        }

        if ($subscribed) {
            $user->subscribe($type, $channelName);
        } else {
            $user->unsubscribe($type, $channelName);
        }

        return back()->with('success', 'Notification settings updated.');
    }
}
```

**2. Routes:**

```php
// routes/web.php
use App\Http\Controllers\NotificationSubscriptionController;

Route::middleware('auth')->group(function () {
    Route::get('/profile/notification-settings', [NotificationSubscriptionController::class, 'index'])
        ->name('profile.notification-settings.index');
    Route::post('/profile/notification-settings', [NotificationSubscriptionController::class, 'store'])
        ->name('profile.notification-settings.store');
});
```

**3. Vue Component (e.g., `NotificationSettings.vue`):**

(The Vue component provided in the prompt `resources/js/Pages/Profile/NotificationSettings.vue` is already up-to-date with channel logic, so it can be referenced here).

Make sure your Vue component correctly iterates through `notificationType.channels` and submits `type`, `channel.name`, and the new `subscribed` state.
