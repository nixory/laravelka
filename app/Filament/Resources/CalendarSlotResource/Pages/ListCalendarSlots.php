<?php

namespace App\Filament\Resources\CalendarSlotResource\Pages;

use App\Filament\Resources\CalendarSlotResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCalendarSlots extends ListRecords
{
    protected static string $resource = CalendarSlotResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
