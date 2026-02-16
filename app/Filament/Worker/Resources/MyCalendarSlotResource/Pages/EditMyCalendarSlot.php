<?php

namespace App\Filament\Worker\Resources\MyCalendarSlotResource\Pages;

use App\Filament\Worker\Resources\MyCalendarSlotResource;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Resources\Pages\EditRecord;

class EditMyCalendarSlot extends EditRecord
{
    protected static string $resource = MyCalendarSlotResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['worker_id'] = Filament::auth()->user()?->workerProfile?->id;
        $data['source'] = 'manual';

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(fn (): bool => in_array((string) $this->record->status, ['available', 'blocked'], true)),
        ];
    }
}

