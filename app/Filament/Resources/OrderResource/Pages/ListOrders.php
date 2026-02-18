<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Filament\Widgets\UnassignedSlaWidget;
use App\Models\Order;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            UnassignedSlaWidget::class,
        ];
    }

    public function getTabs(): array
    {
        $slaBreached = Order::query()
            ->where('status', Order::STATUS_NEW)
            ->whereNull('worker_id')
            ->where('created_at', '<=', now()->subMinutes(10))
            ->count();

        $unassignedLabel = 'Без работницы';
        if ($slaBreached > 0) {
            $unassignedLabel = "Без работницы 🔴 {$slaBreached}";
        }

        return [
            'all' => Tab::make('Все'),
            'new' => Tab::make('Новые')
                ->modifyQueryUsing(fn(Builder $query): Builder => $query->where('status', Order::STATUS_NEW)),
            'assigned' => Tab::make('Назначены')
                ->modifyQueryUsing(fn(Builder $query): Builder => $query->where('status', Order::STATUS_ASSIGNED)),
            'in_work' => Tab::make('В работе')
                ->modifyQueryUsing(fn(Builder $query): Builder => $query->whereIn('status', [
                    Order::STATUS_ACCEPTED,
                    Order::STATUS_IN_PROGRESS,
                ])),
            'done' => Tab::make('Выполнены')
                ->modifyQueryUsing(fn(Builder $query): Builder => $query->where('status', Order::STATUS_DONE)),
            'cancelled' => Tab::make('Отменены')
                ->modifyQueryUsing(fn(Builder $query): Builder => $query->where('status', Order::STATUS_CANCELLED)),
            'unassigned' => Tab::make($unassignedLabel)
                ->modifyQueryUsing(fn(Builder $query): Builder => $query->whereNull('worker_id')->where('status', Order::STATUS_NEW)),
        ];
    }
}
