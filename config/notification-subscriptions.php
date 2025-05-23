<?php

use Humweb\Notifications\Models\NotificationSubscription;
// It's good practice to add App\Notifications\* example classes if they are referenced.
// For a package, you might use placeholder comments or generic examples.
// use App\Notifications\AppUpdatesNotification; 
// use App\Notifications\MarketingNewsletterNotification;

return [
    /*
    |--------------------------------------------------------------------------
    | User Model
    |--------------------------------------------------------------------------
    |
    | When using the Subscribable trait, this specifies the class name of your
    | User model. If commented out or null, the package will try to guess
    | the user model from the default Laravel auth configuration.
    |
    */
    'user_model' => App\Models\User::class, // Example: \App\Models\User::class

    /*
    |--------------------------------------------------------------------------
    | Subscription Model
    |--------------------------------------------------------------------------
    |
    | This is the Eloquent model that will be used to store notification
    | subscriptions. You can override this if you need to customize the model.
    |
    */
    'subscription_model' => NotificationSubscription::class,

    /*
    |--------------------------------------------------------------------------
    | Subscriptions Table Name
    |--------------------------------------------------------------------------
    |
    | This is the name of the database table that will store your notification
    | subscriptions. You can change this if it conflicts with an existing table.
    |
    */
    'table_name' => 'notification_subscriptions',

    /*
    |--------------------------------------------------------------------------
    | Subscribable Notification Types
    |--------------------------------------------------------------------------
    |
    | Here you can define all the notification types that users can subscribe to.
    | Each key is a unique identifier for the notification type (e.g., 'newsletter:weekly').
    | - 'label': A human-readable name for the notification type.
    | - 'description': A short explanation of what the notification is about.
    | - 'class': (Optional) The fully qualified class name of the Laravel Notification 
    |            that corresponds to this type. Useful for reference or if your dispatching
    |            logic needs to look up the class based on the type string.
    | - 'default_subscribed': (Optional) Boolean, true if users should be subscribed by default.
    |
    */
    'notifications' => [
        'app:updates' => [
            'label' => 'Application Updates',
            'description' => 'Receive notifications about new features and important updates to the application.',
            // 'class' => App\Notifications\AppUpdatesNotification::class, // Example
        ],
        'newsletter:marketing' => [
            'label' => 'Marketing Newsletter',
            'description' => 'Get occasional updates about our products, special offers, and news.',
            // 'class' => App\Notifications\MarketingNewsletterNotification::class, // Example
        ],
        /*
        'comment:created' => [
            'label'       => 'New Comments on Your Posts',
            'description' => 'Get notified every time a user comments on one of your posts.',
            'class'       => App\Notifications\CommentCreatedNotification::class, 
        ],
        */
    ],
];
