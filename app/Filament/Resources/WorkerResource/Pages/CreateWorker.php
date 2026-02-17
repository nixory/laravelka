<?php

namespace App\Filament\Resources\WorkerResource\Pages;

use App\Filament\Resources\WorkerResource;
use App\Models\User;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class CreateWorker extends CreateRecord
{
    protected static string $resource = WorkerResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $createAccount = (bool) ($data['create_user_account'] ?? false);
        $selectedUserId = isset($data['user_id']) ? (int) $data['user_id'] : null;

        unset(
            $data['create_user_account'],
            $data['account_name'],
            $data['account_email'],
            $data['account_password'],
            $data['account_password_confirmation']
        );

        if ($selectedUserId) {
            $user = User::query()->find($selectedUserId);
            if (! $user) {
                throw ValidationException::withMessages([
                    'user_id' => 'Selected user was not found.',
                ]);
            }
            $user->role = User::ROLE_WORKER;
            $user->save();
            $data['user_id'] = $user->id;
        } elseif ($createAccount) {
            $email = (string) ($this->data['account_email'] ?? '');
            $password = (string) ($this->data['account_password'] ?? '');
            $name = (string) ($this->data['account_name'] ?? $data['display_name'] ?? '');

            if ($email === '' || $password === '') {
                throw ValidationException::withMessages([
                    'account_email' => 'Email and password are required to create worker account.',
                ]);
            }

            $user = User::query()->create([
                'name' => $name !== '' ? $name : (string) ($data['display_name'] ?? 'Работница'),
                'email' => $email,
                'password' => Hash::make($password),
                'role' => User::ROLE_WORKER,
            ]);

            $data['user_id'] = $user->id;
        } else {
            $data['user_id'] = null;
        }

        return static::getModel()::query()->create($data);
    }
}
