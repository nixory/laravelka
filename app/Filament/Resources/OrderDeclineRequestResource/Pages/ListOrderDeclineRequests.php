<?php

namespace App\Filament\Resources\OrderDeclineRequestResource\Pages;

use App\Filament\Resources\OrderDeclineRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOrderDeclineRequests extends ListRecords
{
    protected static string $resource = OrderDeclineRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

