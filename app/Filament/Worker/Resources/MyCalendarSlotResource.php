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

    protected static ?string $navigationLabel = 'My calendar';

    protected static ?string $modelLabel = 'Calendar slot';

    protected static ?string $pluralModelLabel = 'Calendar slots';

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
                        'available' => 'Available',
                        'blocked' => 'Blocked',
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
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('ends_at')
                    ->dateTime()
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
                        'available' => 'Available',
                        'blocked' => 'Blocked',
                        'booked' => 'Booked',
                        'reserved' => 'Reserved',
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

