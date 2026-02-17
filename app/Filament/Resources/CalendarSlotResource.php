<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CalendarSlotResource\Pages;
use App\Models\CalendarSlot;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Throwable;

class CalendarSlotResource extends Resource
{
    protected static ?string $model = CalendarSlot::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationLabel = 'Календарь';
    protected static ?string $modelLabel = 'Слот календаря';
    protected static ?string $pluralModelLabel = 'Слоты календаря';

    protected static ?string $navigationGroup = 'Операции';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Основное')
                    ->schema([
                        Forms\Components\Select::make('worker_id')
                            ->label('Работница')
                            ->relationship('worker', 'display_name')
                            ->required()
                            ->searchable()
                            ->preload(),
                        Forms\Components\Select::make('order_id')
                            ->label('Заказ')
                            ->relationship('order', 'id')
                            ->searchable()
                            ->preload()
                            ->unique(ignoreRecord: true),
                        Forms\Components\Select::make('status')
                            ->label('Статус')
                            ->options([
                                'available' => 'Доступно',
                                'reserved' => 'В резерве',
                                'booked' => 'Забронировано',
                                'blocked' => 'Заблокировано',
                            ])
                            ->required(),
                        Forms\Components\Select::make('source')
                            ->label('Источник')
                            ->options([
                                'manual' => 'Вручную',
                                'auto' => 'Авто',
                                'order' => 'Заказ',
                            ])
                            ->required(),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Время слота (МСК)')
                    ->description('Сначала выбери начало, затем длительность. Конец заполнится автоматически.')
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
                Tables\Columns\TextColumn::make('worker.display_name')
                    ->label('Работница')
                    ->searchable(),
                Tables\Columns\TextColumn::make('order_id')
                    ->label('Заказ'),
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
                        'info' => 'reserved',
                        'warning' => 'booked',
                        'danger' => 'blocked',
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
                        'reserved' => 'В резерве',
                        'booked' => 'Забронировано',
                        'blocked' => 'Заблокировано',
                    ]),
                SelectFilter::make('worker_id')
                    ->relationship('worker', 'display_name')
                    ->label('Работница'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCalendarSlots::route('/'),
            'create' => Pages\CreateCalendarSlot::route('/create'),
            'edit' => Pages\EditCalendarSlot::route('/{record}/edit'),
        ];
    }
}
