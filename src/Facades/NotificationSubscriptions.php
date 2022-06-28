<?php

namespace Humweb\Notifications\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Humweb\Notifications\NotificationSubscriptions
 */
class NotificationSubscriptions extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'notification.subscriptions';
    }
}
