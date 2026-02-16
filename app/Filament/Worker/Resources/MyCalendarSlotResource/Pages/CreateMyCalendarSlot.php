<?php

namespace App\Filament\Worker\Resources\MyCalendarSlotResource\Pages;

use App\Filament\Worker\Resources\MyCalendarSlotResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateMyCalendarSlot extends CreateRecord
{
    protected static string $resource = MyCalendarSlotResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $workerId = Filament::auth()->user()?->workerProfile?->id;
        $data['worker_id'] = $workerId;
        $data['source'] = 'manual';

        return $data;
    }
}

