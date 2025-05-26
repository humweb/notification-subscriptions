<?php

namespace Humweb\Notifications\Models;

use Humweb\Notifications\Database\Factories\PendingNotificationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PendingNotification extends Model
{
    use HasFactory;

    protected $table = 'pending_notifications';

    protected $fillable = [
        'user_id',
        'notification_type',
        'channel',
        'notification_class',
        'notification_data',
    ];

    protected $casts = [
        'notification_data' => 'array',
    ];

    /**
     * Get the user that owns the pending notification.
     */
    public function user(): BelongsTo
    {
        // It's important that this user model matches what's defined in the config
        // or your application's default user model.
        $userModel = config('notification-subscriptions.user_model', config('auth.providers.users.model'));

        return $this->belongsTo($userModel, 'user_id');
    }

    /**
     * Create a new factory instance for the model.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    protected static function newFactory()
    {
        return PendingNotificationFactory::new();
    }
}
