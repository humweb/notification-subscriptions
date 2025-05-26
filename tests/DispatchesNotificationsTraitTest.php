<?php

use Humweb\Notifications\Database\Stubs\User;
use Humweb\Notifications\Models\NotificationSubscription;
use Humweb\Notifications\Tests\Stubs\NotifyCommentCreated;
use Humweb\Notifications\Tests\Stubs\NotifyCommentReply;
use Humweb\Notifications\Tests\Stubs\NotifyEventNew;
use Humweb\Notifications\Tests\Stubs\NotifyWithFilter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();

    // Users
    $this->user1 = User::factory()->create();
    $this->user2 = User::factory()->create();
    $this->user3 = User::factory()->create();
    $this->adminUser = User::factory()->admin()->create(); // Use admin state here

    // Config
    Config::set('notification-subscriptions.user_model', User::class);
    Config::set('notification-subscriptions.subscription_model', NotificationSubscription::class);
    Config::set('notification-subscriptions.notifications', [
        'comment:created' => [
            'label' => 'Comments',
            'class' => NotifyCommentCreated::class,
            'channels' => [
                ['name' => 'mail', 'label' => 'Email'],
                ['name' => 'database', 'label' => 'Site Notification'],
            ],
        ],
        'comment:replied' => [
            'label' => 'Comment replies',
            'class' => NotifyCommentReply::class,
            'channels' => [
                ['name' => 'mail', 'label' => 'Email'],
            ],
        ],
        'event:new' => [
            'label' => 'New Events',
            'class' => NotifyEventNew::class,
            'channels' => [
                ['name' => 'mail', 'label' => 'Email'],
                ['name' => 'sms', 'label' => 'SMS'],
            ],
        ],
        'filtered:notification' => [
            'label' => 'Filtered Notification',
            'class' => NotifyWithFilter::class,
            'channels' => [
                ['name' => 'mail', 'label' => 'Email'],
            ],
        ],
    ]);

    // Subscriptions
    $this->user1->subscribe('comment:created', 'mail');
    $this->user1->subscribe('comment:created', 'database');
    $this->user2->subscribe('comment:created', 'mail');
    $this->user3->subscribe('comment:replied', 'mail');
    $this->adminUser->subscribe('filtered:notification', 'mail');
    $this->user1->subscribe('filtered:notification', 'mail'); // Non-admin user for filter test
});

it('trait subscribers method returns users subscribed to the notification type', function () {
    $comment = (object) ['id' => 1];
    $notification = new NotifyCommentCreated($comment);
    $subscribers = $notification->subscribers();

    expect($subscribers)->toHaveCount(2)
        ->and($subscribers->pluck('id'))->toContain($this->user1->id, $this->user2->id)
        ->and($subscribers->pluck('id'))->not->toContain($this->user3->id);
});

it('trait subscribers method returns unique users even if subscribed to multiple channels of same type', function () {
    // user1 is subscribed to comment:created via mail and database
    $comment = (object) ['id' => 1];
    $notification = new NotifyCommentCreated($comment);
    $subscribers = $notification->subscribers();

    expect($subscribers)->toHaveCount(2); // User1, User2. User1 is not duplicated.
    expect($subscribers->where('id', $this->user1->id)->count())->toBe(1);
});

it('trait subscribers method returns empty collection if no one is subscribed', function () {
    $event = (object) ['id' => 1];
    $notification = new NotifyEventNew($event);
    $subscribers = $notification->subscribers();

    expect($subscribers)->toBeEmpty();
});

it('trait dispatch method sends notification to all subscribed users', function () {
    $comment = (object) ['id' => 1, 'content' => 'Test Comment'];

    NotifyCommentCreated::dispatch($comment);

    Notification::assertSentTo($this->user1, NotifyCommentCreated::class);
    Notification::assertSentTo($this->user2, NotifyCommentCreated::class);
    Notification::assertNotSentTo($this->user3, NotifyCommentCreated::class);
});

it('trait dispatch method passes arguments to notification constructor', function () {
    $commentData = (object) ['id' => 5, 'text' => 'Hello World'];

    NotifyCommentCreated::dispatch($commentData);

    Notification::assertSentTo($this->user1, function (NotifyCommentCreated $notification) use ($commentData) {
        return $notification->comment->id === $commentData->id && $notification->comment->text === $commentData->text;
    });
});

it('trait subscribers method respects filter method on notification class', function () {
    $data = (object) ['id' => 1];
    $notification = new NotifyWithFilter($data); // NotifyWithFilter has a filter for admin users
    $subscribers = $notification->subscribers();

    expect($subscribers)->toHaveCount(1);
    expect($subscribers->first()->id)->toBe($this->adminUser->id);
    expect($subscribers->pluck('id'))->not->toContain($this->user1->id);
});

