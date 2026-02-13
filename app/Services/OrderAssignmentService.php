<?php

namespace App\Services;

use App\Models\CalendarSlot;
use App\Models\Chat;
use App\Models\Order;
use App\Models\Worker;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class OrderAssignmentService
{
    public function assign(Order $order): ?Worker
    {
        if (! $order->isAutoAssignable()) {
            return null;
        }

        $worker = $this->pickWorker($order);

        if (! $worker) {
            return null;
        }

        DB::transaction(function () use ($order, $worker): void {
            $order->forceFill([
                'worker_id' => $worker->id,
                'status' => Order::STATUS_ASSIGNED,
            ])->save();

            $chat = Chat::firstOrCreate(
                ['order_id' => $order->id],
                [
                    'worker_id' => $worker->id,
                    'client_name' => $order->client_name,
                    'client_email' => $order->client_email,
                    'client_phone' => $order->client_phone,
                    'status' => 'open',
                ]
            );

            if (! $chat->worker_id) {
                $chat->worker_id = $worker->id;
                $chat->save();
            }

            if ($order->starts_at && $order->ends_at) {
                CalendarSlot::updateOrCreate(
                    ['order_id' => $order->id],
                    [
                        'worker_id' => $worker->id,
                        'starts_at' => $order->starts_at,
                        'ends_at' => $order->ends_at,
                        'status' => 'booked',
                        'source' => 'order',
                    ]
                );
            }
        });

        return $worker;
    }

    public function pickWorker(Order $order): ?Worker
    {
        $query = Worker::query()
            ->where('is_active', true)
            ->whereIn('status', ['online', 'busy'])
            ->with('availabilities')
            ->withCount([
                'orders as active_orders_count' => fn ($query) => $query
                    ->whereIn('status', Order::ACTIVE_WORK_STATUSES),
            ]);

        if ($order->starts_at && $order->ends_at) {
            $query->whereDoesntHave('calendarSlots', function ($slotQuery) use ($order): void {
                $slotQuery
                    ->whereIn('status', ['reserved', 'booked', 'blocked'])
                    ->where('starts_at', '<', $order->ends_at)
                    ->where('ends_at', '>', $order->starts_at);
            });
        }

        $assignmentMoment = $this->resolveAssignmentMoment($order);

        return $query
            ->orderByRaw("case when status = 'online' then 0 else 1 end")
            ->orderBy('active_orders_count')
            ->orderByDesc('rating')
            ->orderByDesc('completed_orders')
            ->get()
            ->first(fn (Worker $worker): bool => $worker->isAvailableAt($assignmentMoment));
    }

    private function resolveAssignmentMoment(Order $order): CarbonInterface
    {
        return $order->starts_at ?: now();
    }
}
