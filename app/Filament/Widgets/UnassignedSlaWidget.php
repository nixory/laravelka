<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class UnassignedSlaWidget extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $unassigned = Order::query()
            ->where('status', Order::STATUS_NEW)
            ->whereNull('worker_id')
            ->count();

        $slaBreached = Order::query()
            ->where('status', Order::STATUS_NEW)
            ->whereNull('worker_id')
            ->where('created_at', '<=', now()->subMinutes(10))
            ->count();

        $oldestMinutes = null;
        $oldest = Order::query()
            ->where('status', Order::STATUS_NEW)
            ->whereNull('worker_id')
            ->orderBy('created_at')
            ->first();

        if ($oldest) {
            $oldestMinutes = (int) now()->diffInMinutes($oldest->created_at);
        }

        return [
            Stat::make('Без воркера', (string) $unassigned)
                ->description('Новых заказов в очереди')
                ->color($unassigned > 0 ? 'warning' : 'success'),

            Stat::make('SLA нарушен (>10 мин)', (string) $slaBreached)
                ->description($slaBreached > 0 ? '⚠️ Требуют срочного назначения!' : 'Всё в норме')
                ->color($slaBreached > 0 ? 'danger' : 'success'),

            Stat::make('Самый старый заказ', $oldestMinutes !== null ? "{$oldestMinutes} мин" : '—')
                ->description($oldestMinutes !== null && $oldestMinutes > 10 ? '🔴 Превышен SLA' : 'Ожидание в норме')
                ->color($oldestMinutes !== null && $oldestMinutes > 10 ? 'danger' : 'gray'),
        ];
    }
}
