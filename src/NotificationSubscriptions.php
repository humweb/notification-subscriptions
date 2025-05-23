<?php

namespace Humweb\Notifications;

class NotificationSubscriptions
{
    /**
     * @return mixed
     */
    public function subscribe($user, string $type)
    {
        return $user->subscribe($type);
    }

    /**
     * @return mixed
     */
    public function unsubscribe($user, string $type)
    {
        return $user->unsubscribe($type);
    }

    /**
     * @return mixed
     */
    public function unsubscribeFromAll($user)
    {
        return $user->unsubscribeFromAll();
    }

    /**
     * @return string
     */
    public function getUserModel()
    {
        return config('subscribable.user_model');
    }

    /**
     * @return array
     */
    public function getSubscribables()
    {
        return config('subscribable.notifications');
    }

    /**
     * @return string|int
     */
    public function getUserLabel($user)
    {
        return collect([
            data_get($user, 'email'),
            data_get($user, 'name'),
            data_get($user, 'last_name'),
            data_get($user, 'first_name'),
            data_get($user, 'id'),
        ])
            ->filter()
            ->first();
    }
}
