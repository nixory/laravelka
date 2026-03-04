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
        // Onboarding
        'onboarding_status',
        'onboarding_notes',
        'age',
        'description',
        'audio_path',
        'photo_main',
        'photos_gallery',
        'favorite_games',
        'favorite_anime',
        'experience',
        'preferred_format',
        'services',
        'services_custom',
        'schedule_preferences',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'decimal:2',
            'is_active' => 'boolean',
            'photos_gallery' => 'array',
            'favorite_games' => 'array',
            'favorite_anime' => 'array',
            'services' => 'array',
            'services_custom' => 'array',
            'schedule_preferences' => 'array',
        ];
    }

    /* ── Onboarding helpers ── */

    public function isOnboardingComplete(): bool
    {
        return $this->onboarding_status === 'completed';
    }

    public function isPendingApproval(): bool
    {
        return $this->onboarding_status === 'pending_approval';
    }

    public function isOnStep(string $step): bool
    {
        return $this->onboarding_status === $step;
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
        $timezone = $this->timezone ?: 'Europe/Moscow';
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

    public function isAvailableWithinWindow(int $hours = 2): bool
    {
        $timezone = $this->timezone ?: 'Europe/Moscow';
        $nowLocal = now()->timezone($timezone);
        $endLocal = $nowLocal->copy()->addHours($hours);

        $nowUtc = now()->utc();
        $endUtc = $nowUtc->copy()->addHours($hours);

        // 1. Check explicit calendar slots (CalendarSlot) for 'available' status within the window.
        $hasAvailableSlot = $this->calendarSlots()
            ->where('status', 'available')
            ->where(function ($query) use ($nowUtc, $endUtc) {
                $query->where(function ($q) use ($nowUtc, $endUtc) {
                    // Slot starts within our window
                    $q->where('starts_at', '>=', $nowUtc)
                        ->where('starts_at', '<=', $endUtc);
                })->orWhere(function ($q) use ($nowUtc, $endUtc) {
                    // Slot is already ongoing and ends after now
                    $q->where('starts_at', '<=', $nowUtc)
                        ->where('ends_at', '>', $nowUtc);
                });
            })->exists();

        if ($hasAvailableSlot) {
            return true;
        }

        // 2. Check weekly recurring availability (WorkerAvailability)
        $current = $nowLocal->copy();
        while ($current <= $endLocal) {
            if ($this->isAvailableAt($current)) {
                return true;
            }
            $current->addMinutes(15);
        }

        if ($this->isAvailableAt($endLocal)) {
            return true;
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
