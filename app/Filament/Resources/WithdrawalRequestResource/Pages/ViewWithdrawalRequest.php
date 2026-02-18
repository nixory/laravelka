<?php

namespace App\Filament\Resources\WithdrawalRequestResource\Pages;

use App\Filament\Resources\WithdrawalRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewWithdrawalRequest extends ViewRecord
{
    protected static string $resource = WithdrawalRequestResource::class;

    public function getTitle(): string
    {
        return 'Заявка на вывод #' . $this->record->id;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()->label('Редактировать'),
        ];
    }
}
