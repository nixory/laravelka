<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CalendarSlot;
use App\Models\Worker;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class BookingController extends Controller
{
    public function slots(Request $request): JsonResponse
    {
        $this->authorizeRequest($request);

        $data = $request->validate([
            'worker_id' => ['required', 'integer', 'exists:workers,id'],
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        $worker = Worker::query()->with(['availabilities' => fn ($q) => $q->where('is_active', true)])->findOrFail((int) $data['worker_id']);
        $date = (string) $data['date'];
        $slots = $this->buildSlotsFromCalendarTable($worker, $date);

        if (count($slots) === 0) {
            $slots = $this->buildSlotsForDate($worker, $date);

            foreach ($slots as &$slot) {
                $slot['available'] = ! $this->isSlotBlocked($worker->id, $date, $slot['start'], $slot['end']);
            }
        }

        return response()->json([
            'ok' => true,
            'slots' => $slots,
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

        if ($this->isSlotBlocked($workerId, $date, $start, $end)) {
            return response()->json([
                'ok' => false,
                'message' => 'Slot is not available.',
            ], 409);
        }

        $ttl = (int) ($data['ttl'] ?? 600);
        $token = (string) \Illuminate\Support\Str::uuid();

        $payload = [
            'token' => $token,
            'worker_id' => $workerId,
            'date' => $date,
            'start' => $start,
            'end' => $end,
            'created_at' => now()->toIso8601String(),
        ];

        $slotKey = $this->holdSlotKey($workerId, $date, $start, $end);

        if (Cache::has($slotKey)) {
            return response()->json([
                'ok' => false,
                'message' => 'Slot already held.',
            ], 409);
        }

        Cache::put($slotKey, $payload, now()->addSeconds($ttl));
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

        $payload = Cache::get($this->holdTokenKey($token));
        if (! is_array($payload)) {
            return response()->json([
                'ok' => false,
                'message' => 'Hold not found or expired.',
            ], 404);
        }

        $worker = Worker::query()->find((int) $payload['worker_id']);
        if (! $worker) {
            return response()->json(['ok' => false, 'message' => 'Worker not found.'], 404);
        }

        $startsAt = Carbon::parse($payload['date'].' '.$payload['start'], $worker->timezone ?: 'UTC')->utc();
        $endsAt = Carbon::parse($payload['date'].' '.$payload['end'], $worker->timezone ?: 'UTC')->utc();

        $slot = CalendarSlot::query()->firstOrNew([
            'worker_id' => (int) $payload['worker_id'],
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);

        $slot->status = 'booked';
        $slot->source = 'order';
        if ($orderId && ! $slot->order_id) {
            $slot->order_id = $orderId;
        }
        $slot->save();

        Cache::forget($this->holdSlotKey((int) $payload['worker_id'], (string) $payload['date'], (string) $payload['start'], (string) $payload['end']));
        Cache::forget($this->holdTokenKey($token));

        return response()->json([
            'ok' => true,
            'confirmed' => [
                'worker_id' => (int) $payload['worker_id'],
                'date' => (string) $payload['date'],
                'start' => (string) $payload['start'],
                'end' => (string) $payload['end'],
                'order_id' => $orderId,
            ],
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
            $payload = Cache::get($this->holdTokenKey($token));
            if (is_array($payload)) {
                Cache::forget($this->holdSlotKey((int) $payload['worker_id'], (string) $payload['date'], (string) $payload['start'], (string) $payload['end']));
                Cache::forget($this->holdTokenKey($token));
                $released++;
            }
        }

        if (! empty($data['order_id'])) {
            $orderId = (int) $data['order_id'];
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

    private function buildSlotsForDate(Worker $worker, string $date): array
    {
        $timezone = $worker->timezone ?: 'UTC';
        $localDate = Carbon::createFromFormat('Y-m-d', $date, $timezone);
        $dayOfWeek = $localDate->dayOfWeek;

        $slots = [];
        $availabilities = $worker->availabilities->where('day_of_week', $dayOfWeek);

        foreach ($availabilities as $availability) {
            $start = Carbon::createFromFormat('Y-m-d H:i:s', $date.' '.$availability->start_time, $timezone);
            $end = Carbon::createFromFormat('Y-m-d H:i:s', $date.' '.$availability->end_time, $timezone);

            if ($end->lessThanOrEqualTo($start)) {
                continue;
            }

            $cursor = $start->copy();
            while ($cursor->lessThan($end)) {
                $slotEnd = $cursor->copy()->addHour();
                if ($slotEnd->greaterThan($end)) {
                    break;
                }

                $slots[] = [
                    'start' => $cursor->format('H:i'),
                    'end' => $slotEnd->format('H:i'),
                    'date' => $date,
                    'label' => $cursor->format('H:i').' - '.$slotEnd->format('H:i'),
                ];

                $cursor = $slotEnd;
            }
        }

        usort($slots, fn ($a, $b) => strcmp($a['start'], $b['start']));

        return array_values(array_map('unserialize', array_unique(array_map('serialize', $slots))));
    }

    private function buildSlotsFromCalendarTable(Worker $worker, string $date): array
    {
        $timezone = $worker->timezone ?: 'UTC';
        $dayStartLocal = Carbon::createFromFormat('Y-m-d', $date, $timezone)->startOfDay();
        $dayEndLocal = $dayStartLocal->copy()->endOfDay();

        $rows = CalendarSlot::query()
            ->where('worker_id', $worker->id)
            ->where('starts_at', '>=', $dayStartLocal->copy()->utc())
            ->where('starts_at', '<=', $dayEndLocal->copy()->utc())
            ->orderBy('starts_at')
            ->get(['starts_at', 'ends_at', 'status']);

        if ($rows->isEmpty()) {
            return [];
        }

        $slots = [];
        foreach ($rows as $row) {
            $startLocal = $row->starts_at->copy()->setTimezone($timezone);
            $endLocal = $row->ends_at->copy()->setTimezone($timezone);

            $start = $startLocal->format('H:i');
            $end = $endLocal->format('H:i');
            $slotDate = $startLocal->format('Y-m-d');

            $isAvailable = $row->status === 'available'
                && ! Cache::has($this->holdSlotKey($worker->id, $slotDate, $start, $end));

            $slots[] = [
                'start' => $start,
                'end' => $end,
                'date' => $slotDate,
                'label' => $start.' - '.$end,
                'available' => $isAvailable,
            ];
        }

        return $slots;
    }

    private function isSlotBlocked(int $workerId, string $date, string $start, string $end): bool
    {
        $worker = Worker::query()->find($workerId);
        if (! $worker) {
            return true;
        }

        $timezone = $worker->timezone ?: 'UTC';
        $startsAt = Carbon::parse($date.' '.$start, $timezone)->utc();
        $endsAt = Carbon::parse($date.' '.$end, $timezone)->utc();

        $bookedExists = CalendarSlot::query()
            ->where('worker_id', $workerId)
            ->where('starts_at', $startsAt)
            ->where('ends_at', $endsAt)
            ->whereIn('status', ['reserved', 'booked', 'blocked'])
            ->exists();

        if ($bookedExists) {
            return true;
        }

        return Cache::has($this->holdSlotKey($workerId, $date, $start, $end));
    }

    private function holdSlotKey(int $workerId, string $date, string $start, string $end): string
    {
        return 'ops_booking_hold_slot_'.md5($workerId.'|'.$date.'|'.$start.'|'.$end);
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
}
