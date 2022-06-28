<?php

use Humweb\Notifications\Database\Stubs\User;
use Humweb\Notifications\Facades\NotificationSubscriptions;
use Humweb\Notifications\Tests\Stubs\NotifyCommentCreated;
use Humweb\Notifications\Tests\Stubs\NotifyCommentReply;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user  = User::factory()->create();
    $this->user2 = User::factory()->create();

    config([
        'subscribable.user_model'    => User::class,
        'subscribable.notifications' => [
            'comment.created' => [
                'label'       => 'Comments',
                'description' => 'Get notified everytime a user comments on one of your posts.',
                'class'       => NotifyCommentCreated::class,
            ],
            'comment.replied' => [
                'label'       => 'Comment replies',
                'description' => 'Get notified everytime a user replies to your comments.',
                'class'       => NotifyCommentReply::class,
            ]
        ]
    ]);
});

it('can subscribe to notifications', function () {
    $count = $this->user->notificationSubscriptions()->count();
    expect($count)->toEqual(0);

    NotificationSubscriptions::subscribe($this->user, 'comment.created');

    $count = $this->user->notificationSubscriptions()->count();
    expect($count)->toEqual(1);
});

it('can unsubscribe from notifications', function () {
    NotificationSubscriptions::subscribe($this->user, 'comment.created');

    $count = $this->user->notificationSubscriptions()->count();
    expect($count)->toEqual(1);

    NotificationSubscriptions::unsubscribe($this->user, 'comment.created');

    $count = $this->user->notificationSubscriptions()->count();
    expect($count)->toEqual(0);
});

it('can unsubscribe from all notifications', function () {
    NotificationSubscriptions::subscribe($this->user, 'comment.created');
    NotificationSubscriptions::subscribe($this->user, 'comment.reply');

    $count = $this->user->notificationSubscriptions()->count();
    expect($count)->toEqual(2);

    NotificationSubscriptions::unsubscribeFromAll($this->user);

    $count = $this->user->notificationSubscriptions()->count();
    expect($count)->toEqual(0);
});

it('can get user model', function () {
    expect(NotificationSubscriptions::getUserModel())->toEqual(User::class);
});

it('can get user label', function () {
    expect(NotificationSubscriptions::getUserLabel($this->user))->toEqual($this->user->email);
});

it('can get subscribable notifications list', function () {
    $subscribables = NotificationSubscriptions::getSubscribables();
    expect($subscribables['comment.created'])->toBeArray();
    expect($subscribables['comment.replied'])->toBeArray();
});
