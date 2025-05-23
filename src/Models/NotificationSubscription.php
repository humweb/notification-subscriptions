<?php

namespace Humweb\Notifications\Models;

use Humweb\Notifications\Facades\NotificationSubscriptions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int    $user_id
 * @property string $type
 * @method static Builder|NotificationSubscription ofType($type)
 */
class NotificationSubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
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
        $userModel = config('notification-subscriptions.user_model', config('auth.providers.users.model', \App\Models\User::class));
        return $this->belongsTo($userModel, 'user_id');
    }

    /**
     * @param  Builder  $query
     * @param           $type
     *
     * @return void
     */
    public function scopeOfType(Builder $query, $type)
    {
        $query->where('type', $type);
    }
}
