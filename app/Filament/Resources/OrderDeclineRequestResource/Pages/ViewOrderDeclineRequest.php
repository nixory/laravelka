<?php

namespace App\Filament\Resources\OrderDeclineRequestResource\Pages;

use App\Filament\Resources\OrderDeclineRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewOrderDeclineRequest extends ViewRecord
{
    protected static string $resource = OrderDeclineRequestResource::class;

    public function getTitle(): string
    {
        return 'Отказ #' . $this->record->id . ' — Заказ #' . $this->record->order_id;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()->label('Редактировать'),
        ];
    }
}
