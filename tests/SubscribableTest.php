<?php

use Humweb\Notifications\Database\Stubs\User;
use Humweb\Notifications\Models\NotificationSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->user2 = User::factory()->create(); // Keep for testing separate user contexts

    // Corrected configuration keys
    config([
        'notification-subscriptions.user_model' => User::class,
        'notification-subscriptions.subscription_model' => NotificationSubscription::class,
        'notification-subscriptions.table_name' => 'notification_subscriptions',
        'notification-subscriptions.notifications' => [
            'comment:created' => [
                'label' => 'Comments',
                'description' => 'Get notified everytime a user comments on one of your posts.',
            ],
            'comment:replied' => [
                'label' => 'Comment replies',
                'description' => 'Get notified everytime a user replies to your comments.',
            ],
            'event:new' => [
                'label' => 'New Events',
                'description' => 'Get notified about new events.',
            ],
        ],
    ]);

    // Initial subscriptions for testing state
    $this->user->subscribe('comment:created');
    $this->user->subscribe('comment:replied');

    $this->user2->subscribe('comment:created');
});

it('allows a user to subscribe to a notification type', function () {
    $user = User::factory()->create();
    expect($user->isSubscribedTo('event:new'))->toBeFalse();

    $subscription = $user->subscribe('event:new');
    expect($subscription)->toBeInstanceOf(NotificationSubscription::class)
        ->and($subscription->type)->toEqual('event:new')
        ->and($subscription->user_id)->toEqual($user->id);

    expect($user->isSubscribedTo('event:new'))->toBeTrue();
});

it('does not create duplicate subscription and returns existing one', function () {
    expect($this->user->isSubscribedTo('comment:created'))->toBeTrue();
    $initialSubscription = $this->user->subscriptions()->where('type', 'comment:created')->first();

    $subscription = $this->user->subscribe('comment:created'); // Try to subscribe again
    expect($subscription)->toBeInstanceOf(NotificationSubscription::class)
        ->and($subscription->id)->toEqual($initialSubscription->id); // Should be the same model
    
    expect($this->user->subscriptions()->where('type', 'comment:created')->count())->toEqual(1); // Still only one
});

it('can check if user is subscribed to specific notification types', function () {
    expect($this->user->isSubscribedTo('comment:created'))->toBeTrue();
    expect($this->user->isSubscribedTo('comment:replied'))->toBeTrue();
    expect($this->user->isSubscribedTo('event:new'))->toBeFalse(); // This type wasn't subscribed in beforeEach

    expect($this->user2->isSubscribedTo('comment:created'))->toBeTrue();
    expect($this->user2->isSubscribedTo('comment:replied'))->toBeFalse(); // user2 not subscribed to this
});

it('can retrieve all user subscriptions', function () {
    $subscriptions = $this->user->subscriptions;
    expect($subscriptions)->toHaveCount(2);

    $types = $subscriptions->pluck('type');
    expect($types)->toContain('comment:created', 'comment:replied');
    expect($types->contains('event:new'))->toBeFalse(); // Corrected assertion for not containing
});

it('can unsubscribe user from a specific notification type', function () {
    expect($this->user->isSubscribedTo('comment:replied'))->toBeTrue();
    
    $result = $this->user->unsubscribe('comment:replied');
    expect($result)->toBeTrue();

    expect($this->user->isSubscribedTo('comment:replied'))->toBeFalse();
    expect($this->user->isSubscribedTo('comment:created'))->toBeTrue(); // Should still be subscribed to other types
    expect($this->user->subscriptions()->count())->toEqual(1);
});

it('unsubscribe returns false if user was not subscribed to type', function () {
    expect($this->user->isSubscribedTo('event:new'))->toBeFalse();
    $result = $this->user->unsubscribe('event:new');
    // Eloquent delete returns number of affected rows (0 in this case).
    // The trait method type-hints bool, so 0 becomes false.
    expect($result)->toBeFalse(); 
    expect($this->user->isSubscribedTo('event:new'))->toBeFalse();
});

it('can unsubscribe user from all notifications', function () {
    expect($this->user->subscriptions()->count())->toBeGreaterThan(0);

    $result = $this->user->unsubscribeFromAll();
    expect($result)->toBeTrue();

    expect($this->user->subscriptions()->count())->toEqual(0);
    expect($this->user->isSubscribedTo('comment:created'))->toBeFalse();
    expect($this->user->isSubscribedTo('comment:replied'))->toBeFalse();
});

it('retrieves correct subscription model class name', function() {
    expect($this->user->getNotificationSubscriptionModel())->toEqual(NotificationSubscription::class);
});

it('subscription belongs to a user', function() {
    $subscription = $this->user->subscriptions()->first();
    expect($subscription->user)->toBeInstanceOf(User::class)
        ->and($subscription->user->id)->toEqual($this->user->id);
}); 
