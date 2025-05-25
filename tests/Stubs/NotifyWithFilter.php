<?php

namespace Humweb\Notifications\Tests\Stubs;

use Humweb\Notifications\Contracts\SubscribableNotification;
use Humweb\Notifications\Traits\DispatchesNotifications;
use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\Notification;

class NotifyWithFilter extends Notification implements SubscribableNotification
{
    use DispatchesNotifications, Queueable;

    public $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public static function subscriptionType(): string
    {
        return 'filtered:notification';
    }

    /**
     * Filter the subscribers for this notification.
     *
     * @return void
     */
    public function filter(Builder $query)
    {
        // This filters the NotificationSubscription query.
        // We want to filter by users who are admins.
        // The NotificationSubscription model has a `user` relationship.
        $query->whereHas('user', function (Builder $userQuery) {
            $userQuery->where('is_admin', true);
        });
    }

    /**
     * Get the notification's delivery channels.
     *
     * This is required by Laravel even if DispatchesNotifications is used,
     * as Notification::send will eventually call it on the notification instance for each notifiable.
     * The ChecksSubscription trait would normally provide this and filter based on preferences.
     * Since this stub is specifically for testing DispatchesNotifications' filter, we can keep it simple.
     *
     * @param  mixed  $notifiable
     */
    public function via($notifiable): array
    {
        // For simplicity in this test, we assume 'mail' is always a desired channel if not filtered out by subscription.
        // In a real scenario, ChecksSubscription would handle this more dynamically.
        return ['mail'];
    }
}
