<?php

namespace Humweb\Notifications\Database\Factories;

use Humweb\Notifications\Models\NotificationSubscription;
use Illuminate\Database\Eloquent\Factories\Factory;

class NotificationSubscriptionFactory extends Factory
{
    protected $model = NotificationSubscription::class;

    public function definition()
    {
        return [
            'type' => $this->faker->domainWord,
            'user_id' => 1,
        ];
    }
}
