<?php

namespace Humweb\Notifications;

class NotificationSubscriptions
{
    /**
     * Get the configured user model class name.
     */
    public function getUserModel(): ?string
    {
        return config('notification-subscriptions.user_model');
    }

    /**
     * Get all defined subscribable notification types from the configuration.
     */
    public function getSubscribableNotificationTypes(): array
    {
        return config('notification-subscriptions.notifications', []);
    }

    /**
     * Get the display label for a given notification type.
     */
    public function getNotificationLabel(string $type, ?string $default = null): ?string
    {
        return config("notification-subscriptions.notifications.{$type}.label", $default);
    }

    /**
     * Get the description for a given notification type.
     */
    public function getNotificationDescription(string $type, ?string $default = null): ?string
    {
        return config("notification-subscriptions.notifications.{$type}.description", $default);
    }

    /**
     * Get the associated Notification class for a given notification type.
     */
    public function getNotificationClass(string $type): ?string
    {
        $notificationTypeConfig = config("notification-subscriptions.notifications.{$type}", null);
        //        dd($notificationTypeConfig,$type, config("notification-subscriptions.notifications.comment.created"));
        if (is_array($notificationTypeConfig)) {
            return $notificationTypeConfig['class'] ?? null;
        }

        return null;
    }

    /**
     * Generates a user-friendly label for a user model instance.
     * It tries to find 'email', 'name', 'last_name', 'first_name', or 'id' in that order.
     *
     * @param  $user  The user model instance.
     * @return string|int|null The most suitable label found, or null.
     */
    public function getUserLabel($user): string|int|null
    {
        if (! $user) {
            return null;
        }

        $possibleAttributes = ['email', 'name', 'last_name', 'first_name', 'id'];

        foreach ($possibleAttributes as $attribute) {
            if (! empty($user->{$attribute})) {
                return $user->{$attribute};
            }
        }

        return null;
    }
}
