<?php

namespace Humweb\Notifications\Models;

use Humweb\Notifications\Database\Factories\PendingNotificationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Represents a pending notification within the application.
 *
 * This model is associated with the `pending_notifications` table
 * and is used to store and manage notifications that are pending delivery.
 * @property int $id
 * @property int $user_id
 * @property string $notification_type
 * @property string $channel
 * @property string $notification_class
 * @property array $notification_data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
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
