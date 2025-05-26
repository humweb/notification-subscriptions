<?php

namespace Humweb\Notifications\Database\Factories;

use Humweb\Notifications\Database\Stubs\User;
use Humweb\Notifications\Models\PendingNotification;
use Humweb\Notifications\Tests\Stubs\NotifyCommentCreated; // Example notification
use Illuminate\Database\Eloquent\Factories\Factory;

class PendingNotificationFactory extends Factory
{
    protected $model = PendingNotification::class;

    public function definition(): array
    {
        $user = User::factory()->create();

        return [
            'user_id' => $user->id,
            'notification_type' => 'test:event', // Default or make configurable
            'channel' => $this->faker->randomElement(['mail', 'database']),
            'notification_class' => NotifyCommentCreated::class, // Example
            'notification_data' => ['id' => $this->faker->randomNumber(), 'content' => $this->faker->sentence()],
        ];
    }
}
