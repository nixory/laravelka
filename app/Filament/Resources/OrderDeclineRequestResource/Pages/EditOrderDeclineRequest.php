<?php

namespace App\Filament\Resources\OrderDeclineRequestResource\Pages;

use App\Filament\Resources\OrderDeclineRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditOrderDeclineRequest extends EditRecord
{
    protected static string $resource = OrderDeclineRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

