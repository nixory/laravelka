<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WithdrawalRequestResource\Pages;
use App\Models\PayoutTransaction;
use App\Models\WithdrawalRequest;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class WithdrawalRequestResource extends Resource
{
    protected static ?string $model = WithdrawalRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-wallet';
    protected static ?string $navigationLabel = 'Заявки на вывод';
    protected static ?string $modelLabel = 'Заявка на вывод';
    protected static ?string $pluralModelLabel = 'Заявки на вывод';

    protected static ?string $navigationGroup = 'Финансы';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('worker_id')->relationship('worker', 'display_name')->required()->searchable(),
            Forms\Components\Select::make('user_id')->relationship('user', 'email')->required()->searchable(),
            Forms\Components\TextInput::make('amount')->numeric()->required(),
            Forms\Components\TextInput::make('currency')->default('RUB')->required(),
                Forms\Components\Select::make('status')
                    ->options([
                        'pending' => 'Ожидает',
                        'approved' => 'Подтверждено',
                        'rejected' => 'Отклонено',
                        'paid' => 'Выплачено',
                        'cancelled' => 'Отменено',
                    ])
                ->required(),
            Forms\Components\TextInput::make('payment_method'),
            Forms\Components\TextInput::make('payment_details'),
            Forms\Components\Textarea::make('note')->columnSpanFull(),
            Forms\Components\Textarea::make('admin_note')->columnSpanFull(),
            Forms\Components\DateTimePicker::make('requested_at'),
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
                Tables\Columns\TextColumn::make('worker.display_name')->label('Работница')->searchable(),
                Tables\Columns\TextColumn::make('amount')->money(fn (WithdrawalRequest $record): string => $record->currency ?: 'RUB'),
                Tables\Columns\TextColumn::make('payment_method')->badge(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'warning' => 'pending',
                        'info' => 'approved',
                        'success' => 'paid',
                        'danger' => 'rejected',
                        'gray' => 'cancelled',
                    ]),
                Tables\Columns\TextColumn::make('requested_at')->dateTime('d.m.Y H:i', 'Europe/Moscow'),
                Tables\Columns\TextColumn::make('processed_at')->dateTime('d.m.Y H:i', 'Europe/Moscow')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'pending' => 'Ожидает',
                    'approved' => 'Подтверждено',
                    'rejected' => 'Отклонено',
                    'paid' => 'Выплачено',
                    'cancelled' => 'Отменено',
                ]),
            ])
            ->actions([
                Action::make('approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('info')
                    ->visible(fn (WithdrawalRequest $record): bool => $record->status === 'pending')
                    ->action(function (WithdrawalRequest $record): void {
                        $record->update([
                            'status' => 'approved',
                            'processed_at' => now(),
                            'processed_by_user_id' => Filament::auth()->id(),
                        ]);

                        Notification::make()->title('Заявка подтверждена')->success()->send();
                    }),
                Action::make('reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (WithdrawalRequest $record): bool => in_array($record->status, ['pending', 'approved'], true))
                    ->form([
                        Forms\Components\Textarea::make('admin_note')
                            ->label('Причина отклонения')
                            ->required(),
                    ])
                    ->action(function (WithdrawalRequest $record, array $data): void {
                        $record->update([
                            'status' => 'rejected',
                            'admin_note' => (string) ($data['admin_note'] ?? ''),
                            'processed_at' => now(),
                            'processed_by_user_id' => Filament::auth()->id(),
                        ]);

                        Notification::make()->title('Заявка отклонена')->warning()->send();
                    }),
                Action::make('markPaid')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->visible(fn (WithdrawalRequest $record): bool => in_array($record->status, ['approved', 'pending'], true))
                    ->action(function (WithdrawalRequest $record): void {
                        $record->update([
                            'status' => 'paid',
                            'processed_at' => now(),
                            'processed_by_user_id' => Filament::auth()->id(),
                        ]);

                        PayoutTransaction::query()->create([
                            'worker_id' => $record->worker_id,
                            'type' => 'debit',
                            'status' => 'confirmed',
                            'amount' => $record->amount,
                            'currency' => $record->currency ?: 'RUB',
                            'description' => 'Withdrawal payout #' . $record->id,
                            'meta' => [
                                'withdrawal_request_id' => $record->id,
                                'payment_method' => $record->payment_method,
                            ],
                            'occurred_at' => now(),
                        ]);

                        Notification::make()->title('Отмечено как выплачено')->success()->send();
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
            'index' => Pages\ListWithdrawalRequests::route('/'),
            'create' => Pages\CreateWithdrawalRequest::route('/create'),
            'edit' => Pages\EditWithdrawalRequest::route('/{record}/edit'),
        ];
    }
}
