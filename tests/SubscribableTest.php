<?php

use Humweb\Notifications\Database\Stubs\User;
use Humweb\Notifications\Models\NotificationSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->user2 = User::factory()->create();

    config([
        'notification-subscriptions.user_model' => User::class,
        'notification-subscriptions.subscription_model' => NotificationSubscription::class,
        'notification-subscriptions.table_name' => 'notification_subscriptions',
        'notification-subscriptions.notifications' => [
            'comment:created' => [
                'label' => 'Comments',
                'description' => 'Get notified everytime a user comments on one of your posts.',
                'channels' => [
                    ['name' => 'mail', 'label' => 'Email'],
                    ['name' => 'database', 'label' => 'Site Notification'],
                ]
            ],
            'comment:replied' => [
                'label' => 'Comment replies',
                'description' => 'Get notified everytime a user replies to your comments.',
                'channels' => [
                    ['name' => 'mail', 'label' => 'Email'],
                ]
            ],
            'event:new' => [
                'label' => 'New Events',
                'description' => 'Get notified about new events.',
                'channels' => [
                    ['name' => 'mail', 'label' => 'Email'],
                    ['name' => 'sms', 'label' => 'SMS'],
                ]
            ],
        ],
    ]);

    // Initial subscriptions for testing state
    $this->user->subscribe('comment:created', 'mail');
    $this->user->subscribe('comment:created', 'database');
    $this->user->subscribe('comment:replied', 'mail');

    $this->user2->subscribe('comment:created', 'mail');
});

it('allows a user to subscribe to a notification type and channel', function () {
    $user = User::factory()->create();
    expect($user->isSubscribedTo('event:new', 'mail'))->toBeFalse();

    $subscription = $user->subscribe('event:new', 'mail');
    expect($subscription)->toBeInstanceOf(NotificationSubscription::class)
        ->and($subscription->type)->toEqual('event:new')
        ->and($subscription->channel)->toEqual('mail')
        ->and($subscription->user_id)->toEqual($user->id);

    expect($user->isSubscribedTo('event:new', 'mail'))->toBeTrue();
    expect($user->isSubscribedTo('event:new', 'sms'))->toBeFalse(); // Should not be subscribed to other channels for this type yet
});

it('does not create duplicate subscription for the same type and channel', function () {
    expect($this->user->isSubscribedTo('comment:created', 'mail'))->toBeTrue();
    $initialSubscription = $this->user->subscriptions()->where('type', 'comment:created')->where('channel', 'mail')->first();

    $subscription = $this->user->subscribe('comment:created', 'mail'); // Try to subscribe again
    expect($subscription)->toBeInstanceOf(NotificationSubscription::class)
        ->and($subscription->id)->toEqual($initialSubscription->id); 
    
    expect($this->user->subscriptions()->where('type', 'comment:created')->where('channel', 'mail')->count())->toEqual(1);
});

it('can check if user is subscribed to specific type and channel', function () {
    expect($this->user->isSubscribedTo('comment:created', 'mail'))->toBeTrue();
    expect($this->user->isSubscribedTo('comment:created', 'database'))->toBeTrue();
    expect($this->user->isSubscribedTo('comment:replied', 'mail'))->toBeTrue();
    expect($this->user->isSubscribedTo('comment:replied', 'database'))->toBeFalse();
    expect($this->user->isSubscribedTo('event:new', 'mail'))->toBeFalse(); 

    expect($this->user2->isSubscribedTo('comment:created', 'mail'))->toBeTrue();
    expect($this->user2->isSubscribedTo('comment:created', 'database'))->toBeFalse();
});

it('can retrieve all user subscriptions (checks count)', function () {
    // User 1 is subscribed to: comment:created (mail, database), comment:replied (mail) = 3 subscriptions
    $subscriptions = $this->user->subscriptions;
    expect($subscriptions)->toHaveCount(3);
});

it('can get subscribed channels for a type', function() {
    $subscribedChannels = $this->user->getSubscribedChannels('comment:created');
    expect($subscribedChannels)->toBeInstanceOf(Collection::class)
        ->toHaveCount(2)
        ->toContain('mail', 'database')
        ->notToContain('sms');

    $otherSubscribedChannels = $this->user->getSubscribedChannels('comment:replied');
    expect($otherSubscribedChannels)->toHaveCount(1)
        ->toContain('mail');
        
    $noSubscribedChannels = $this->user->getSubscribedChannels('event:new');
    expect($noSubscribedChannels)->toBeEmpty();
});

it('can unsubscribe user from a specific type and channel', function () {
    expect($this->user->isSubscribedTo('comment:created', 'database'))->toBeTrue();
    
    $result = $this->user->unsubscribe('comment:created', 'database');
    expect($result)->toBeTrue();

    expect($this->user->isSubscribedTo('comment:created', 'database'))->toBeFalse();
    expect($this->user->isSubscribedTo('comment:created', 'mail'))->toBeTrue(); 
    expect($this->user->subscriptions()->count())->toEqual(2);
});

it('unsubscribe returns false if user was not subscribed to type and channel', function () {
    expect($this->user->isSubscribedTo('event:new', 'mail'))->toBeFalse();
    $result = $this->user->unsubscribe('event:new', 'mail');
    expect($result)->toBeFalse(); 
    expect($this->user->isSubscribedTo('event:new', 'mail'))->toBeFalse();
});

it('can unsubscribe user from all channels for a given type', function() {
    expect($this->user->isSubscribedTo('comment:created', 'mail'))->toBeTrue();
    expect($this->user->isSubscribedTo('comment:created', 'database'))->toBeTrue();
    expect($this->user->subscriptions()->where('type', 'comment:created')->count())->toEqual(2);

    $result = $this->user->unsubscribeFromType('comment:created');
    expect($result)->toBeTrue();
    expect($this->user->isSubscribedTo('comment:created', 'mail'))->toBeFalse();
    expect($this->user->isSubscribedTo('comment:created', 'database'))->toBeFalse();
    expect($this->user->subscriptions()->where('type', 'comment:created')->count())->toEqual(0);
    expect($this->user->isSubscribedTo('comment:replied', 'mail'))->toBeTrue(); // Other types unaffected
});

it('can unsubscribe user from all notifications (all types and channels)', function () {
    expect($this->user->subscriptions()->count())->toBeGreaterThan(0);

    $result = $this->user->unsubscribeFromAll();
    expect($result)->toBeTrue();

    expect($this->user->subscriptions()->count())->toEqual(0);
    expect($this->user->isSubscribedTo('comment:created', 'mail'))->toBeFalse();
    expect($this->user->isSubscribedTo('comment:replied', 'mail'))->toBeFalse();
});

it('retrieves correct subscription model class name', function() {
    expect($this->user->getNotificationSubscriptionModel())->toEqual(NotificationSubscription::class);
});

it('subscription belongs to a user', function() {
    $subscription = $this->user->subscriptions()->where('type', 'comment:created')->where('channel', 'mail')->first();
    expect($subscription->user)->toBeInstanceOf(User::class)
        ->and($subscription->user->id)->toEqual($this->user->id);
}); 
