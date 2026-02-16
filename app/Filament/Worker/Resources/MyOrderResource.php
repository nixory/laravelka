<?php

namespace App\Filament\Worker\Resources;

use App\Filament\Worker\Resources\MyOrderResource\Pages;
use App\Models\Order;
use App\Models\OrderDeclineRequest;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MyOrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'My orders';

    protected static ?string $modelLabel = 'Order';

    protected static ?string $pluralModelLabel = 'Orders';

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('client_name')->searchable(),
                Tables\Columns\TextColumn::make('service_name')->searchable(),
                Tables\Columns\TextColumn::make('service_price')->money('RUB'),
                Tables\Columns\TextColumn::make('starts_at')->dateTime(),
                Tables\Columns\TextColumn::make('ends_at')->dateTime(),
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
                            ->required(fn (Forms\Get $get): bool => $get('reason_code') === 'other')
                            ->maxLength(2000),
                    ])
                    ->visible(function (Order $record): bool {
                        $user = Filament::auth()->user();
                        $workerId = $user?->workerProfile?->id;
                        if (! $workerId || (int) $record->worker_id !== (int) $workerId) {
                            return false;
                        }

                        if (! in_array((string) $record->status, [Order::STATUS_ASSIGNED, Order::STATUS_ACCEPTED, Order::STATUS_IN_PROGRESS], true)) {
                            return false;
                        }

                        $hasPending = OrderDeclineRequest::query()
                            ->where('order_id', $record->id)
                            ->where('worker_id', $workerId)
                            ->where('status', 'pending')
                            ->exists();

                        return ! $hasPending;
                    })
                    ->action(function (Order $record, array $data): void {
                        $user = Filament::auth()->user();
                        $worker = $user?->workerProfile;
                        if (! $user || ! $worker) {
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

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $workerId = Filament::auth()->user()?->workerProfile?->id;

        if (! $workerId) {
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
        ];
    }
}

