<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderDeclineRequestResource\Pages;
use App\Models\Order;
use App\Models\OrderDeclineRequest;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
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

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('order_id')->label('Заказ'),
                Tables\Columns\TextColumn::make('worker.display_name')->label('Работница')->searchable(),
                Tables\Columns\TextColumn::make('reason_code')->badge(),
                Tables\Columns\TextColumn::make('reason_text')->wrap()->limit(50),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'approved',
                        'danger' => 'rejected',
                    ]),
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
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (OrderDeclineRequest $record): bool => $record->status === 'pending')
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
                Action::make('reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (OrderDeclineRequest $record): bool => $record->status === 'pending')
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
            'create' => Pages\CreateOrderDeclineRequest::route('/create'),
            'edit' => Pages\EditOrderDeclineRequest::route('/{record}/edit'),
        ];
    }
}
