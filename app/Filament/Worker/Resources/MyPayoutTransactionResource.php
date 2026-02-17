<?php

namespace App\Filament\Worker\Resources;

use App\Filament\Worker\Resources\MyPayoutTransactionResource\Pages;
use App\Models\PayoutTransaction;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MyPayoutTransactionResource extends Resource
{
    protected static ?string $model = PayoutTransaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Выплаты';

    protected static ?string $modelLabel = 'Транзакция выплаты';

    protected static ?string $pluralModelLabel = 'Транзакции выплат';

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('occurred_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('occurred_at')
                    ->label('Дата')
                    ->dateTime('d.m.Y H:i', 'Europe/Moscow')
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->colors([
                        'success' => 'credit',
                        'danger' => 'debit',
                    ]),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'confirmed',
                        'danger' => 'cancelled',
                    ]),
                Tables\Columns\TextColumn::make('amount')
                    ->money(fn (PayoutTransaction $record): string => $record->currency ?: 'RUB'),
                Tables\Columns\TextColumn::make('description')
                    ->wrap()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('order_id')
                    ->label('Заказ')
                    ->toggleable(),
            ])
            ->actions([])
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
            'index' => Pages\ListMyPayoutTransactions::route('/'),
        ];
    }
}
