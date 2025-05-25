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
use Illuminate\Database\Eloquent\Builder;

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
        ]
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
    $data = (object)['id' => 1];
    $notification = new NotifyWithFilter($data); // NotifyWithFilter has a filter for admin users
    $subscribers = $notification->subscribers();

    expect($subscribers)->toHaveCount(1);
    expect($subscribers->first()->id)->toBe($this->adminUser->id);
    expect($subscribers->pluck('id'))->not->toContain($this->user1->id);
});

it('trait dispatch method sends to filtered subscribers', function () {
    $data = (object)['id' => 1];
    NotifyWithFilter::dispatch($data); // NotifyWithFilter has a filter for admin users

    Notification::assertSentTo($this->adminUser, NotifyWithFilter::class);
    Notification::assertNotSentTo($this->user1, NotifyWithFilter::class);
});

// Stub notification class that uses the DispatchesNotifications trait and has a filter method
// This needs to be defined outside the test case, or in a separate stub file if preferred.
// For simplicity here, let's assume it can be defined globally or autoloaded if in a real scenario.
// However, PHPUnit/Pest tests usually run in a way that top-level classes defined in test files are fine.

// Attempting to create a stub here, but it might be better in tests/Stubs/
// For now, let's assume tests/Stubs/NotifyWithFilter.php exists and is structured like:
/*
namespace Humweb\Notifications\Tests\Stubs;

use Humweb\Notifications\Contracts\SubscribableNotification;
use Humweb\Notifications\Traits\DispatchesNotifications;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;

class NotifyWithFilter extends Notification implements SubscribableNotification
{
    use Queueable, DispatchesNotifications;

    public $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public static function subscriptionType(): string
    {
        return 'filtered:notification';
    }

    public function filter(Builder $query)
    {
        // Example filter: only users who are admins
        // Assumes User model has an 'is_admin' attribute
        $query->whereHas('user', function (Builder $userQuery) {
            $userQuery->where('is_admin', true);
        });
    }

    // Dummy via method, as DispatchesNotifications handles subscribers,
    // but ChecksSubscription trait (if also used) would use this.
    // For a notification that *only* uses DispatchesNotifications, this might not be strictly needed
    // unless Laravel's Notification::send internally requires it.
    public function via($notifiable): array
    {
        return ['mail']; // Or derive from config/user preference
    }
}
*/ 
