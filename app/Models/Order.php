<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use HasFactory;

    public const STATUS_NEW = 'new';
    public const STATUS_ASSIGNED = 'assigned';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_DONE = 'done';
    public const STATUS_CANCELLED = 'cancelled';

    public const ACTIVE_WORK_STATUSES = [
        self::STATUS_ASSIGNED,
        self::STATUS_ACCEPTED,
        self::STATUS_IN_PROGRESS,
    ];

    protected $fillable = [
        'external_source',
        'external_order_id',
        'client_name',
        'client_phone',
        'client_email',
        'service_name',
        'service_price',
        'starts_at',
        'ends_at',
        'status',
        'worker_id',
        'assigned_by_user_id',
        'accepted_at',
        'completed_at',
        'cancelled_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'service_price' => 'decimal:2',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'accepted_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }

    public function chat(): HasOne
    {
        return $this->hasOne(Chat::class);
    }

    public function calendarSlot(): HasOne
    {
        return $this->hasOne(CalendarSlot::class);
    }

    public function payoutTransactions(): HasMany
    {
        return $this->hasMany(PayoutTransaction::class);
    }

    public function declineRequests(): HasMany
    {
        return $this->hasMany(OrderDeclineRequest::class);
    }

    public function isAutoAssignable(): bool
    {
        return $this->status === self::STATUS_NEW && ! $this->worker_id;
    }
}
