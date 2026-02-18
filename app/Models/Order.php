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
        'woo_status',
        'client_name',
        'client_phone',
        'client_email',
        'service_name',
        'service_price',
        'woo_currency',
        'woo_payment_method',
        'woo_plan',
        'woo_hours',
        'woo_addons',
        'woo_session_date',
        'woo_session_time',
        'woo_worker_id',
        'woo_client_telegram',
        'woo_client_discord',
        'woo_desired_datetime',
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
            'woo_session_date' => 'date',
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
        if (! empty($this->woo_plan)) {
            return (string) $this->woo_plan;
        }

        return $this->metaValue(['План', 'plan', 'tariff']);
    }

    public function wooHours(): ?string
    {
        if (! empty($this->woo_hours)) {
            return (string) $this->woo_hours;
        }

        return $this->metaValue(['Часы', 'hours']);
    }

    public function wooAddons(): ?string
    {
        if (! empty($this->woo_addons)) {
            return (string) $this->woo_addons;
        }

        return $this->metaValue(['Дополнительно', 'addons', 'extra_services']);
    }

    public function wooSessionDate(): ?string
    {
        if ($this->woo_session_date) {
            return $this->woo_session_date->format('Y-m-d');
        }

        return $this->metaValue(['Дата сессии', 'booking_date']);
    }

    public function wooSessionTime(): ?string
    {
        if (! empty($this->woo_session_time)) {
            return (string) $this->woo_session_time;
        }

        return $this->metaValue(['Время сессии', 'booking_time']);
    }

    public function wooWorkerIdFromMeta(): ?string
    {
        if (! empty($this->woo_worker_id)) {
            return (string) $this->woo_worker_id;
        }

        return $this->metaValue(['ID работницы', 'worker_id', 'booking_worker_id']);
    }

    public function wooClientTelegram(): ?string
    {
        if (! empty($this->woo_client_telegram)) {
            return (string) $this->woo_client_telegram;
        }

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
        if (! empty($this->woo_client_discord)) {
            return (string) $this->woo_client_discord;
        }

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
        if (! empty($this->woo_desired_datetime)) {
            return (string) $this->woo_desired_datetime;
        }

        return $this->metaValue([
            'billing_time',
            'Желаемая дата и время',
            'Желаемая дата и время:',
        ]);
    }

    public function statusLabel(): string
    {
        return match ((string) $this->status) {
            self::STATUS_NEW => 'Новый',
            self::STATUS_ASSIGNED => 'Назначен',
            self::STATUS_ACCEPTED => 'Принят',
            self::STATUS_IN_PROGRESS => 'В работе',
            self::STATUS_DONE => 'Выполнен',
            self::STATUS_CANCELLED => 'Отменён',
            default => (string) $this->status,
        };
    }

    public function timelineEvents(): array
    {
        $events = [];

        $events[] = [
            'title' => 'Заказ создан',
            'type' => 'created',
            'at' => optional($this->created_at)?->timezone('Europe/Moscow')?->format('d.m.Y H:i') ?: '-',
            'sort_at' => optional($this->created_at)?->timestamp ?? 0,
            'note' => $this->external_order_id ? "Woo ID: {$this->external_order_id}" : null,
        ];

        if ($this->worker_id) {
            $events[] = [
                'title' => 'Назначена работница',
                'type' => 'assigned',
                'at' => optional($this->updated_at)?->timezone('Europe/Moscow')?->format('d.m.Y H:i') ?: '-',
                'sort_at' => optional($this->updated_at)?->timestamp ?? 0,
                'note' => $this->worker?->display_name ? "Работница: {$this->worker->display_name}" : null,
            ];
        }

        if ($this->accepted_at) {
            $events[] = [
                'title' => 'Заказ принят работницей',
                'type' => 'accepted',
                'at' => $this->accepted_at->timezone('Europe/Moscow')->format('d.m.Y H:i'),
                'sort_at' => $this->accepted_at->timestamp,
                'note' => null,
            ];
        }

        if ($this->completed_at) {
            $events[] = [
                'title' => 'Заказ выполнен',
                'type' => 'done',
                'at' => $this->completed_at->timezone('Europe/Moscow')->format('d.m.Y H:i'),
                'sort_at' => $this->completed_at->timestamp,
                'note' => null,
            ];
        }

        if ($this->cancelled_at) {
            $events[] = [
                'title' => 'Заказ отменён',
                'type' => 'cancelled',
                'at' => $this->cancelled_at->timezone('Europe/Moscow')->format('d.m.Y H:i'),
                'sort_at' => $this->cancelled_at->timestamp,
                'note' => null,
            ];
        }

        foreach ($this->declineRequests()->with('worker')->latest('created_at')->get() as $decline) {
            $events[] = [
                'title' => 'Отказ от заказа',
                'type' => 'declined',
                'at' => optional($decline->created_at)?->timezone('Europe/Moscow')?->format('d.m.Y H:i') ?: '-',
                'sort_at' => optional($decline->created_at)?->timestamp ?? 0,
                'note' => trim(($decline->worker?->display_name ? "Работница: {$decline->worker->display_name}. " : '') . ($decline->reason_text ?: $decline->reason_code)),
            ];
        }

        usort($events, static fn (array $a, array $b): int => ((int) $a['sort_at']) <=> ((int) $b['sort_at']));

        foreach ($events as &$event) {
            unset($event['sort_at']);
        }
        unset($event);

        return $events;
    }
}
