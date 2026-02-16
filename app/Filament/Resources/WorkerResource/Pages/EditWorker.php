<?php

namespace App\Filament\Resources\WorkerResource\Pages;

use App\Filament\Resources\WorkerResource;
use App\Models\User;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

class EditWorker extends EditRecord
{
    protected static string $resource = WorkerResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $newPassword = (string) ($this->data['new_account_password'] ?? '');

        unset(
            $data['create_user_account'],
            $data['account_name'],
            $data['account_email'],
            $data['account_password'],
            $data['account_password_confirmation'],
            $data['current_account_email'],
            $data['new_account_password'],
            $data['new_account_password_confirmation']
        );

        if (isset($data['user_id']) && $data['user_id']) {
            $user = User::query()->find((int) $data['user_id']);
            if ($user) {
                $user->role = User::ROLE_WORKER;
                if ($newPassword !== '') {
                    $user->password = Hash::make($newPassword);
                }
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
