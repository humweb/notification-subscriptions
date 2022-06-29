<?php

namespace Humweb\Notifications\Contracts;

interface SubscribableNotification
{
    public static function dispatch();

    public function subscribers();

    public static function subscriptionType(): string;
}
