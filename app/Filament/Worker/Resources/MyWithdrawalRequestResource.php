<?php

namespace App\Filament\Worker\Resources;

use App\Filament\Worker\Resources\MyWithdrawalRequestResource\Pages;
use App\Models\WithdrawalRequest;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MyWithdrawalRequestResource extends Resource
{
    protected static ?string $model = WithdrawalRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';
    protected static ?string $navigationGroup = 'Финансы';
    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Заявки на вывод';

    protected static ?string $modelLabel = 'Заявка на вывод';

    protected static ?string $pluralModelLabel = 'Заявки на вывод';

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('requested_at')->dateTime('d.m.Y H:i', 'Europe/Moscow')->label('Запрошено'),
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
                Tables\Columns\TextColumn::make('admin_note')->wrap()->toggleable(),
                Tables\Columns\TextColumn::make('processed_at')->dateTime('d.m.Y H:i', 'Europe/Moscow')->toggleable(),
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
            'index' => Pages\ListMyWithdrawalRequests::route('/'),
        ];
    }
}
