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
            'channels' => [
                ['name' => 'mail', 'label' => 'Email'],
                ['name' => 'database', 'label' => 'Site Notification'],
            ],
        ],
        'comment:replied' => [
            'label' => 'Comment replies',
            'description' => 'Get notified everytime a user replies to your comments.',
            'class' => NotifyCommentReply::class,
            'channels' => [
                ['name' => 'mail', 'label' => 'Email'],
            ],
        ],
        'event:without_class' => [
            'label' => 'Event without class',
            'description' => 'An event that does not have a specific notification class defined in config.',
            'channels' => [
                ['name' => 'mail', 'label' => 'Email'],
            ],
        ],
    ]);
});

it('can get user model from config', function () {
    expect(NotificationSubscriptions::getUserModel())->toEqual(User::class);
});

it('can get user label', function () {
    expect(NotificationSubscriptions::getUserLabel($this->user))->toEqual($this->user->email);
});

it('can get subscribable notification types list with channels', function () {
    $subscribableTypes = NotificationSubscriptions::getSubscribableNotificationTypes();
    expect($subscribableTypes)->toBeArray()
        ->toHaveKey('comment:created')
        ->toHaveKey('comment:replied');

    $commentCreatedConfig = $subscribableTypes['comment:created'];
    expect($commentCreatedConfig['label'])->toEqual('Comments');
    expect($commentCreatedConfig['class'])->toEqual(NotifyCommentCreated::class);
    expect($commentCreatedConfig['channels'])->toBeArray()->toHaveCount(2);
    expect($commentCreatedConfig['channels'][0]['name'])->toEqual('mail');
});

it('can get notification class for a type', function () {
    $manager = new \Humweb\Notifications\NotificationSubscriptions;
    $classViaMethod = $manager->getNotificationClass('comment:created');
    expect($classViaMethod)->toEqual(NotifyCommentCreated::class);
});

it('returns null for notification class if not defined for type', function () {
    $class = NotificationSubscriptions::getNotificationClass('event:without_class');
    expect($class)->toBeNull(); // Assuming 'class' key is optional and might not be present
});

it('returns null for notification class for a non-existent type', function () {
    $class = NotificationSubscriptions::getNotificationClass('non:existent:type');
    expect($class)->toBeNull();
});
