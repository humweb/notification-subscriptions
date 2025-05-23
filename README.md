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

### Subscribing a User

```php
$user = User::find(1);

// Subscribe to a single notification type
$user->subscribe('app:updates');

// The subscribe method returns the NotificationSubscription model instance or null if already subscribed
$subscription = $user->subscribe('newsletter:marketing');

if ($subscription) {
    // New subscription created
} else {
    // User was already subscribed to 'newsletter:marketing'
}
```

### Unsubscribing a User

```php
$user = User::find(1);

// Unsubscribe from a single notification type
$user->unsubscribe('app:updates');

// The unsubscribe method returns true if successful, false otherwise.
```

### Checking Subscription Status

```php
$user = User::find(1);

if ($user->isSubscribedTo('newsletter:marketing')) {
    // User is subscribed to marketing newsletters
} else {
    // User is not subscribed
}
```

### Unsubscribing from All Notifications

```php
$user = User::find(1);
$user->unsubscribeFromAll();
```

### Retrieving User Subscriptions

You can get all of a user's active subscriptions:

```php
$user = User::find(1);
$subscriptions = $user->subscriptions; // Returns a Collection of NotificationSubscription models

foreach ($subscriptions as $subscription) {
    echo "User is subscribed to: " . $subscription->type;
}
```

### Listing Available Notification Types

You can use the `NotificationSubscriptions` facade or helper to get all defined notification types, which is useful for building a settings page for users.

```php
use Humweb\Notifications\Facades\NotificationSubscriptions; // If you set up a Facade
// Or resolve from the service container:
// $notificationManager = app('notification.subscriptions');

$allTypes = NotificationSubscriptions::getSubscribableNotificationTypes();
/*
Output might be:
[
    'app:updates' => [
        'label' => 'Application Updates',
        'description' => 'Receive notifications about new features and important updates to the application.',
    ],
    'newsletter:marketing' => [
        'label' => 'Marketing Newsletter',
        'description' => 'Get occasional updates about our products, special offers, and news.',
    ],
]
*/

foreach ($allTypes as $type => $details) {
    echo "Type: {$type}, Label: {$details['label']}, Description: {$details['description']}";
    // You can then check if the current user is subscribed to $type
    // if (auth()->user()->isSubscribedTo($type)) { ... }
}
```

## Integrating with Laravel Notifications

This package primarily manages the subscription preferences. When sending a Laravel Notification, you would first check if the user is subscribed to the relevant type before dispatching the notification.

```php
use App\Notifications\NewAppUpdate; // Your actual Laravel Notification class

$user = User::find(1);
$updateInfo = /* ... some data ... */;

// Let's say your NewAppUpdate notification corresponds to the 'app:updates' type
if ($user->isSubscribedTo('app:updates')) {
    $user->notify(new NewAppUpdate($updateInfo));
}
```

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
