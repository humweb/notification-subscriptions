<?php

namespace Humweb\Notifications;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class NotificationSubscriptionsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('notifications')
            ->hasConfigFile('notification-subscriptions')
            ->hasMigration('create_notification_subscriptions_table');
    }

    public function register()
    {
        parent::register();

        $this->app->singleton('notification.subscriptions', function ($app) {
            return new NotificationSubscriptions();
        });
    }
}
