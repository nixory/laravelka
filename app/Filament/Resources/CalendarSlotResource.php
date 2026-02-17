<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CalendarSlotResource\Pages;
use App\Models\CalendarSlot;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CalendarSlotResource extends Resource
{
    protected static ?string $model = CalendarSlot::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = 'Операции';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('worker_id')
                    ->relationship('worker', 'display_name')
                    ->required()
                    ->searchable()
                    ->preload(),
                Forms\Components\Select::make('order_id')
                    ->relationship('order', 'id')
                    ->searchable()
                    ->preload()
                    ->unique(ignoreRecord: true),
                Forms\Components\DateTimePicker::make('starts_at')
                    ->required(),
                Forms\Components\DateTimePicker::make('ends_at')
                    ->required(),
                Forms\Components\Select::make('status')
                    ->options([
                        'available' => 'Доступно',
                        'reserved' => 'В резерве',
                        'booked' => 'Забронировано',
                        'blocked' => 'Заблокировано',
                    ])
                    ->required(),
                Forms\Components\Select::make('source')
                    ->options([
                        'manual' => 'Вручную',
                        'auto' => 'Авто',
                        'order' => 'Заказ',
                    ])
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
                    ->colors([
                        'success' => 'available',
                        'info' => 'reserved',
                        'warning' => 'booked',
                        'danger' => 'blocked',
                    ]),
                Tables\Columns\TextColumn::make('source')
                    ->badge(),
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
