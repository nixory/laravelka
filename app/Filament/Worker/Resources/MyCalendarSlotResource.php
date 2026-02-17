<?php

namespace App\Filament\Worker\Resources;

use App\Filament\Worker\Resources\MyCalendarSlotResource\Pages;
use App\Models\CalendarSlot;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MyCalendarSlotResource extends Resource
{
    protected static ?string $model = CalendarSlot::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationGroup = 'Работа';
    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Мой календарь';

    protected static ?string $modelLabel = 'Слот календаря';

    protected static ?string $pluralModelLabel = 'Слоты календаря';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\DateTimePicker::make('starts_at')
                    ->required()
                    ->seconds(false),
                Forms\Components\DateTimePicker::make('ends_at')
                    ->required()
                    ->seconds(false)
                    ->after('starts_at'),
                Forms\Components\Select::make('status')
                    ->options([
                        'available' => 'Доступно',
                        'blocked' => 'Заблокировано',
                    ])
                    ->default('available')
                    ->required(),
                Forms\Components\Textarea::make('notes')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('starts_at')
            ->columns([
                Tables\Columns\TextColumn::make('starts_at')
                    ->dateTime('d.m.Y H:i', 'Europe/Moscow')
                    ->sortable(),
                Tables\Columns\TextColumn::make('ends_at')
                    ->dateTime('d.m.Y H:i', 'Europe/Moscow')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'success' => 'available',
                        'danger' => 'blocked',
                        'warning' => 'booked',
                        'info' => 'reserved',
                    ]),
                Tables\Columns\TextColumn::make('source')
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'available' => 'Доступно',
                        'blocked' => 'Заблокировано',
                        'booked' => 'Забронировано',
                        'reserved' => 'В резерве',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn (CalendarSlot $record): bool => in_array($record->status, ['available', 'blocked'], true)),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn (): bool => true),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = Filament::auth()->user();
        $workerId = $user?->workerProfile?->id;

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
        return self::canViewAny();
    }

    public static function canDelete($record): bool
    {
        return in_array($record->status, ['available', 'blocked'], true);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMyCalendarSlots::route('/'),
            'create' => Pages\CreateMyCalendarSlot::route('/create'),
            'edit' => Pages\EditMyCalendarSlot::route('/{record}/edit'),
        ];
    }
}
