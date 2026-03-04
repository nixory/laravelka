<?php

namespace App\Filament\Resources\WorkerProfileEditRequestResource\Pages;

use App\Filament\Resources\WorkerProfileEditRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageWorkerProfileEditRequests extends ManageRecords
{
    protected static string $resource = WorkerProfileEditRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
