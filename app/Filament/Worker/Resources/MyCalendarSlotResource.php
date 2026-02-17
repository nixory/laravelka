<?php

namespace App\Filament\Worker\Resources;

use App\Filament\Worker\Resources\MyCalendarSlotResource\Pages;
use App\Models\CalendarSlot;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

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
                Forms\Components\Section::make('Время слота (МСК)')
                    ->description('Выбери начало и длительность. Конец заполнится автоматически.')
                    ->schema([
                        Forms\Components\DateTimePicker::make('starts_at')
                            ->label('Начало')
                            ->required()
                            ->timezone('Europe/Moscow')
                            ->displayFormat('d.m.Y H:i')
                            ->seconds(false)
                            ->native(false)
                            ->live()
                            ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get): void {
                                self::applyDurationPreset($set, $get);
                            }),
                        Forms\Components\Select::make('duration_preset')
                            ->label('Длительность')
                            ->dehydrated(false)
                            ->options([
                                '30' => '30 мин',
                                '60' => '1 час',
                                '90' => '1.5 часа',
                                '120' => '2 часа',
                                '180' => '3 часа',
                                '240' => '4 часа',
                                '480' => '8 часов',
                            ])
                            ->default('60')
                            ->live()
                            ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get): void {
                                self::applyDurationPreset($set, $get);
                            }),
                        Forms\Components\DateTimePicker::make('ends_at')
                            ->label('Окончание')
                            ->required()
                            ->timezone('Europe/Moscow')
                            ->displayFormat('d.m.Y H:i')
                            ->seconds(false)
                            ->native(false)
                            ->after('starts_at'),
                    ])
                    ->columns(3),
                Forms\Components\Select::make('status')
                    ->label('Статус')
                    ->options([
                        'available' => 'Доступно',
                        'blocked' => 'Заблокировано',
                    ])
                    ->default('available')
                    ->required(),
                Forms\Components\Textarea::make('notes')
                    ->label('Комментарий')
                    ->columnSpanFull(),
            ]);
    }

    private static function applyDurationPreset(Forms\Set $set, Forms\Get $get): void
    {
        $startsAt = $get('starts_at');
        $duration = (int) ($get('duration_preset') ?? 0);

        if (! $startsAt || $duration <= 0) {
            return;
        }

        try {
            $start = Carbon::parse((string) $startsAt);
        } catch (Throwable) {
            return;
        }

        $set('ends_at', $start->copy()->addMinutes($duration)->format('Y-m-d H:i:s'));
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
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'available' => 'Доступно',
                        'reserved' => 'В резерве',
                        'booked' => 'Забронировано',
                        'blocked' => 'Заблокировано',
                        default => $state,
                    })
                    ->colors([
                        'success' => 'available',
                        'danger' => 'blocked',
                        'warning' => 'booked',
                        'info' => 'reserved',
                    ]),
                Tables\Columns\TextColumn::make('source')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'manual' => 'Вручную',
                        'auto' => 'Авто',
                        'order' => 'Заказ',
                        default => $state,
                    })
                    ->colors([
                        'gray' => 'manual',
                        'info' => 'auto',
                        'warning' => 'order',
                    ]),
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
