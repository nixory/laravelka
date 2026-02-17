<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Worker extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'display_name',
        'slug',
        'phone',
        'telegram',
        'telegram_chat_id',
        'city',
        'timezone',
        'status',
        'rating',
        'completed_orders',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function chats(): HasMany
    {
        return $this->hasMany(Chat::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function calendarSlots(): HasMany
    {
        return $this->hasMany(CalendarSlot::class);
    }

    public function availabilities(): HasMany
    {
        return $this->hasMany(WorkerAvailability::class);
    }

    public function payoutTransactions(): HasMany
    {
        return $this->hasMany(PayoutTransaction::class);
    }

    public function withdrawalRequests(): HasMany
    {
        return $this->hasMany(WithdrawalRequest::class);
    }

    public function orderDeclineRequests(): HasMany
    {
        return $this->hasMany(OrderDeclineRequest::class);
    }

    public function isAvailableAt(CarbonInterface $moment): bool
    {
        $timezone = $this->timezone ?: 'UTC';
        $localMoment = $moment->copy()->timezone($timezone);
        $dayOfWeek = $localMoment->dayOfWeek;
        $time = $localMoment->format('H:i:s');

        $availabilities = $this->availabilities
            ->where('is_active', true)
            ->where('day_of_week', $dayOfWeek);

        foreach ($availabilities as $availability) {
            $start = $availability->start_time;
            $end = $availability->end_time;

            if ($start <= $end) {
                if ($time >= $start && $time < $end) {
                    return true;
                }

                continue;
            }

            if ($time >= $start || $time < $end) {
                return true;
            }
        }

        return false;
    }

    public function confirmedPayoutBalance(): float
    {
        $credits = (float) $this->payoutTransactions()
            ->where('status', 'confirmed')
            ->where('type', 'credit')
            ->sum('amount');

        $debits = (float) $this->payoutTransactions()
            ->where('status', 'confirmed')
            ->where('type', 'debit')
            ->sum('amount');

        return max(0, round($credits - $debits, 2));
    }

    public function pendingWithdrawalAmount(): float
    {
        return (float) $this->withdrawalRequests()
            ->whereIn('status', ['pending', 'approved'])
            ->sum('amount');
    }

    public function availableWithdrawalBalance(): float
    {
        return max(0, round($this->confirmedPayoutBalance() - $this->pendingWithdrawalAmount(), 2));
    }
}
