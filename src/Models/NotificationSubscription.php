<?php

namespace Humweb\Notifications\Models;

use Humweb\Notifications\Facades\NotificationSubscriptions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int    $user_id
 * @property string $type
 * @method static Builder|NotificationSubscription ofType($type)
 */
class NotificationSubscription extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function user()
    {
        return $this->belongsTo(NotificationSubscriptions::getUserModel());
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
