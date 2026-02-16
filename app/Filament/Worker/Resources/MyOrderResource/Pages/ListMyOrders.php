<?php

namespace App\Filament\Worker\Resources\MyOrderResource\Pages;

use App\Filament\Worker\Resources\MyOrderResource;
use Filament\Resources\Pages\ListRecords;

class ListMyOrders extends ListRecords
{
    protected static string $resource = MyOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}

