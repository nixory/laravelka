<?php

namespace App\Filament\Worker\Resources\MyCalendarSlotResource\Pages;

use App\Filament\Worker\Resources\MyCalendarSlotResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMyCalendarSlots extends ListRecords
{
    protected static string $resource = MyCalendarSlotResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

