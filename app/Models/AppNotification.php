<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class AppNotification extends Model
{
    use BelongsToTenant, HasUuids;

    protected $table = 'notifications';

    protected $fillable = [
        'type', 'notifiable_type', 'notifiable_id', 'data', 'read_at',
    ];

    protected $casts = [
        'data'    => 'array',
        'read_at' => 'datetime',
    ];

    public function scopeForUser($query, int $userId): void
    {
        $query->where(function ($q) use ($userId) {
            $q->where(function ($q2) use ($userId) {
                $q2->where('notifiable_type', User::class)
                   ->where('notifiable_id', $userId);
            })->orWhere('notifiable_type', 'broadcast');
        });
    }

    public function getIsReadAttribute(): bool
    {
        return !is_null($this->read_at);
    }
}
