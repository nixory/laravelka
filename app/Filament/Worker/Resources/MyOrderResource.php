<?php

namespace App\Filament\Worker\Resources;

use App\Filament\Worker\Resources\MyOrderResource\Pages;
use App\Models\Order;
use App\Models\OrderDeclineRequest;
use App\Services\TelegramNotifier;
use Filament\Actions\Action as HeaderAction;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class MyOrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationGroup = 'Работа';
    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Мои заказы';

    protected static ?string $modelLabel = 'Заказ';

    protected static ?string $pluralModelLabel = 'Заказы';

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->recordUrl(fn(Order $record): string => static::getUrl('view', ['record' => $record]))
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('client_name')->searchable(),
                Tables\Columns\TextColumn::make('service_name')->searchable(),
                Tables\Columns\TextColumn::make('service_price')->money('RUB'),
                Tables\Columns\TextColumn::make('starts_at')->dateTime('d.m.Y H:i', 'Europe/Moscow'),
                Tables\Columns\TextColumn::make('ends_at')->dateTime('d.m.Y H:i', 'Europe/Moscow'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'gray' => 'new',
                        'info' => 'assigned',
                        'warning' => 'accepted',
                        'primary' => 'in_progress',
                        'success' => 'done',
                        'danger' => 'cancelled',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('decline')
                    ->label('Не могу взяться')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Отказ от заказа')
                    ->form([
                        Forms\Components\Select::make('reason_code')
                            ->label('Причина')
                            ->options([
                                'busy_now' => 'Уже занята',
                                'outside_schedule' => 'Вне моего графика',
                                'technical_issue' => 'Техническая проблема',
                                'health' => 'Проблемы со здоровьем',
                                'personal' => 'Личные обстоятельства',
                                'other' => 'Другая причина',
                            ])
                            ->required()
                            ->live(),
                        Forms\Components\Textarea::make('reason_text')
                            ->label('Комментарий')
                            ->rows(4)
                            ->required(fn(Forms\Get $get): bool => $get('reason_code') === 'other')
                            ->maxLength(2000),
                    ])
                    ->visible(function (Order $record): bool {
                        $user = Filament::auth()->user();
                        $workerId = $user?->workerProfile?->id;
                        if (!$workerId || (int) $record->worker_id !== (int) $workerId) {
                            return false;
                        }

                        if (!in_array((string) $record->status, [Order::STATUS_ASSIGNED, Order::STATUS_ACCEPTED, Order::STATUS_IN_PROGRESS], true)) {
                            return false;
                        }

                        $hasPending = OrderDeclineRequest::query()
                            ->where('order_id', $record->id)
                            ->where('worker_id', $workerId)
                            ->where('status', 'pending')
                            ->exists();

                        return !$hasPending;
                    })
                    ->action(function (Order $record, array $data): void {
                        $user = Filament::auth()->user();
                        $worker = $user?->workerProfile;
                        if (!$user || !$worker) {
                            return;
                        }

                        OrderDeclineRequest::query()->create([
                            'order_id' => $record->id,
                            'worker_id' => $worker->id,
                            'user_id' => $user->id,
                            'reason_code' => (string) $data['reason_code'],
                            'reason_text' => (string) ($data['reason_text'] ?? ''),
                            'status' => 'pending',
                        ]);

                        Notification::make()
                            ->title('Запрос отправлен администратору')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            // ── Header row ──────────────────────────────────────────────
            Infolists\Components\Section::make()
                ->schema([
                    Infolists\Components\TextEntry::make('status')
                        ->label('Статус')
                        ->badge()
                        ->formatStateUsing(fn(string $state): string => match ($state) {
                            'new' => 'Новый',
                            'assigned' => 'Назначен',
                            'accepted' => 'Принят',
                            'in_progress' => 'В работе',
                            'done' => 'Выполнен',
                            'cancelled' => 'Отменён',
                            default => $state,
                        })
                        ->color(fn(string $state): string => match ($state) {
                            'new' => 'gray',
                            'assigned' => 'info',
                            'accepted' => 'warning',
                            'in_progress' => 'primary',
                            'done' => 'success',
                            'cancelled' => 'danger',
                            default => 'gray',
                        })
                        ->size(Infolists\Components\TextEntry\TextEntrySize::Large),
                    Infolists\Components\TextEntry::make('service_price')
                        ->label('Сумма')
                        ->money('RUB')
                        ->icon('heroicon-o-banknotes'),
                    Infolists\Components\TextEntry::make('sessionRange')
                        ->label('Сессия')
                        ->icon('heroicon-o-calendar-days')
                        ->state(function (Order $record): string {
                            $date = $record->wooSessionDate();
                            $time = $record->wooSessionTime();
                            if ($date || $time) {
                                return trim(($date ?: '') . ' ' . ($time ?: ''));
                            }
                            if ($record->starts_at && $record->ends_at) {
                                return $record->starts_at->format('d.m.Y H:i') . ' – ' . $record->ends_at->format('H:i');
                            }
                            return '—';
                        }),
                    Infolists\Components\TextEntry::make('service_name')
                        ->label('Услуга')
                        ->icon('heroicon-o-sparkles'),
                    Infolists\Components\TextEntry::make('wooPlan')
                        ->label('Тариф')
                        ->state(fn(Order $record): string => $record->wooPlan() ?: '—'),
                    Infolists\Components\TextEntry::make('wooHours')
                        ->label('Часы')
                        ->state(fn(Order $record): string => $record->wooHours() ?: '—'),
                ])
                ->columns(3),

            // ── Client contacts ─────────────────────────────────────────
            Infolists\Components\Section::make('Контакты клиента')
                ->description('Используй для связи с клиентом')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->schema([
                    Infolists\Components\TextEntry::make('client_name')
                        ->label('Имя')
                        ->icon('heroicon-o-user'),
                    Infolists\Components\TextEntry::make('client_phone')
                        ->label('Телефон')
                        ->icon('heroicon-o-phone')
                        ->placeholder('—')
                        ->copyable(),
                    Infolists\Components\TextEntry::make('wooClientTelegram')
                        ->label('Telegram')
                        ->icon('heroicon-o-paper-airplane')
                        ->state(fn(Order $record): string => $record->wooClientTelegram() ?: '—')
                        ->copyable(),
                    Infolists\Components\TextEntry::make('wooClientDiscord')
                        ->label('Discord')
                        ->icon('heroicon-o-chat-bubble-oval-left')
                        ->state(fn(Order $record): string => $record->wooClientDiscord() ?: '—')
                        ->copyable(),
                    Infolists\Components\TextEntry::make('client_email')
                        ->label('Email')
                        ->icon('heroicon-o-envelope')
                        ->placeholder('—')
                        ->copyable(),
                    Infolists\Components\TextEntry::make('wooDesiredDateTime')
                        ->label('Желаемое время')
                        ->icon('heroicon-o-clock')
                        ->state(fn(Order $record): string => $record->wooDesiredDateTime() ?: '—'),
                ])
                ->columns(2),

            // ── Order details ────────────────────────────────────────────
            Infolists\Components\Section::make('Детали заказа')
                ->schema([
                    Infolists\Components\TextEntry::make('wooAddons')
                        ->label('Дополнительные услуги')
                        ->state(fn(Order $record): string => $record->wooAddons() ?: '—')
                        ->columnSpanFull(),
                ])
                ->columns(1)
                ->collapsible(),
        ]);
    }

    public static function takeOrderAction(): HeaderAction
    {
        return HeaderAction::make('takeOrder')
            ->label('Взяться за заказ')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->visible(function (Order $record): bool {
                $workerId = Filament::auth()->user()?->workerProfile?->id;

                return $workerId && (int) $record->worker_id === (int) $workerId && in_array((string) $record->status, [
                    Order::STATUS_ASSIGNED,
                    Order::STATUS_NEW,
                ], true);
            })
            ->requiresConfirmation()
            ->action(function (Order $record): void {
                $workerId = Filament::auth()->user()?->workerProfile?->id;
                if (!$workerId || (int) $record->worker_id !== (int) $workerId) {
                    return;
                }

                $record->forceFill([
                    'status' => Order::STATUS_ACCEPTED,
                    'accepted_at' => now(),
                ])->save();

                app(TelegramNotifier::class)->notifyAdminWorkerAccepted($record->fresh(['worker']));

                Notification::make()
                    ->title('Заказ принят')
                    ->success()
                    ->send();
            });
    }

    public static function declineOrderAction(): HeaderAction
    {
        return HeaderAction::make('declineOrder')
            ->label('Не смогу взять')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->form([
                Forms\Components\Select::make('reason_code')
                    ->label('Причина')
                    ->options([
                        'busy_now' => 'Уже занята',
                        'outside_schedule' => 'Вне моего графика',
                        'technical_issue' => 'Техническая проблема',
                        'health' => 'Проблемы со здоровьем',
                        'personal' => 'Личные обстоятельства',
                        'other' => 'Другая причина',
                    ])
                    ->required()
                    ->live(),
                Forms\Components\Textarea::make('reason_text')
                    ->label('Комментарий')
                    ->rows(4)
                    ->required(fn(Forms\Get $get): bool => $get('reason_code') === 'other')
                    ->maxLength(2000),
            ])
            ->visible(function (Order $record): bool {
                $workerId = Filament::auth()->user()?->workerProfile?->id;
                if (!$workerId || (int) $record->worker_id !== (int) $workerId) {
                    return false;
                }

                return in_array((string) $record->status, [
                    Order::STATUS_NEW,
                    Order::STATUS_ASSIGNED,
                    Order::STATUS_ACCEPTED,
                    Order::STATUS_IN_PROGRESS,
                ], true);
            })
            ->action(function (Order $record, array $data): void {
                $user = Filament::auth()->user();
                $worker = $user?->workerProfile;

                if (!$user || !$worker || (int) $record->worker_id !== (int) $worker->id) {
                    return;
                }

                $declineRequest = null;

                DB::transaction(function () use ($record, $data, $worker, $user, &$declineRequest): void {
                    $declineRequest = OrderDeclineRequest::query()->create([
                        'order_id' => $record->id,
                        'worker_id' => $worker->id,
                        'user_id' => $user->id,
                        'reason_code' => (string) $data['reason_code'],
                        'reason_text' => (string) ($data['reason_text'] ?? ''),
                        'status' => 'pending',
                    ]);

                    $record->forceFill([
                        'worker_id' => null,
                        'status' => Order::STATUS_NEW,
                        'assigned_by_user_id' => null,
                        'accepted_at' => null,
                    ])->save();
                });

                if ($declineRequest instanceof OrderDeclineRequest) {
                    app(TelegramNotifier::class)->notifyAdminWorkerDeclined(
                        $record->fresh(['worker']),
                        $declineRequest->fresh(['worker'])
                    );
                }

                Notification::make()
                    ->title('Запрос отправлен, заказ откреплен')
                    ->success()
                    ->send();
            });
    }

    public static function supportAction(): HeaderAction
    {
        return HeaderAction::make('support')
            ->label('Написать в поддержку')
            ->icon('heroicon-o-lifebuoy')
            ->color('gray')
            ->url((string) config('services.support.worker_telegram_url'), shouldOpenInNewTab: true);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $workerId = Filament::auth()->user()?->workerProfile?->id;

        if (!$workerId) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('worker_id', $workerId);
    }

    public static function canViewAny(): bool
    {
        return Filament::auth()->check() && Filament::auth()->user()?->workerProfile !== null;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canView($record): bool
    {
        $workerId = Filament::auth()->user()?->workerProfile?->id;

        return $workerId && (int) $record->worker_id === (int) $workerId;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMyOrders::route('/'),
            'view' => Pages\ViewMyOrder::route('/{record}'),
        ];
    }
}
