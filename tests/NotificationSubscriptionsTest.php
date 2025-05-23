<?php

use Humweb\Notifications\Database\Stubs\User;
use Humweb\Notifications\Facades\NotificationSubscriptions;
use Humweb\Notifications\Tests\Stubs\NotifyCommentCreated;
use Humweb\Notifications\Tests\Stubs\NotifyCommentReply;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();

    Config::set('notification-subscriptions.user_model', User::class);
    Config::set('notification-subscriptions.notifications', [
        'comment:created' => [
            'label' => 'Comments',
            'description' => 'Get notified everytime a user comments on one of your posts.',
            'class' => NotifyCommentCreated::class,
        ],
        'comment:replied' => [
            'label' => 'Comment replies',
            'description' => 'Get notified everytime a user replies to your comments.',
            'class' => NotifyCommentReply::class,
        ],
        'event:without_class' => [
            'label' => 'Event without class',
            'description' => 'An event that does not have a specific notification class defined in config.',
        ]
    ]);
});

it('can get user model from config', function () {
    expect(NotificationSubscriptions::getUserModel())->toEqual(User::class);
});

it('can get user label', function () {
    expect(NotificationSubscriptions::getUserLabel($this->user))->toEqual($this->user->email);
});

it('can get subscribable notification types list', function () {
    $subscribableTypes = NotificationSubscriptions::getSubscribableNotificationTypes();
    expect($subscribableTypes)->toBeArray()
        ->toHaveKey('comment:created')
        ->toHaveKey('comment:replied')
        ->and($subscribableTypes['comment:created']['label'])->toEqual('Comments')
        ->and($subscribableTypes['comment:created']['class'])->toEqual(NotifyCommentCreated::class);

});

it('can get notification class for a type', function () {
    // @todo: This test mysteriously fails. Config::get("notification-subscriptions.notifications.comment:created.class") returns null
    // when called from within getNotificationClass() in this specific test's execution context,
    // even though the config is set in beforeEach and another test ('it can get subscribable notification types list')
    // successfully reads the same config key when accessing the parent array.
    // Direct instantiation or facade use makes no difference. Skipping for now.
//    $this->markTestSkipped('Investigate elusive config access issue in this specific test.');

     $manager = new \Humweb\Notifications\NotificationSubscriptions();
     $classViaMethod = $manager->getNotificationClass('comment:created');
     expect($classViaMethod)->toEqual(NotifyCommentCreated::class);
});

it('returns null for notification class if not defined', function () {
    $class = NotificationSubscriptions::getNotificationClass('event:without_class');
    expect($class)->toBeNull();
});

it('returns null for notification class for a non-existent type', function () {
    $class = NotificationSubscriptions::getNotificationClass('non:existent:type');
    expect($class)->toBeNull();
});
