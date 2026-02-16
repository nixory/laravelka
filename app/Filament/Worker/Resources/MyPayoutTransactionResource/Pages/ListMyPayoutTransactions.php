<?php

namespace App\Filament\Worker\Resources\MyPayoutTransactionResource\Pages;

use App\Filament\Worker\Resources\MyPayoutTransactionResource;
use App\Models\WithdrawalRequest;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListMyPayoutTransactions extends ListRecords
{
    protected static string $resource = MyPayoutTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('requestWithdrawal')
                ->label('Запросить вывод')
                ->icon('heroicon-o-arrow-up-on-square')
                ->color('warning')
                ->form([
                    Forms\Components\TextInput::make('amount')
                        ->label('Сумма')
                        ->numeric()
                        ->required()
                        ->minValue(1),
                    Forms\Components\Select::make('payment_method')
                        ->label('Способ выплаты')
                        ->options([
                            'card' => 'Банковская карта',
                            'sbp' => 'СБП',
                            'crypto' => 'Crypto',
                            'other' => 'Другое',
                        ])
                        ->required(),
                    Forms\Components\TextInput::make('payment_details')
                        ->label('Реквизиты')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\Textarea::make('note')
                        ->label('Комментарий')
                        ->rows(3)
                        ->maxLength(2000),
                ])
                ->action(function (array $data): void {
                    $user = Filament::auth()->user();
                    $worker = $user?->workerProfile;

                    if (! $user || ! $worker) {
                        return;
                    }

                    WithdrawalRequest::query()->create([
                        'worker_id' => $worker->id,
                        'user_id' => $user->id,
                        'amount' => (float) $data['amount'],
                        'currency' => 'RUB',
                        'status' => 'pending',
                        'payment_method' => (string) $data['payment_method'],
                        'payment_details' => (string) $data['payment_details'],
                        'note' => (string) ($data['note'] ?? ''),
                        'requested_at' => now(),
                    ]);

                    Notification::make()
                        ->title('Запрос на вывод отправлен')
                        ->success()
                        ->send();
                }),
        ];
    }
}

