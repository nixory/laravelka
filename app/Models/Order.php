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

    public function wooLineItems(): array
    {
        $items = $this->meta['line_items'] ?? [];
        return is_array($items) ? $items : [];
    }

    public function wooLineItemMeta(): array
    {
        foreach ($this->wooLineItems() as $lineItem) {
            if (! is_array($lineItem)) {
                continue;
            }

            $meta = $lineItem['meta'] ?? [];
            if (is_array($meta) && $meta !== []) {
                return $meta;
            }
        }

        return [];
    }

    public function wooOrderMeta(): array
    {
        $meta = $this->meta['order_meta'] ?? [];
        return is_array($meta) ? $meta : [];
    }

    public function metaValue(array $keys): ?string
    {
        $lineMeta = $this->wooLineItemMeta();
        foreach ($keys as $key) {
            if (array_key_exists($key, $lineMeta) && $lineMeta[$key] !== null && $lineMeta[$key] !== '') {
                return (string) $lineMeta[$key];
            }
        }

        $orderMeta = $this->wooOrderMeta();
        foreach ($keys as $key) {
            if (array_key_exists($key, $orderMeta) && $orderMeta[$key] !== null && $orderMeta[$key] !== '') {
                return (string) $orderMeta[$key];
            }
        }

        return null;
    }

    public function wooPlan(): ?string
    {
        return $this->metaValue(['План', 'plan', 'tariff']);
    }

    public function wooHours(): ?string
    {
        return $this->metaValue(['Часы', 'hours']);
    }

    public function wooAddons(): ?string
    {
        return $this->metaValue(['Дополнительно', 'addons', 'extra_services']);
    }

    public function wooSessionDate(): ?string
    {
        return $this->metaValue(['Дата сессии', 'booking_date']);
    }

    public function wooSessionTime(): ?string
    {
        return $this->metaValue(['Время сессии', 'booking_time']);
    }

    public function wooWorkerIdFromMeta(): ?string
    {
        return $this->metaValue(['ID работницы', 'worker_id', 'booking_worker_id']);
    }

    public function wooClientTelegram(): ?string
    {
        return $this->metaValue([
            'billing_tg',
            'billing_telegram',
            'telegram',
            'Твой Телеграм',
            'Твой Телеграм:',
        ]);
    }

    public function wooClientDiscord(): ?string
    {
        return $this->metaValue([
            'billing_ds',
            'billing_discord',
            'discord',
            'Твой Discord',
            'Твой Discord:',
        ]);
    }

    public function wooDesiredDateTime(): ?string
    {
        return $this->metaValue([
            'billing_time',
            'Желаемая дата и время',
            'Желаемая дата и время:',
        ]);
    }
}
