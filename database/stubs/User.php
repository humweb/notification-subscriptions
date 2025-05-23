<?php

namespace Humweb\Notifications\Database\Stubs;

use Humweb\Notifications\Traits\Subscribable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable, Subscribable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'email',
        'name',
    ];

    protected static function newFactory()
    {
        return \Humweb\Notifications\Database\Factories\UserFactory::new();
    }
}
