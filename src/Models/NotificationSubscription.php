<?php

namespace Humweb\Notifications\Models;

use Humweb\Notifications\Database\Stubs\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $type
 * @property string $channel
 * @property string $digest_interval
 * @property string $digest_at_time
 * @property string $digest_at_day
 * @property \Illuminate\Support\Carbon|null $last_digest_sent_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @method static Builder|NotificationSubscription ofType($type)
 */
class NotificationSubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'channel',
        'digest_interval',
        'digest_at_time',
        'digest_at_day',
        'last_digest_sent_at',
    ];

    protected $casts = [
        'last_digest_sent_at' => 'datetime',
    ];

    /**
     * Get the table associated with the model.
     *
     * @return string
     */
    public function getTable()
    {
        return config('notification-subscriptions.table_name', 'notification_subscriptions');
    }

    /**
     * Get the user that owns the subscription.
     */
    public function user(): BelongsTo
    {
        $userModel = config('notification-subscriptions.user_model', config('auth.providers.users.model', User::class));

        return $this->belongsTo($userModel, 'user_id');
    }

    /**
     * @return void
     */
    public function scopeOfType(Builder $query, $type)
    {
        $query->where('type', $type);
    }
}
