<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\TelegramNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class DispatchOpsAlerts extends Command
{
    protected $signature = 'ops:dispatch-alerts';

    protected $description = 'Dispatch admin and worker alert notifications';

    public function handle(TelegramNotifier $telegramNotifier): int
    {
        $this->dispatchUnassignedOrderAlerts($telegramNotifier);
        $this->dispatchStartRiskAlerts($telegramNotifier);
        $this->dispatchWorkerReminders($telegramNotifier, 30);
        $this->dispatchWorkerReminders($telegramNotifier, 10);

        return self::SUCCESS;
    }

    private function dispatchUnassignedOrderAlerts(TelegramNotifier $telegramNotifier): void
    {
        $thresholdMinutes = (int) env('OPS_ALERT_UNASSIGNED_MINUTES', 5);

        Order::query()
            ->whereNull('worker_id')
            ->whereIn('status', [Order::STATUS_NEW])
            ->where('created_at', '<=', now()->subMinutes($thresholdMinutes))
            ->orderBy('created_at')
            ->limit(100)
            ->get()
            ->each(function (Order $order) use ($telegramNotifier, $thresholdMinutes): void {
                $key = "ops_alert_unassigned_order_{$order->id}";
                if (! Cache::add($key, true, now()->addHours(2))) {
                    return;
                }

                $age = (int) $order->created_at->diffInMinutes(now());
                $telegramNotifier->notifyAdminUnassignedOrder($order, max($age, $thresholdMinutes));
            });
    }

    private function dispatchStartRiskAlerts(TelegramNotifier $telegramNotifier): void
    {
        $minutes = (int) env('OPS_ALERT_START_RISK_MINUTES', 30);
        $from = now();
        $to = now()->addMinutes($minutes);

        Order::query()
            ->with('worker')
            ->whereNotNull('starts_at')
            ->whereBetween('starts_at', [$from, $to])
            ->whereIn('status', [Order::STATUS_NEW, Order::STATUS_ASSIGNED])
            ->orderBy('starts_at')
            ->limit(100)
            ->get()
            ->each(function (Order $order) use ($telegramNotifier): void {
                $key = "ops_alert_start_risk_order_{$order->id}_{$order->starts_at?->timestamp}";
                if (! Cache::add($key, true, now()->addHours(3))) {
                    return;
                }

                $left = (int) now()->diffInMinutes($order->starts_at, false);
                if ($left < 0) {
                    return;
                }

                $telegramNotifier->notifyAdminOrderStartsSoonNotAccepted($order, $left);
            });
    }

    private function dispatchWorkerReminders(TelegramNotifier $telegramNotifier, int $minutesBefore): void
    {
        $windowStart = now()->addMinutes($minutesBefore)->subMinute();
        $windowEnd = now()->addMinutes($minutesBefore)->addMinute();

        Order::query()
            ->with('worker')
            ->whereNotNull('worker_id')
            ->whereNotNull('starts_at')
            ->whereBetween('starts_at', [$windowStart, $windowEnd])
            ->whereIn('status', [Order::STATUS_ASSIGNED, Order::STATUS_ACCEPTED, Order::STATUS_IN_PROGRESS])
            ->orderBy('starts_at')
            ->limit(200)
            ->get()
            ->each(function (Order $order) use ($telegramNotifier, $minutesBefore): void {
                $key = "ops_alert_worker_reminder_{$minutesBefore}_order_{$order->id}_{$order->starts_at?->timestamp}";
                if (! Cache::add($key, true, now()->addDay())) {
                    return;
                }

                $telegramNotifier->notifyWorkerStartReminder($order, $minutesBefore);
            });
    }
}

