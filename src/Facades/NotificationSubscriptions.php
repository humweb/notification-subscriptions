<?php

namespace Humweb\Notifications\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static array getSubscribableNotificationTypes()
 * @method static string|null getUserModel()
 * @method static string|null getNotificationLabel(string $type, string $default = null)
 * @method static string|null getNotificationDescription(string $type, string $default = null)
 * @method static string|null getNotificationClass(string $type)
 * @method static string|int|null getUserLabel($user)
 *
 * @see \Humweb\Notifications\NotificationSubscriptions
 */
class NotificationSubscriptions extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'notification.subscriptions'; // This must match the binding key in the service provider
    }
}
