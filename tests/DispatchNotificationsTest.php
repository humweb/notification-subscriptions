<?php

use Humweb\Notifications\Database\Stubs\User;
use Humweb\Notifications\Tests\Stubs\NotifyCommentCreated;
use Humweb\Notifications\Tests\Stubs\NotifyCommentReply;
use Humweb\Notifications\Tests\Stubs\NotifyFilteredComment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();

    $this->userSubscribedToCommentCreatedMail = User::factory()->create();
    $this->userSubscribedToCommentCreatedDb = User::factory()->create();
    $this->userSubscribedToCommentReplyMail = User::factory()->create();
    $this->userNotSubscribed = User::factory()->create();

    // Configuration needs to match what the Subscribable trait and NotificationSubscriptionController expect
    Config::set('notification-subscriptions.user_model', User::class);
    Config::set('notification-subscriptions.notifications', [
        'comment:created' => [
            'label' => 'Comments',
            'description' => 'Get notified every time a user comments on one of your posts.',
            'class' => NotifyCommentCreated::class,
            'channels' => [
                ['name' => 'mail', 'label' => 'Email'],
                ['name' => 'database', 'label' => 'Site Notification'],
            ]
        ],
        'comment:replied' => [
            'label' => 'Comment replies',
            'description' => 'Get notified every time a user replies to your comments.',
            'class' => NotifyCommentReply::class,
            'channels' => [
                ['name' => 'mail', 'label' => 'Email'],
            ]
        ],
    ]);

    // Set up subscriptions per channel
    $this->userSubscribedToCommentCreatedMail->subscribe('comment:created', 'mail');
    $this->userSubscribedToCommentCreatedDb->subscribe('comment:created', 'database');
    $this->userSubscribedToCommentReplyMail->subscribe('comment:replied', 'mail');
    // userNotSubscribed has no subscriptions
});

it('dispatches notification to user subscribed to the type and a channel', function () {
    $post = (object)['id' => 1, 'title' => 'Test Post']; // Mock post object
    $comment = (object)['id' => 1, 'user' => $this->userSubscribedToCommentCreatedMail, 'post' => $post]; // Mock comment object

    // User subscribed via mail
    $this->userSubscribedToCommentCreatedMail->notify(new NotifyCommentCreated($comment));
    Notification::assertSentTo($this->userSubscribedToCommentCreatedMail, NotifyCommentCreated::class);

    Notification::assertNothingSentTo($this->userNotSubscribed, NotifyCommentCreated::class);

    // User subscribed via database
    $this->userSubscribedToCommentCreatedDb->notify(new NotifyCommentCreated($comment));
    Notification::assertSentTo($this->userSubscribedToCommentCreatedDb, NotifyCommentCreated::class);
});

it('does not dispatch notification if user is not subscribed to the type on any channel', function () {
    $post = (object)['id' => 1, 'title' => 'Test Post'];
    $comment = (object)['id' => 1, 'user' => $this->userNotSubscribed, 'post' => $post];

    $this->userNotSubscribed->notify(new NotifyCommentCreated($comment));
    Notification::assertNotSentTo($this->userNotSubscribed, NotifyCommentCreated::class);
});

it('dispatches correct notification types to respective subscribed users', function () {
    $post = (object)['id' => 1, 'title' => 'Test Post'];
    $comment = (object)['id' => 1, 'user_id' => 999, 'post' => $post]; // Mock comment object for NotifyCommentCreated
    $reply = (object)['id' => 2, 'user_id' => 998, 'parent_comment_user_id' => $this->userSubscribedToCommentReplyMail->id ]; // Mock reply object for NotifyCommentReply

    // Dispatch CommentCreated notification
    $this->userSubscribedToCommentCreatedMail->notify(new NotifyCommentCreated($comment));
    $this->userSubscribedToCommentReplyMail->notify(new NotifyCommentCreated($comment)); // This user is NOT subscribed to comment:created

    Notification::assertSentTo($this->userSubscribedToCommentCreatedMail, NotifyCommentCreated::class);
    Notification::assertNotSentTo($this->userSubscribedToCommentReplyMail, NotifyCommentCreated::class);

    // Dispatch CommentReply notification
    $this->userSubscribedToCommentReplyMail->notify(new NotifyCommentReply($reply));
    $this->userSubscribedToCommentCreatedMail->notify(new NotifyCommentReply($reply)); // This user is NOT subscribed to comment:replied
    
    Notification::assertSentTo($this->userSubscribedToCommentReplyMail, NotifyCommentReply::class);
    Notification::assertNotSentTo($this->userSubscribedToCommentCreatedMail, NotifyCommentReply::class);
});


it('does not dispatch notification if notification type is not in config', function () {
    // Temporarily remove a notification type from config for this test
    $originalConfig = Config::get('notification-subscriptions.notifications');
    $modifiedConfig = $originalConfig;
    unset($modifiedConfig['comment:created']);
    Config::set('notification-subscriptions.notifications', $modifiedConfig);

    $post = (object)['id' => 1, 'title' => 'Test Post'];
    $comment = (object)['id' => 1, 'user' => $this->userSubscribedToCommentCreatedMail, 'post' => $post];

    $this->userSubscribedToCommentCreatedMail->notify(new NotifyCommentCreated($comment));
    Notification::assertNotSentTo($this->userSubscribedToCommentCreatedMail, NotifyCommentCreated::class);

    // Restore original config
    Config::set('notification-subscriptions.notifications', $originalConfig);
});

// Future test: Once DispatchesNotifications respects specific channels from NotificationSubscription records
// it('dispatches notification only via channels user is subscribed to for that type', function () { ... });
