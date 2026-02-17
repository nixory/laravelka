<?php

namespace App\Filament\Worker\Resources\MyOrderResource\Pages;

use App\Filament\Worker\Resources\MyOrderResource;
use App\Models\Order;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListMyOrders extends ListRecords
{
    protected static string $resource = MyOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Все'),
            'new' => Tab::make('Новые')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', Order::STATUS_NEW)),
            'assigned' => Tab::make('Назначены')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', Order::STATUS_ASSIGNED)),
            'in_work' => Tab::make('В работе')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('status', [
                    Order::STATUS_ACCEPTED,
                    Order::STATUS_IN_PROGRESS,
                ])),
            'done' => Tab::make('Выполнены')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', Order::STATUS_DONE)),
            'cancelled' => Tab::make('Отменены')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', Order::STATUS_CANCELLED)),
        ];
    }
}
