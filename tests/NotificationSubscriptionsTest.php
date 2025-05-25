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

it('can get notification label for a type', function () {
    $label = NotificationSubscriptions::getNotificationLabel('comment:created');
    expect($label)->toEqual('Comments');

    $defaultLabel = NotificationSubscriptions::getNotificationLabel('non:existent', 'Default');
    expect($defaultLabel)->toEqual('Default');

    $nullLabel = NotificationSubscriptions::getNotificationLabel('non:existent');
    expect($nullLabel)->toBeNull();
});

it('can get notification description for a type', function () {
    $description = NotificationSubscriptions::getNotificationDescription('comment:created');
    expect($description)->toEqual('Get notified everytime a user comments on one of your posts.');

    $defaultDescription = NotificationSubscriptions::getNotificationDescription('non:existent', 'Default Desc');
    expect($defaultDescription)->toEqual('Default Desc');

    $nullDescription = NotificationSubscriptions::getNotificationDescription('non:existent');
    expect($nullDescription)->toBeNull();
});

it('can get user label with different attributes', function () {
    $userWithEmail = User::factory()->make(['email' => 'test@example.com', 'name' => null, 'first_name' => null, 'last_name' => null, 'id' => 1]);
    expect(NotificationSubscriptions::getUserLabel($userWithEmail))->toEqual('test@example.com');

    $userWithName = User::factory()->make(['email' => null, 'name' => 'Test User', 'first_name' => null, 'last_name' => null, 'id' => 2]);
    expect(NotificationSubscriptions::getUserLabel($userWithName))->toEqual('Test User');

    $userWithLastName = User::factory()->make(['email' => null, 'name' => null, 'first_name' => 'Test', 'last_name' => 'UserLastName', 'id' => 3]);
    expect(NotificationSubscriptions::getUserLabel($userWithLastName))->toEqual('UserLastName'); // Prefers last_name if name is null

    $userWithFirstName = User::factory()->make(['email' => null, 'name' => null, 'first_name' => 'TestFirst', 'last_name' => null, 'id' => 4]);
    expect(NotificationSubscriptions::getUserLabel($userWithFirstName))->toEqual('TestFirst');

    $userWithId = User::factory()->make(['email' => null, 'name' => null, 'first_name' => null, 'last_name' => null, 'id' => 5]);
    expect(NotificationSubscriptions::getUserLabel($userWithId))->toEqual(5);

    $userWithAll = User::factory()->make(['email' => 'all@example.com', 'name' => 'All User', 'first_name' => 'First', 'last_name' => 'Last', 'id' => 6]);
    expect(NotificationSubscriptions::getUserLabel($userWithAll))->toEqual('all@example.com'); // Email has priority

    expect(NotificationSubscriptions::getUserLabel(null))->toBeNull();
    expect(NotificationSubscriptions::getUserLabel((object) ['foo' => 'bar']))->toBeNull();
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

    $class = NotificationSubscriptions::getNotificationClass('nonexistant');
    expect($class)->toBeNull(); // Assuming 'class' key is optional and might not be present
});
