<?php

namespace App\Filament\Worker\Resources\MyWithdrawalRequestResource\Pages;

use App\Filament\Worker\Resources\MyWithdrawalRequestResource;
use Filament\Resources\Pages\ListRecords;

class ListMyWithdrawalRequests extends ListRecords
{
    protected static string $resource = MyWithdrawalRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}

