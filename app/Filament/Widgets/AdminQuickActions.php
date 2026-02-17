<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\OrderResource;
use App\Filament\Resources\WithdrawalRequestResource;
use App\Models\Order;
use App\Models\WithdrawalRequest;
use Filament\Widgets\Widget;

class AdminQuickActions extends Widget
{
    protected static string $view = 'filament.widgets.admin-quick-actions';

    protected int|string|array $columnSpan = 'full';

    protected function getViewData(): array
    {
        $newOrders = Order::query()->where('status', Order::STATUS_NEW)->count();
        $unassignedOrders = Order::query()->whereNull('worker_id')->count();
        $pendingWithdrawals = WithdrawalRequest::query()->where('status', 'pending')->count();

        return [
            'newOrders' => $newOrders,
            'unassignedOrders' => $unassignedOrders,
            'pendingWithdrawals' => $pendingWithdrawals,
            'newOrdersUrl' => OrderResource::getUrl('index', ['activeTab' => 'new']),
            'unassignedOrdersUrl' => OrderResource::getUrl('index', ['activeTab' => 'unassigned']),
            'withdrawalsUrl' => WithdrawalRequestResource::getUrl('index'),
        ];
    }
}

