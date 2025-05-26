# Installation

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
