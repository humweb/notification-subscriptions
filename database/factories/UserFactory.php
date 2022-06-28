<?php

namespace Humweb\Notifications\Database\Factories;

use Humweb\Notifications\Database\Stubs\User;
use Illuminate\Database\Eloquent\Factories\Factory;


class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition()
    {
        return [
            'email'   => $this->faker->email,
            'name'   => $this->faker->userName,
        ];
    }
}
