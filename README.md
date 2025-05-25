# Notification Subscriptions for Laravel

[//]: # "[![Latest Version on Packagist](https://img.shields.io/packagist/v/humweb/notification-subscriptions.svg?style=flat-square)](https://packagist.org/packages/humweb/notification-subscriptions)"

[![Tests](https://github.com/humweb/notification-subscriptions/actions/workflows/run-tests.yml/badge.svg)](https://github.com/humweb/notification-subscriptions/actions/workflows/run-tests.yml)
[![codecov](https://codecov.io/gh/humweb/notification-subscriptions/graph/badge.svg)](https://codecov.io/gh/humweb/notification-subscriptions)
[![Code Style](https://github.com/humweb/notification-subscriptions/actions/workflows/fix-php-code-style-issues.yml/badge.svg)](https://github.com/humweb/notification-subscriptions/actions/workflows/fix-php-code-style-issues.yml)
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

#### 2. Define Notification Types and Channels

In your `config/notification-subscriptions.php` file, define the types of notifications users can subscribe to. Each type should have a unique key, a human-readable label, a description, and an array of available `channels`. Each channel should have a `name` (its identifier, e.g., 'mail', 'database', 'sms') and a `label` (human-readable, e.g., 'Email', 'Site Notification').

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
            'description' => 'Receive notifications about new features and important updates.',
            'class' => App\Notifications\AppUpdatesNotification::class, // Optional
            'channels' => [
                ['name' => 'mail', 'label' => 'Email'],
                ['name' => 'database', 'label' => 'Site Notification'],
                // Add more channels like ['name' => 'sms', 'label' => 'SMS Text Message']
            ]
        ],
        'newsletter:marketing' => [
            'label' => 'Marketing Newsletter',
            'description' => 'Get occasional updates about our products and special offers.',
            'channels' => [
                ['name' => 'mail', 'label' => 'Email'],
            ]
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

### Channel Configuration

The `channels` array for each notification type defines which delivery methods are available for that specific notification. Common channel names include:

-   `mail` - Email notifications
-   `database` - In-app notifications stored in the database
-   `broadcast` - Real-time notifications via websockets
-   `sms` - SMS text messages (requires additional setup)
-   `slack` - Slack notifications
-   Any custom channel you've implemented

Each channel in the array should have:

-   `name`: The technical identifier that matches Laravel's notification channel name
-   `label`: A human-readable name to display in the UI

The channels you configure here should match the channels your notification classes can actually send to. When using the `ChecksSubscription` trait, only the channels that both:

1. Are configured for the notification type
2. The user has subscribed to

will be used for sending the notification.

### Key Configuration Options

-   **`user_model`**: The class name of your User model.
-   **`subscription_model`**: The Eloquent model for storing subscriptions.
-   **`table_name`**: The database table for subscriptions.
-   **`notifications`**: An array where each key is a unique string identifying a notification type (e.g., `app:updates`).
    -   **`label`**: A human-readable name for the notification, useful for displaying in user settings.
    -   **`description`**: A more detailed explanation of the notification.
    -   **`class`**: (Optional) The fully qualified class name of the Laravel Notification that corresponds to this type. This is useful for reference or if your dispatching logic needs to look up the class based on the type string.
    -   **`channels`**: An array of available delivery channels for this notification type.

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
