<?php

use Humweb\Notifications\Database\Stubs\User;
use Humweb\Notifications\Tests\Stubs\NotifyCommentCreated;
use Humweb\Notifications\Tests\Stubs\NotifyCommentReply;
use Humweb\Notifications\Tests\Stubs\NotifyFilteredComment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
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

    $this->user = User::factory()->create();
    $this->user2 = User::factory()->create();

    $this->user->subscribe('comment.created');
    $this->user->subscribe('comment.replied');

    $this->user2->subscribe('comment.created');
});

it('send notifications to subscribers', function () {
    $fake = Notification::fake();

    $comment = [
        'name' => 'foobar',
        'comment' => 'Hello World',
    ];

    NotifyCommentCreated::dispatch($comment);
    Notification::assertSentTo(
        [$this->user, $this->user2],
        NotifyCommentCreated::class,
        function ($notification) use ($comment) {
            $this->assertEquals($comment['comment'], $notification->comment['comment']);

            return $comment['comment'] === $notification->comment['comment'];
        }
    );


    NotifyCommentReply::dispatch($comment);
    Notification::assertNotSentTo($this->user2, NotifyCommentReply::class);
    Notification::assertSentTo($this->user, NotifyCommentReply::class);
});

it('can filter subscribers for certain notifications', function () {
    $fake = Notification::fake();

    $comment = [
        'name' => 'foobar',
        'user_id' => 1,
        'comment' => 'Hello World',
    ];

    $this->user->subscribe('comment.filtered');
    $this->user2->subscribe('comment.filtered');

    NotifyFilteredComment::dispatch($comment);

    Notification::assertSentTo($this->user, NotifyFilteredComment::class);
    Notification::assertNotSentTo($this->user2, NotifyFilteredComment::class);
});
