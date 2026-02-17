<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use App\Models\WithdrawalRequest;
use App\Models\Worker;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AdminOpsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $newOrders = Order::query()->where('status', Order::STATUS_NEW)->count();
        $activeOrders = Order::query()
            ->whereIn('status', [Order::STATUS_ASSIGNED, Order::STATUS_ACCEPTED, Order::STATUS_IN_PROGRESS])
            ->count();
        $doneToday = Order::query()
            ->where('status', Order::STATUS_DONE)
            ->whereDate('completed_at', now()->toDateString())
            ->count();
        $pendingWithdrawals = WithdrawalRequest::query()
            ->where('status', 'pending')
            ->count();
        $onlineWorkers = Worker::query()
            ->where('is_active', true)
            ->where('status', 'online')
            ->count();

        return [
            Stat::make('Новые заказы', (string) $newOrders)
                ->description('Нуждаются в обработке')
                ->color($newOrders > 0 ? 'warning' : 'success'),
            Stat::make('Активные заказы', (string) $activeOrders)
                ->description('Назначены и в работе')
                ->color($activeOrders > 0 ? 'info' : 'gray'),
            Stat::make('Выполнено сегодня', (string) $doneToday)
                ->description('Завершённые сессии за день')
                ->color('success'),
            Stat::make('Заявки на вывод', (string) $pendingWithdrawals)
                ->description('Ожидают решения')
                ->color($pendingWithdrawals > 0 ? 'danger' : 'success'),
            Stat::make('Работницы онлайн', (string) $onlineWorkers)
                ->description('Сейчас доступны')
                ->color($onlineWorkers > 0 ? 'success' : 'gray'),
        ];
    }
}

