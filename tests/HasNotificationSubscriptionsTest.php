<?php

use Humweb\Notifications\Database\Stubs\User;
use Humweb\Notifications\Tests\Stubs\NotifyCommentCreated;
use Humweb\Notifications\Tests\Stubs\NotifyCommentReply;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->user2 = User::factory()->create();

    $this->notification = $this->user->subscribe('comment.created');
    $this->user->subscribe('comment.replied');

    $this->user2->subscribe('comment.created');

    config([
        'subscribable.user_model' => User::class,
        'subscribable.notifications' => [
            'comment.created' => [
                'label' => 'Comments',
                'description' => 'Get notified everytime a user comments on one of your posts.',
                'class' => NotifyCommentCreated::class,
            ],
            'comment.replied' => [
                'label' => 'Comment replies',
                'description' => 'Get notified everytime a user replies to your comments.',
                'class' => NotifyCommentReply::class,
            ],
        ],
    ]);
});

it('can get user subscriptions', function () {
    $subscriptions = $this->user->notificationSubscriptions()->get()->pluck('type');

    expect($this->user->notificationSubscriptionExists('comment.created'))->toBeTrue();
    expect($this->user->notificationSubscriptionExists('comment.replied'))->toBeTrue();
    expect($this->user2->notificationSubscriptionExists('comment.replied'))->toBeFalse();

    expect($subscriptions->contains('comment.created'))->toBeTrue();
    expect($subscriptions->contains('comment.replied'))->toBeTrue();
});


it('can unsubscribe from notifications', function () {
    expect($this->user->notificationSubscriptionExists('comment.created'))->toBeTrue();
    expect($this->user->notificationSubscriptionExists('comment.replied'))->toBeTrue();

    $this->user->unsubscribe('comment.replied');

    expect($this->user->notificationSubscriptionExists('comment.replied'))->toBeFalse();
    expect($this->user->notificationSubscriptionExists('comment.created'))->toBeTrue();
    expect($this->user->notificationSubscriptions()->count())->toEqual(1);
});

it('can unsubscribe from all notifications', function () {
    expect($this->user->notificationSubscriptionExists('comment.created'))->toBeTrue();
    expect($this->user->notificationSubscriptionExists('comment.replied'))->toBeTrue();

    $this->user->unsubscribeFromAll();

    expect($this->user->notificationSubscriptionExists('comment.replied'))->toBeFalse();
    expect($this->user->notificationSubscriptionExists('comment.created'))->toBeFalse();
});


it('can get subscription user', function () {
    expect($this->notification->user->id)->toEqual($this->user->id);
});
