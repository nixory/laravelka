<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BookingHold;
use App\Models\CalendarSlot;
use App\Models\Worker;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class BookingController extends Controller
{
    private const DISPLAY_TIMEZONE = 'Europe/Moscow';

    public function slotsRange(Request $request): JsonResponse
    {
        $this->authorizeRequest($request);

        $data = $request->validate([
            'worker_id' => ['required', 'integer', 'exists:workers,id'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'days' => ['nullable', 'integer', 'min:1', 'max:60'],
        ]);

        $this->cleanupExpiredHolds();

        $worker = Worker::query()
            ->findOrFail((int) $data['worker_id']);

        $from = (string) ($data['from'] ?? now(self::DISPLAY_TIMEZONE)->format('Y-m-d'));
        $days = (int) ($data['days'] ?? 30);

        $calendar = [];
        $cursor = Carbon::createFromFormat('Y-m-d', $from, self::DISPLAY_TIMEZONE)->startOfDay();

        for ($i = 0; $i < $days; $i++) {
            $date = $cursor->copy()->addDays($i)->format('Y-m-d');
            $slots = $this->resolveSlotsForDate($worker, $date);

            if ($slots !== []) {
                $calendar[] = [
                    'date' => $date,
                    'slots' => $slots,
                ];
            }
        }

        return response()->json([
            'ok' => true,
            'workerId' => $worker->id,
            'from' => $from,
            'days' => $days,
            'calendar' => $calendar,
            'errors' => [],
        ]);
    }

    public function hold(Request $request): JsonResponse
    {
        $this->authorizeRequest($request);

        $data = $request->validate([
            'worker_id' => ['required', 'integer', 'exists:workers,id'],
            'date' => ['required', 'date_format:Y-m-d'],
            'start' => ['required', 'date_format:H:i'],
            'end' => ['required', 'date_format:H:i'],
            'ttl' => ['nullable', 'integer', 'min:60', 'max:3600'],
        ]);

        $workerId = (int) $data['worker_id'];
        $date = (string) $data['date'];
        $start = (string) $data['start'];
        $end = (string) $data['end'];
        $range = $this->parseRangeToUtc($date, $start, $end);

        if ($range === null) {
            return response()->json([
                'ok' => false,
                'message' => 'Invalid time range.',
            ], 422);
        }

        [$startsAtUtc, $endsAtUtc] = $range;

        $ttl = (int) ($data['ttl'] ?? 600);
        $token = (string) Str::uuid();

        $this->cleanupExpiredHolds();

        [$hold, $error] = DB::transaction(function () use ($workerId, $startsAtUtc, $endsAtUtc, $ttl, $token): array {
            if ($this->hasCalendarOverlap($workerId, $startsAtUtc, $endsAtUtc)) {
                return [null, 'Slot is not available.'];
            }

            if ($this->hasActiveHoldOverlap($workerId, $startsAtUtc, $endsAtUtc)) {
                return [null, 'Slot already held.'];
            }

            $hold = BookingHold::query()->create([
                'token' => $token,
                'worker_id' => $workerId,
                'starts_at' => $startsAtUtc,
                'ends_at' => $endsAtUtc,
                'expires_at' => now()->addSeconds($ttl),
            ]);

            return [$hold, null];
        }, 3);

        if ($error !== null || ! $hold) {
            return response()->json([
                'ok' => false,
                'message' => $error ?? 'Could not create hold.',
            ], 409);
        }

        $payload = [
            'token' => $token,
            'worker_id' => $workerId,
            'date' => $hold->starts_at->copy()->setTimezone(self::DISPLAY_TIMEZONE)->format('Y-m-d'),
            'start' => $hold->starts_at->copy()->setTimezone(self::DISPLAY_TIMEZONE)->format('H:i'),
            'end' => $hold->ends_at->copy()->setTimezone(self::DISPLAY_TIMEZONE)->format('H:i'),
            'created_at' => now()->toIso8601String(),
            'expires_at' => $hold->expires_at->toIso8601String(),
        ];
        Cache::put($this->holdTokenKey($token), $payload, now()->addSeconds($ttl));

        return response()->json([
            'ok' => true,
            'hold' => $payload,
        ]);
    }

    public function confirm(Request $request): JsonResponse
    {
        $this->authorizeRequest($request);

        $data = $request->validate([
            'token' => ['required', 'string'],
            'order_id' => ['nullable', 'integer'],
        ]);

        $token = (string) $data['token'];
        $orderId = isset($data['order_id']) ? (int) $data['order_id'] : null;

        $this->cleanupExpiredHolds();

        [$confirmed, $error] = DB::transaction(function () use ($token, $orderId): array {
            $hold = BookingHold::query()
                ->where('token', $token)
                ->whereNull('released_at')
                ->whereNull('confirmed_at')
                ->lockForUpdate()
                ->first();

            if (! $hold) {
                return [null, 'Hold not found or expired.'];
            }

            if ($hold->expires_at->isPast()) {
                $hold->released_at = now();
                $hold->save();
                return [null, 'Hold not found or expired.'];
            }

            if ($this->hasCalendarOverlap(
                (int) $hold->worker_id,
                $hold->starts_at,
                $hold->ends_at,
                $orderId
            )) {
                return [null, 'Slot is not available.'];
            }

            $slot = CalendarSlot::query()->firstOrNew([
                'worker_id' => (int) $hold->worker_id,
                'starts_at' => $hold->starts_at,
                'ends_at' => $hold->ends_at,
            ]);

            $slot->status = 'booked';
            $slot->source = 'order';
            if ($orderId) {
                $slot->order_id = $orderId;
            }
            $slot->save();

            $hold->confirmed_at = now();
            if ($orderId) {
                $hold->order_id = $orderId;
            }
            $hold->save();

            return [[
                'worker_id' => (int) $hold->worker_id,
                'date' => $hold->starts_at->copy()->setTimezone(self::DISPLAY_TIMEZONE)->format('Y-m-d'),
                'start' => $hold->starts_at->copy()->setTimezone(self::DISPLAY_TIMEZONE)->format('H:i'),
                'end' => $hold->ends_at->copy()->setTimezone(self::DISPLAY_TIMEZONE)->format('H:i'),
                'order_id' => $orderId,
            ], null];
        }, 3);

        if ($error !== null || ! is_array($confirmed)) {
            return response()->json([
                'ok' => false,
                'message' => $error ?? 'Hold not found or expired.',
            ], 409);
        }

        Cache::forget($this->holdTokenKey($token));

        return response()->json([
            'ok' => true,
            'confirmed' => $confirmed,
        ]);
    }

    public function release(Request $request): JsonResponse
    {
        $this->authorizeRequest($request);

        $data = $request->validate([
            'token' => ['nullable', 'string'],
            'order_id' => ['nullable', 'integer'],
        ]);

        $released = 0;

        if (! empty($data['token'])) {
            $token = (string) $data['token'];
            $released += BookingHold::query()
                ->where('token', $token)
                ->whereNull('confirmed_at')
                ->whereNull('released_at')
                ->update(['released_at' => now()]);

            Cache::forget($this->holdTokenKey($token));
        }

        if (! empty($data['order_id'])) {
            $orderId = (int) $data['order_id'];
            BookingHold::query()
                ->where('order_id', $orderId)
                ->whereNull('released_at')
                ->update(['released_at' => now()]);

            $released += CalendarSlot::query()
                ->where('order_id', $orderId)
                ->whereIn('status', ['reserved', 'booked'])
                ->update([
                    'status' => 'available',
                    'order_id' => null,
                    'source' => 'manual',
                ]);
        }

        return response()->json([
            'ok' => true,
            'released' => $released,
        ]);
    }

    private function buildSlotsFromCalendarTable(Worker $worker, string $date): array
    {
        $timezone = self::DISPLAY_TIMEZONE;
        $dayStartLocal = Carbon::createFromFormat('Y-m-d', $date, $timezone)->startOfDay();
        $nextDayStartLocal = $dayStartLocal->copy()->addDay();

        $rows = CalendarSlot::query()
            ->where('worker_id', $worker->id)
            // Include any slot overlapping this local day, not only slots that start on this day.
            ->where('starts_at', '<', $nextDayStartLocal->copy()->utc())
            ->where('ends_at', '>', $dayStartLocal->copy()->utc())
            ->orderBy('starts_at')
            ->get(['starts_at', 'ends_at', 'status']);

        if ($rows->isEmpty()) {
            return [];
        }

        $availableIntervals = [];
        $blockedIntervals = [];

        foreach ($rows as $row) {
            $rowStartLocal = $row->starts_at->copy()->setTimezone($timezone);
            $rowEndLocal = $row->ends_at->copy()->setTimezone($timezone);

            $startLocal = $rowStartLocal->greaterThan($dayStartLocal) ? $rowStartLocal : $dayStartLocal->copy();
            $endLocal = $rowEndLocal->lessThan($nextDayStartLocal) ? $rowEndLocal : $nextDayStartLocal->copy();

            if ($endLocal->lessThanOrEqualTo($startLocal)) {
                continue;
            }

            $interval = [
                'start' => $startLocal,
                'end' => $endLocal,
            ];

            if ($row->status === 'available') {
                $availableIntervals[] = $interval;
                continue;
            }

            if (in_array((string) $row->status, ['reserved', 'booked', 'blocked'], true)) {
                $blockedIntervals[] = $interval;
            }
        }

        $activeHolds = BookingHold::query()
            ->where('worker_id', $worker->id)
            ->whereNull('released_at')
            ->whereNull('confirmed_at')
            ->where('expires_at', '>', now())
            ->where('starts_at', '<', $nextDayStartLocal->copy()->utc())
            ->where('ends_at', '>', $dayStartLocal->copy()->utc())
            ->get(['starts_at', 'ends_at']);

        foreach ($activeHolds as $hold) {
            $holdStartLocal = $hold->starts_at->copy()->setTimezone($timezone);
            $holdEndLocal = $hold->ends_at->copy()->setTimezone($timezone);
            $blockedIntervals[] = [
                'start' => $holdStartLocal->greaterThan($dayStartLocal) ? $holdStartLocal : $dayStartLocal->copy(),
                'end' => $holdEndLocal->lessThan($nextDayStartLocal) ? $holdEndLocal : $nextDayStartLocal->copy(),
            ];
        }

        $slots = [];
        foreach ($availableIntervals as $interval) {
            $cursor = $interval['start']->copy();

            while ($cursor->lessThan($interval['end'])) {
                $slotEnd = $cursor->copy()->addHour();
                if ($slotEnd->greaterThan($interval['end'])) {
                    break;
                }

                if ($this->overlapsBlockedIntervals($cursor, $slotEnd, $blockedIntervals)) {
                    $cursor = $slotEnd;
                    continue;
                }

                $start = $cursor->format('H:i');
                $end = $slotEnd->format('H:i');
                $slotDate = $date;

                $slots[] = [
                    'start' => $start,
                    'end' => $end,
                    'date' => $slotDate,
                    'label' => $start.' - '.$end,
                    'available' => true,
                ];

                $cursor = $slotEnd;
            }
        }

        usort($slots, fn ($a, $b) => strcmp((string) $a['start'], (string) $b['start']));

        return array_values(array_map('unserialize', array_unique(array_map('serialize', $slots))));
    }

    private function hasCalendarOverlap(
        int $workerId,
        Carbon $startsAtUtc,
        Carbon $endsAtUtc,
        ?int $ignoreOrderId = null
    ): bool
    {
        return CalendarSlot::query()
            ->where('worker_id', $workerId)
            ->whereIn('status', ['reserved', 'booked', 'blocked'])
            ->where('starts_at', '<', $endsAtUtc)
            ->where('ends_at', '>', $startsAtUtc)
            ->when($ignoreOrderId, fn ($q) => $q->where(fn ($qq) => $qq->whereNull('order_id')->orWhere('order_id', '!=', $ignoreOrderId)))
            ->exists();
    }

    private function hasActiveHoldOverlap(int $workerId, Carbon $startsAtUtc, Carbon $endsAtUtc): bool
    {
        return BookingHold::query()
            ->where('worker_id', $workerId)
            ->whereNull('released_at')
            ->whereNull('confirmed_at')
            ->where('expires_at', '>', now())
            ->where('starts_at', '<', $endsAtUtc)
            ->where('ends_at', '>', $startsAtUtc)
            ->exists();
    }

    private function parseRangeToUtc(string $date, string $start, string $end): ?array
    {
        try {
            $startsAt = Carbon::createFromFormat('Y-m-d H:i', $date.' '.$start, self::DISPLAY_TIMEZONE);
            $endsAt = Carbon::createFromFormat('Y-m-d H:i', $date.' '.$end, self::DISPLAY_TIMEZONE);

            if ($endsAt->lessThanOrEqualTo($startsAt)) {
                $endsAt->addDay();
            }

            // Hard safety bounds for single hold range.
            if ($endsAt->diffInMinutes($startsAt) > 24 * 60) {
                return null;
            }

            return [$startsAt->copy()->utc(), $endsAt->copy()->utc()];
        } catch (\Throwable) {
            return null;
        }
    }

    private function cleanupExpiredHolds(): void
    {
        BookingHold::query()
            ->whereNull('released_at')
            ->whereNull('confirmed_at')
            ->where('expires_at', '<=', now())
            ->update(['released_at' => now()]);
    }

    private function overlapsBlockedIntervals(Carbon $slotStart, Carbon $slotEnd, array $blockedIntervals): bool
    {
        foreach ($blockedIntervals as $interval) {
            $blockedStart = $interval['start'];
            $blockedEnd = $interval['end'];

            if ($slotStart->lessThan($blockedEnd) && $slotEnd->greaterThan($blockedStart)) {
                return true;
            }
        }

        return false;
    }

    private function holdTokenKey(string $token): string
    {
        return 'ops_booking_hold_token_'.$token;
    }

    private function authorizeRequest(Request $request): void
    {
        $configuredToken = (string) config('services.woo.webhook_token');
        $incomingToken = (string) ($request->header('X-Ops-Webhook-Token') ?: $request->bearerToken());

        if ($configuredToken !== '' && $incomingToken !== '' && hash_equals($configuredToken, $incomingToken)) {
            return;
        }

        response()->json([
            'ok' => false,
            'message' => 'Invalid token.',
        ], 401)->throwResponse();
    }

    private function resolveSlotsForDate(Worker $worker, string $date): array
    {
        return $this->buildSlotsFromCalendarTable($worker, $date);
    }
}
