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
            ->name('notification-subscriptions')
            ->hasConfigFile('notification-subscriptions')
            ->hasMigration('create_notification_subscriptions_table');

        // Load test-specific migrations if in testing environment
        if ($this->app->environment('testing')) {
            // Correct path assuming service provider is in src/
            // and migrations are in database/migrations relative to package root
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }
    }

    public function register()
    {
        parent::register();

        $this->app->singleton('notification.subscriptions', function ($app) {
            return new NotificationSubscriptions;
        });
    }
}
