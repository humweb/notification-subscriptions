<?php

namespace Humweb\Notifications\Database\Stubs;

use Humweb\Notifications\Traits\Subscribable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * @property int $id
 */
class User extends Authenticatable
{
    use HasFactory, Notifiable, Subscribable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'email',
        'name',
        'is_admin',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_admin' => 'boolean',
    ];

    protected static function newFactory()
    {
        return \Humweb\Notifications\Database\Factories\UserFactory::new();
    }
}
