<?php

namespace App\Filament\Worker\Pages;

use App\Models\PayoutTransaction;
use App\Models\WithdrawalRequest;
use App\Models\Worker;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class MyPayoutsPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationLabel = 'Мои выплаты';
    protected static ?string $navigationGroup = 'Финансы';
    protected static ?int $navigationSort = 5;
    protected static string $view = 'filament.worker.pages.my-payouts-page';
    protected static ?string $title = 'Мои выплаты';

    public function getWorker(): ?Worker
    {
        return Filament::auth()->user()?->workerProfile;
    }

    public function getAvailableBalance(): string
    {
        $worker = $this->getWorker();
        if (!$worker) {
            return '0.00 ₽';
        }
        return number_format((float) $worker->availableWithdrawalBalance(), 2, '.', ' ') . ' ₽';
    }

    public function getExpectedBalance(): string
    {
        $worker = $this->getWorker();
        if (!$worker) {
            return '0.00 ₽';
        }
        // Sum of pending payout transactions (earned but not yet confirmed)
        $pending = PayoutTransaction::query()
            ->where('worker_id', $worker->id)
            ->where('status', 'pending')
            ->where('type', 'credit')
            ->sum('amount');
        return number_format((float) $pending, 2, '.', ' ') . ' ₽';
    }

    public function getInProcessBalance(): string
    {
        $worker = $this->getWorker();
        if (!$worker) {
            return '0.00 ₽';
        }
        $inProcess = WithdrawalRequest::query()
            ->where('worker_id', $worker->id)
            ->whereIn('status', ['pending', 'approved'])
            ->sum('amount');
        return number_format((float) $inProcess, 2, '.', ' ') . ' ₽';
    }

    public function getRecentTransactions(): \Illuminate\Database\Eloquent\Collection
    {
        $worker = $this->getWorker();
        if (!$worker) {
            return collect();
        }
        return PayoutTransaction::query()
            ->where('worker_id', $worker->id)
            ->orderByDesc('occurred_at')
            ->limit(10)
            ->get();
    }

    public function getRecentWithdrawals(): \Illuminate\Database\Eloquent\Collection
    {
        $worker = $this->getWorker();
        if (!$worker) {
            return collect();
        }
        return WithdrawalRequest::query()
            ->where('worker_id', $worker->id)
            ->orderByDesc('requested_at')
            ->limit(5)
            ->get();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('withdraw')
                ->label('Вывести средства')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('success')
                ->form([
                    TextInput::make('amount')
                        ->label('Сумма (₽)')
                        ->numeric()
                        ->required()
                        ->minValue(1)
                        ->default(function (): float {
                            $worker = $this->getWorker();
                            return $worker ? (float) $worker->availableWithdrawalBalance() : 0;
                        }),
                    Select::make('payment_method')
                        ->label('Способ получения')
                        ->options([
                            'sbp' => 'СБП (по номеру телефона)',
                            'card' => 'Банковская карта',
                            'usdt' => 'USDT (TRC-20)',
                            'other' => 'Другое',
                        ])
                        ->required()
                        ->live(),
                    TextInput::make('payment_details')
                        ->label('Реквизиты')
                        ->required()
                        ->placeholder('Номер телефона / номер карты / адрес кошелька')
                        ->maxLength(500),
                    Textarea::make('note')
                        ->label('Комментарий (необязательно)')
                        ->rows(2)
                        ->maxLength(1000),
                ])
                ->modalHeading('Заявка на вывод')
                ->modalDescription('Заявка будет обработана администратором в течение 24 часов.')
                ->action(function (array $data): void {
                    $user = Filament::auth()->user();
                    $worker = $user?->workerProfile;

                    if (!$user || !$worker) {
                        Notification::make()->title('Ошибка: профиль не найден')->danger()->send();
                        return;
                    }

                    $available = (float) $worker->availableWithdrawalBalance();
                    if ((float) $data['amount'] > $available) {
                        Notification::make()
                            ->title('Недостаточно средств')
                            ->body("Доступно: {$available} ₽")
                            ->danger()
                            ->send();
                        return;
                    }

                    WithdrawalRequest::create([
                        'worker_id' => $worker->id,
                        'user_id' => $user->id,
                        'amount' => $data['amount'],
                        'currency' => 'RUB',
                        'status' => 'pending',
                        'payment_method' => $data['payment_method'],
                        'payment_details' => $data['payment_details'],
                        'note' => $data['note'] ?? null,
                        'requested_at' => now(),
                    ]);

                    Notification::make()
                        ->title('Заявка на вывод отправлена')
                        ->body('Администратор обработает её в течение 24 часов.')
                        ->success()
                        ->send();
                }),
        ];
    }

    public static function canAccess(): bool
    {
        return Filament::auth()->check() && Filament::auth()->user()?->workerProfile !== null;
    }
}
