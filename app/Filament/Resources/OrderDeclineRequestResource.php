<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderDeclineRequestResource\Pages;
use App\Models\Order;
use App\Models\OrderDeclineRequest;
use App\Models\Worker;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class OrderDeclineRequestResource extends Resource
{
    protected static ?string $model = OrderDeclineRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';
    protected static ?string $navigationLabel = 'Отказы от заказов';
    protected static ?string $modelLabel = 'Запрос отказа';
    protected static ?string $pluralModelLabel = 'Запросы отказа';

    protected static ?string $navigationGroup = 'Операции';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('order_id')->relationship('order', 'id')->required()->searchable(),
            Forms\Components\Select::make('worker_id')->relationship('worker', 'display_name')->required()->searchable(),
            Forms\Components\Select::make('user_id')->relationship('user', 'email')->required()->searchable(),
            Forms\Components\TextInput::make('reason_code')->required(),
            Forms\Components\Textarea::make('reason_text')->columnSpanFull(),
            Forms\Components\Select::make('status')
                ->options([
                    'pending' => 'Ожидает',
                    'approved' => 'Подтверждено',
                    'rejected' => 'Отклонено',
                ])
                ->required(),
            Forms\Components\Textarea::make('admin_note')->columnSpanFull(),
            Forms\Components\DateTimePicker::make('processed_at'),
            Forms\Components\Select::make('processed_by_user_id')->relationship('processedBy', 'email')->searchable(),
        ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make()
                ->schema([
                    Infolists\Components\TextEntry::make('status')
                        ->label('Статус')
                        ->badge()
                        ->formatStateUsing(fn(string $state): string => match ($state) {
                            'pending' => 'Ожидает',
                            'approved' => 'Подтверждено',
                            'rejected' => 'Отклонено',
                            default => $state,
                        })
                        ->colors([
                            'warning' => 'pending',
                            'success' => 'approved',
                            'danger' => 'rejected',
                        ])
                        ->size(Infolists\Components\TextEntry\TextEntrySize::Large),

                    Infolists\Components\TextEntry::make('worker.display_name')
                        ->label('Работница')
                        ->icon('heroicon-o-user'),

                    Infolists\Components\TextEntry::make('order_id')
                        ->label('Заказ #')
                        ->icon('heroicon-o-clipboard-document-list'),

                    Infolists\Components\TextEntry::make('created_at')
                        ->label('Дата отказа')
                        ->dateTime('d.m.Y H:i', 'Europe/Moscow')
                        ->icon('heroicon-o-clock'),
                ])
                ->columns(4),

            Infolists\Components\Section::make('Причина отказа')
                ->icon('heroicon-o-chat-bubble-left-ellipsis')
                ->schema([
                    Infolists\Components\TextEntry::make('reason_code')
                        ->label('Код причины')
                        ->badge()
                        ->formatStateUsing(fn(string $state): string => match ($state) {
                            'busy' => 'Занята',
                            'sick' => 'Болезнь',
                            'personal' => 'Личные причины',
                            'technical' => 'Технические проблемы',
                            'client' => 'Проблема с клиентом',
                            'other' => 'Другое',
                            default => $state,
                        })
                        ->color('danger'),
                    Infolists\Components\TextEntry::make('reason_text')
                        ->label('Комментарий работницы')
                        ->placeholder('—')
                        ->columnSpanFull(),
                ])
                ->columns(2),

            Infolists\Components\Section::make('Решение администратора')
                ->icon('heroicon-o-shield-check')
                ->schema([
                    Infolists\Components\TextEntry::make('admin_note')
                        ->label('Комментарий')
                        ->placeholder('—')
                        ->columnSpanFull(),
                    Infolists\Components\TextEntry::make('processedBy.email')
                        ->label('Обработал')
                        ->placeholder('—'),
                    Infolists\Components\TextEntry::make('processed_at')
                        ->label('Дата обработки')
                        ->dateTime('d.m.Y H:i', 'Europe/Moscow')
                        ->placeholder('—'),
                ])
                ->columns(2)
                ->collapsible(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('order_id')
                    ->label('Заказ')
                    ->url(fn(OrderDeclineRequest $record): string => route('filament.admin.resources.orders.view', ['record' => $record->order_id]))
                    ->openUrlInNewTab()
                    ->color('primary'),
                Tables\Columns\TextColumn::make('worker.display_name')->label('Работница')->searchable(),
                Tables\Columns\TextColumn::make('reason_code')
                    ->label('Причина')
                    ->badge()
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'busy' => 'Занята',
                        'sick' => 'Болезнь',
                        'personal' => 'Личные причины',
                        'technical' => 'Технические проблемы',
                        'client' => 'Проблема с клиентом',
                        'other' => 'Другое',
                        default => $state,
                    })
                    ->color('danger'),
                Tables\Columns\TextColumn::make('reason_text')
                    ->label('Комментарий')
                    ->wrap()
                    ->limit(50)
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'pending' => 'Ожидает',
                        'approved' => 'Подтверждено',
                        'rejected' => 'Отклонено',
                        default => $state,
                    })
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'approved',
                        'danger' => 'rejected',
                    ]),
                Tables\Columns\TextColumn::make('processedBy.email')
                    ->label('Обработал')
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime('d.m.Y H:i', 'Europe/Moscow'),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'pending' => 'Ожидает',
                    'approved' => 'Подтверждено',
                    'rejected' => 'Отклонено',
                ]),
            ])
            ->actions([
                Action::make('approve')
                    ->label('Одобрить отказ')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn(OrderDeclineRequest $record): bool => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->modalHeading('Одобрить отказ?')
                    ->modalDescription('Заказ будет возвращён в очередь без работницы.')
                    ->action(function (OrderDeclineRequest $record): void {
                        $record->update([
                            'status' => 'approved',
                            'processed_at' => now(),
                            'processed_by_user_id' => Filament::auth()->id(),
                        ]);

                        $order = Order::query()->find($record->order_id);
                        if ($order && (int) $order->worker_id === (int) $record->worker_id) {
                            $order->update([
                                'worker_id' => null,
                                'status' => Order::STATUS_NEW,
                                'assigned_by_user_id' => null,
                            ]);
                        }

                        Notification::make()->title('Отказ подтверждён, заказ возвращён в очередь')->success()->send();
                    }),

                Action::make('reassignOrder')
                    ->label('Перекинуть заказ')
                    ->icon('heroicon-o-arrows-right-left')
                    ->color('warning')
                    ->visible(fn(OrderDeclineRequest $record): bool => $record->status === 'pending')
                    ->form([
                        Forms\Components\Select::make('worker_id')
                            ->label('Новая работница')
                            ->options(
                                Worker::query()
                                    ->where('is_active', true)
                                    ->orderBy('display_name')
                                    ->pluck('display_name', 'id')
                            )
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (OrderDeclineRequest $record, array $data): void {
                        // Одобряем отказ
                        $record->update([
                            'status' => 'approved',
                            'processed_at' => now(),
                            'processed_by_user_id' => Filament::auth()->id(),
                        ]);

                        // Переназначаем заказ новой работнице
                        $order = Order::query()->find($record->order_id);
                        if ($order) {
                            $order->update([
                                'worker_id' => $data['worker_id'],
                                'status' => Order::STATUS_ASSIGNED,
                                'assigned_by_user_id' => Filament::auth()->id(),
                            ]);
                        }

                        $worker = Worker::query()->find($data['worker_id']);
                        Notification::make()
                            ->title('Заказ перекинут на: ' . ($worker?->display_name ?? '—'))
                            ->success()
                            ->send();
                    }),

                Action::make('reject')
                    ->label('Отклонить')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn(OrderDeclineRequest $record): bool => $record->status === 'pending')
                    ->form([
                        Forms\Components\Textarea::make('admin_note')
                            ->label('Комментарий')
                            ->required(),
                    ])
                    ->action(function (OrderDeclineRequest $record, array $data): void {
                        $record->update([
                            'status' => 'rejected',
                            'admin_note' => (string) ($data['admin_note'] ?? ''),
                            'processed_at' => now(),
                            'processed_by_user_id' => Filament::auth()->id(),
                        ]);

                        Notification::make()->title('Заявка на отказ отклонена')->warning()->send();
                    }),

                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrderDeclineRequests::route('/'),
            'view' => Pages\ViewOrderDeclineRequest::route('/{record}'),
            'create' => Pages\CreateOrderDeclineRequest::route('/create'),
            'edit' => Pages\EditOrderDeclineRequest::route('/{record}/edit'),
        ];
    }
}
