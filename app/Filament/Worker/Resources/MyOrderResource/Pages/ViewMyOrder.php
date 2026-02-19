<?php

namespace App\Filament\Worker\Resources\MyOrderResource\Pages;

use App\Filament\Worker\Resources\MyOrderResource;
use Filament\Resources\Pages\ViewRecord;

class ViewMyOrder extends ViewRecord
{
    protected static string $resource = MyOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            MyOrderResource::takeOrderAction(),
            MyOrderResource::startOrderAction(),
            MyOrderResource::completeOrderAction(),
            MyOrderResource::declineOrderAction(),
            MyOrderResource::supportAction(),
        ];
    }
}

