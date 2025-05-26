<?php

use Humweb\Notifications\Database\Stubs\User;
use Humweb\Notifications\Models\NotificationSubscription;
use Humweb\Notifications\Models\PendingNotification;
use Humweb\Notifications\Notifications\UserNotificationDigest;
use Humweb\Notifications\Tests\Stubs\NotifyCommentCreated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();

    // Base Config
    Config::set('notification-subscriptions.user_model', User::class);
    Config::set('notification-subscriptions.subscription_model', NotificationSubscription::class);
    Config::set('notification-subscriptions.digest_notification_class', UserNotificationDigest::class);
    Config::set('notification-subscriptions.notifications', [
        'test:event' => [
            'label' => 'Test Event',
            'class' => NotifyCommentCreated::class, // Using a generic one for data structure
            'channels' => [
                ['name' => 'mail', 'label' => 'Email'],
                ['name' => 'database', 'label' => 'Site'],
            ],
        ],
    ]);

    $this->user = User::factory()->create();
});

it('sends no digests if none are due', function () {
    Carbon::setTestNow(Carbon::parse('2023-01-01 08:00:00'));
    $this->user->subscribe('test:event', 'mail', 'daily', '09:00:00');

    $this->artisan('notifications:send-digests')
        ->expectsOutputToContain('No digests due at this time.')
        ->assertExitCode(0);
    Notification::assertNothingSent();
});

it('sends daily digest when due', function () {
    $this->user->subscribe('test:event', 'mail', 'daily', '09:00:00');
    PendingNotification::create([
        'user_id' => $this->user->id,
        'notification_type' => 'test:event',
        'channel' => 'mail',
        'notification_class' => NotifyCommentCreated::class,
        'notification_data' => ['id' => 1, 'content' => 'Test content 1'],
        'created_at' => Carbon::parse('2023-01-01 08:30:00'),
    ]);

    Carbon::setTestNow(Carbon::parse('2023-01-01 09:00:01'));

    $this->artisan('notifications:send-digests')->assertExitCode(0);

    Notification::assertSentTo($this->user, UserNotificationDigest::class, function ($notification) {
        expect($notification->channel)->toEqual('mail');
        expect($notification->pendingNotificationsData)->toHaveCount(1);
        expect($notification->pendingNotificationsData->first()['data']['content'])->toEqual('Test content 1');

        return true;
    });

    $this->assertDatabaseMissing('pending_notifications', ['user_id' => $this->user->id, 'notification_type' => 'test:event']);
    $subscription = $this->user->getSubscriptionDetails('test:event', 'mail');
    expect($subscription->last_digest_sent_at->format('Y-m-d H:i:s'))->toEqual('2023-01-01 09:00:01');
});

it('sends weekly digest when due', function () {
    $this->user->subscribe('test:event', 'database', 'weekly', '10:00:00', 'wednesday');
    PendingNotification::create([
        'user_id' => $this->user->id,
        'notification_type' => 'test:event',
        'channel' => 'database',
        'notification_class' => NotifyCommentCreated::class,
        'notification_data' => ['id' => 2, 'content' => 'Weekly test'],
        'created_at' => Carbon::parse('2023-01-03 10:00:00'), // A Tuesday
    ]);

    // Set time to Wednesday 10:00 AM
    Carbon::setTestNow(Carbon::parse('2023-01-04 10:00:00')); // This is a Wednesday

    $this->artisan('notifications:send-digests')->assertExitCode(0);

    Notification::assertSentTo($this->user, UserNotificationDigest::class, function ($notification) {
        expect($notification->channel)->toEqual('database');
        expect($notification->pendingNotificationsData)->toHaveCount(1);

        return true;
    });
    $this->assertDatabaseMissing('pending_notifications', ['user_id' => $this->user->id, 'notification_type' => 'test:event']);
    $subscription = $this->user->getSubscriptionDetails('test:event', 'database');
    expect($subscription->last_digest_sent_at->format('Y-m-d H:i:s'))->toEqual('2023-01-04 10:00:00');
});

it('does not send daily digest if time has not passed', function () {
    $this->user->subscribe('test:event', 'mail', 'daily', '09:00:00');
    PendingNotification::factory()->create(['user_id' => $this->user->id, 'notification_type' => 'test:event', 'channel' => 'mail']);
    Carbon::setTestNow(Carbon::parse('2023-01-01 08:59:59'));
    $this->artisan('notifications:send-digests');
    Notification::assertNothingSent();
});

it('does not send daily digest if already sent today', function () {
    $this->user->subscribe('test:event', 'mail', 'daily', '09:00:00', null, null)
        ->update(['last_digest_sent_at' => Carbon::parse('2023-01-01 09:05:00')]);
    PendingNotification::factory()->create(['user_id' => $this->user->id, 'notification_type' => 'test:event', 'channel' => 'mail']);
    Carbon::setTestNow(Carbon::parse('2023-01-01 10:00:00'));
    $this->artisan('notifications:send-digests');
    Notification::assertNothingSent();
});

it('updates last_digest_sent_at even if no pending items for a due subscription', function () {
    $this->user->subscribe('test:event', 'mail', 'daily', '09:00:00');
    // No pending notifications created
    Carbon::setTestNow(Carbon::parse('2023-01-01 09:00:01'));
    $this->artisan('notifications:send-digests')->assertExitCode(0);
    Notification::assertNothingSent();
    $subscription = $this->user->getSubscriptionDetails('test:event', 'mail');
    expect($subscription->last_digest_sent_at->format('Y-m-d H:i:s'))->toEqual('2023-01-01 09:00:01');
});

afterEach(function () {
    Carbon::setTestNow(); // Reset Carbon mock
});
