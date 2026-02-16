<?php

namespace App\Filament\Resources\WorkerResource\Pages;

use App\Filament\Resources\WorkerResource;
use App\Models\User;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditWorker extends EditRecord
{
    protected static string $resource = WorkerResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if (isset($data['user_id']) && $data['user_id']) {
            $user = User::query()->find((int) $data['user_id']);
            if ($user) {
                $user->role = User::ROLE_WORKER;
                $user->save();
            }
        }

        $record->update($data);

        return $record;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