it('trait dispatch method sends to filtered subscribers', function () {
    $data = (object) ['id' => 1];
    NotifyWithFilter::dispatch($data); // NotifyWithFilter has a filter for admin users

    Notification::assertSentTo($this->adminUser, NotifyWithFilter::class);
    Notification::assertNotSentTo($this->user1, NotifyWithFilter::class);
});

// New tests for digest functionality
it('it stores notification in pending_notifications for daily digest preference', function () {
    $this->user1->subscribe('event:new', 'mail', 'daily', '10:00:00');
    $eventData = (object) ['id' => 100, 'name' => 'Daily Digest Test Event'];

    NotifyEventNew::dispatch($eventData);

    Notification::assertNothingSentTo($this->user1, NotifyEventNew::class);
    $this->assertDatabaseHas('pending_notifications', [
        'user_id' => $this->user1->id,
        'notification_type' => 'event:new',
        'channel' => 'mail',
        'notification_class' => NotifyEventNew::class,
        'notification_data' => json_encode([$eventData]),
    ]);
});

it('it stores notification in pending_notifications for weekly digest preference', function () {
    $this->user2->subscribe('event:new', 'sms', 'weekly', '11:00:00', 'wednesday');
    $eventData = (object) ['id' => 200, 'name' => 'Weekly Digest Test Event'];

    NotifyEventNew::dispatch($eventData);

    Notification::assertNothingSentTo($this->user2, NotifyEventNew::class);
    $this->assertDatabaseHas('pending_notifications', [
        'user_id' => $this->user2->id,
        'notification_type' => 'event:new',
        'channel' => 'sms',
        'notification_class' => NotifyEventNew::class,
        'notification_data' => json_encode([$eventData]),
    ]);
});

it('it sends notification immediately if digest_interval is immediate', function () {
    // user3 is subscribed to 'comment:replied', 'mail' (implicitly immediate)
    $replyData = (object) ['id' => 300, 'content' => 'Immediate Reply'];

    NotifyCommentReply::dispatch($replyData);

    Notification::assertSentTo($this->user3, NotifyCommentReply::class);
    $this->assertDatabaseMissing('pending_notifications', [
        'user_id' => $this->user3->id,
        'notification_type' => 'comment:replied',
    ]);
});

it('ChecksSubscription via method returns only immediate channels', function () {
    $this->user1->subscribe('event:new', 'mail', 'immediate');
    $this->user1->subscribe('event:new', 'database', 'daily', '09:00');
    $this->user1->subscribe('event:new', 'sms', 'weekly', '10:00', 'monday');

    $notificationInstance = new NotifyEventNew((object) ['id' => 1]);
    $viaChannels = $notificationInstance->via($this->user1);

    expect($viaChannels)->toBeArray()
        ->toHaveCount(1)
        ->toContain('mail')
        ->not->toContain('database', 'sms');
});

it('dispatch sends to multiple immediate channels and stores for multiple digest channels correctly', function () {
    // User1: event:new, mail (immediate); event:new, database (daily)
    $this->user1->subscribe('event:new', 'mail', 'immediate');
    $this->user1->subscribe('event:new', 'database', 'daily', '08:00');

    // User2: event:new, mail (weekly); event:new, database (immediate)
    $this->user2->subscribe('event:new', 'mail', 'weekly', '09:00', 'tuesday');
    $this->user2->subscribe('event:new', 'sms', 'immediate');

    $eventData = (object) ['id' => 400, 'name' => 'Mixed Dispatch Test'];
    NotifyEventNew::dispatch($eventData);

    // User1 assertions
    Notification::assertSentTo($this->user1, NotifyEventNew::class, function ($notification, $channels, $notifiable) {
        // The via method on NotifyEventNew (from ChecksSubscription) should restrict it to 'mail' for user1
        return $channels === ['mail'];
    });
    $this->assertDatabaseHas('pending_notifications', [
        'user_id' => $this->user1->id,
        'notification_type' => 'event:new',
        'channel' => 'database',
    ]);

    // User2 assertions
    Notification::assertSentTo($this->user2, NotifyEventNew::class, function ($notification, $channels, $notifiable) {
        // The via method should restrict it to 'sms' for user2
        return $channels === ['sms'];
    });
    $this->assertDatabaseHas('pending_notifications', [
        'user_id' => $this->user2->id,
        'notification_type' => 'event:new',
        'channel' => 'mail',
    ]);
});

it('subscribers method in DispatchesNotifications still returns all potential users', function () {
    // User1 has an immediate sub for comment:created mail
    // User2 has an immediate sub for comment:created mail
    // Let's add a digest subscription for user3 for comment:created mail
    $this->user3->subscribe('comment:created', 'mail', 'daily', '07:00');

    $notification = new NotifyCommentCreated((object) ['id' => 1]);
    $subscribers = $notification->subscribers();

    // Should include user1, user2 (from beforeEach immediate), and user3 (newly added digest)
    expect($subscribers)->toHaveCount(3)
        ->pluck('id')->toContain($this->user1->id, $this->user2->id, $this->user3->id);
});
