<?php

namespace App\Filament\Worker\Widgets;

use App\Models\Order;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class WorkerOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $worker = Filament::auth()->user()?->workerProfile;

        if (! $worker) {
            return [
                Stat::make('Активные заказы', '0'),
                Stat::make('Выполнено', '0'),
                Stat::make('Доступно к выводу', '0 ₽'),
                Stat::make('Заявки на вывод', '0'),
            ];
        }

        $activeOrders = Order::query()
            ->where('worker_id', $worker->id)
            ->whereIn('status', [Order::STATUS_ASSIGNED, Order::STATUS_ACCEPTED, Order::STATUS_IN_PROGRESS])
            ->count();

        $doneOrders = Order::query()
            ->where('worker_id', $worker->id)
            ->where('status', Order::STATUS_DONE)
            ->count();

        $pendingWithdrawals = $worker->withdrawalRequests()
            ->whereIn('status', ['pending', 'approved'])
            ->count();

        return [
            Stat::make('Активные заказы', (string) $activeOrders)
                ->description('Нужно отработать')
                ->color($activeOrders > 0 ? 'warning' : 'success'),
            Stat::make('Выполнено', (string) $doneOrders)
                ->description('За всё время')
                ->color('success'),
            Stat::make('Доступно к выводу', $this->formatRub($worker->availableWithdrawalBalance()) . ' ₽')
                ->description('Подтверждённый баланс')
                ->color('info'),
            Stat::make('Заявки на вывод', (string) $pendingWithdrawals)
                ->description('В обработке')
                ->color($pendingWithdrawals > 0 ? 'warning' : 'gray'),
        ];
    }

    private function formatRub(float $amount): string
    {
        return number_format($amount, 2, '.', ' ');
    }
}

