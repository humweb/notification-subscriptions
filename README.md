# Notification Subscriptions for Laravel

[//]: # ([![Latest Version on Packagist]&#40;https://img.shields.io/packagist/v/humweb/notification-subscriptions.svg?style=flat-square&#41;]&#40;https://packagist.org/packages/humweb/notification-subscriptions&#41;)
[![Tests](https://github.com/humweb/notification-subscriptions/actions/workflows/run-tests.yml/badge.svg)](https://github.com/humweb/notification-subscriptions/actions/workflows/run-tests.yml)
[![Code Style](https://github.com/humweb/notification-subscriptions/actions/workflows/php-cs-fixer.yml/badge.svg)](https://github.com/humweb/notification-subscriptions/actions/workflows/php-cs-fixer.yml)
[![PHPStan](https://github.com/humweb/notification-subscriptions/actions/workflows/phpstan.yml/badge.svg)](https://github.com/humweb/notification-subscriptions/actions/workflows/phpstan.yml)

[//]: # ([![Total Downloads]&#40;https://img.shields.io/packagist/dt/humweb/notification-subscriptions.svg?style=flat-square&#41;]&#40;https://packagist.org/packages/humweb/notification-subscriptions&#41;)

Notification Subscriptions allows your users to subscribe to certain notifications in your application.

## Installation

You can install the package via composer:

```bash
composer require humweb/notifications
```

You can publish and run the migrations with:

```bash
php artisan vendor:publish --tag="notification-subscriptions-migrations"
php artisan migrate
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="notification-subscriptions-config"
```

## Setup
#### Notification Classes
```php
class NotifyCommentCreated extends Notification implements SubscribableNotification
{
    use Queueable;
    use DispatchesNotifications;


    public $comment;

    /**
     * @param $comment
     */
    public function __construct($comment)
    {
        $this->comment = $comment;
    }

    public static function subscriptionType(): string
    {
        return 'comment.created';
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     *
     * @return array
     */
    public function via($notifiable)
    {
        return [];
    }
}
```
<br />

**Config file example**
```php
return [
    'user_model' => App\Models\User::class,
    'notifications' => [
        'comment.created' => [
            'label'       => 'Comments',
            'description' => 'Get notified everytime a user comments on one of your posts.',
            'class'       => NotifyCommentCreated::class,
        ],
    ],
];

```
<br />

## Usage
You will need to add `DispatchesNotifications` trait,
and implement `SubscribableNotification` to your `Notifications` classes.

#### Subscribe
```php
$user = User::find(1);
$user->subscribe('comment.created');

// or

$user->subscribe(new NotifyCommentCreated());
```

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

- [ryun](https://github.com/humweb)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
