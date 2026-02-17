<?php

namespace App\Filament\Worker\Resources\MyPayoutTransactionResource\Pages;

use App\Filament\Worker\Resources\MyPayoutTransactionResource;
use App\Models\Worker;
use App\Models\WithdrawalRequest;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListMyPayoutTransactions extends ListRecords
{
    private const MIN_WITHDRAWAL_AMOUNT = 2000;

    protected static string $resource = MyPayoutTransactionResource::class;

    protected function getHeaderActions(): array
    {
        $availableBalance = $this->getAvailableBalance();

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
                        ->default($availableBalance > 0 ? $availableBalance : null)
                        ->minValue(self::MIN_WITHDRAWAL_AMOUNT)
                        ->maxValue($availableBalance > 0 ? $availableBalance : null)
                        ->helperText(sprintf(
                            'Минимум %d RUB. Максимум: %.2f RUB (весь доступный баланс).',
                            self::MIN_WITHDRAWAL_AMOUNT,
                            $availableBalance
                        )),
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

                    $availableBalance = $worker->availableWithdrawalBalance();
                    $requestedAmount = round((float) $data['amount'], 2);

                    if ($availableBalance < self::MIN_WITHDRAWAL_AMOUNT) {
                        Notification::make()
                            ->title('Недостаточно средств для вывода')
                            ->body(sprintf(
                                'Доступно %.2f RUB. Минимум для вывода: %d RUB.',
                                $availableBalance,
                                self::MIN_WITHDRAWAL_AMOUNT
                            ))
                            ->danger()
                            ->send();

                        return;
                    }

                    if ($requestedAmount < self::MIN_WITHDRAWAL_AMOUNT) {
                        Notification::make()
                            ->title('Сумма вывода слишком мала')
                            ->body(sprintf('Минимальная сумма вывода: %d RUB.', self::MIN_WITHDRAWAL_AMOUNT))
                            ->danger()
                            ->send();

                        return;
                    }

                    if ($requestedAmount > $availableBalance) {
                        Notification::make()
                            ->title('Сумма вывода превышает доступный баланс')
                            ->body(sprintf('Максимально доступно: %.2f RUB.', $availableBalance))
                            ->danger()
                            ->send();

                        return;
                    }

                    WithdrawalRequest::query()->create([
                        'worker_id' => $worker->id,
                        'user_id' => $user->id,
                        'amount' => $requestedAmount,
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

    public function getSubheading(): ?string
    {
        return sprintf(
            'Баланс: %.2f RUB | Доступно к выводу: %.2f RUB | Мин. вывод: %d RUB',
            $this->getConfirmedBalance(),
            $this->getAvailableBalance(),
            self::MIN_WITHDRAWAL_AMOUNT
        );
    }

    private function getWorker(): ?Worker
    {
        $user = Filament::auth()->user();

        if (! $user) {
            return null;
        }

        return $user->workerProfile;
    }

    private function getConfirmedBalance(): float
    {
        $worker = $this->getWorker();

        if (! $worker) {
            return 0;
        }

        return $worker->confirmedPayoutBalance();
    }

    private function getAvailableBalance(): float
    {
        $worker = $this->getWorker();

        if (! $worker) {
            return 0;
        }

        return $worker->availableWithdrawalBalance();
    }
}
