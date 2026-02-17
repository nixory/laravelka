<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WorkerResource\Pages;
use App\Models\User;
use App\Models\Worker;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class WorkerResource extends Resource
{
    protected static ?string $model = Worker::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'Операции';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->relationship('user', 'email')
                    ->searchable()
                    ->preload()
                    ->helperText('Можно выбрать существующий аккаунт или создать новый ниже.'),
                Forms\Components\Section::make('Аккаунт работницы')
                    ->description('Данные для входа работницы в личный кабинет.')
                    ->schema([
                        Forms\Components\Toggle::make('create_user_account')
                            ->label('Создать аккаунт для входа')
                            ->default(true)
                            ->live()
                            ->dehydrated(true)
                            ->visible(fn (string $operation): bool => $operation === 'create'),
                        Forms\Components\TextInput::make('account_name')
                            ->label('Имя аккаунта')
                            ->maxLength(255)
                            ->default(fn (Get $get): string => (string) ($get('display_name') ?? ''))
                            ->dehydrated(true)
                            ->visible(fn (Get $get, string $operation): bool => $operation === 'create' && (bool) $get('create_user_account') && ! $get('user_id')),
                        Forms\Components\TextInput::make('account_email')
                            ->label('Email для входа')
                            ->email()
                            ->maxLength(255)
                            ->unique(User::class, 'email')
                            ->required(fn (Get $get, string $operation): bool => $operation === 'create' && (bool) $get('create_user_account') && ! $get('user_id'))
                            ->dehydrated(true)
                            ->visible(fn (Get $get, string $operation): bool => $operation === 'create' && (bool) $get('create_user_account') && ! $get('user_id')),
                        Forms\Components\TextInput::make('account_password')
                            ->label('Пароль')
                            ->password()
                            ->revealable()
                            ->minLength(8)
                            ->same('account_password_confirmation')
                            ->required(fn (Get $get, string $operation): bool => $operation === 'create' && (bool) $get('create_user_account') && ! $get('user_id'))
                            ->dehydrated(true)
                            ->visible(fn (Get $get, string $operation): bool => $operation === 'create' && (bool) $get('create_user_account') && ! $get('user_id')),
                        Forms\Components\TextInput::make('account_password_confirmation')
                            ->label('Подтвердите пароль')
                            ->password()
                            ->revealable()
                            ->minLength(8)
                            ->required(fn (Get $get, string $operation): bool => $operation === 'create' && (bool) $get('create_user_account') && ! $get('user_id'))
                            ->dehydrated(false)
                            ->visible(fn (Get $get, string $operation): bool => $operation === 'create' && (bool) $get('create_user_account') && ! $get('user_id')),
                        Forms\Components\TextInput::make('current_account_email')
                            ->label('Текущий email для входа')
                            ->disabled()
                            ->dehydrated(false)
                            ->formatStateUsing(fn (?Worker $record): string => (string) ($record?->user?->email ?? 'Аккаунт не привязан'))
                            ->visible(fn (string $operation): bool => $operation === 'edit'),
                        Forms\Components\TextInput::make('new_account_password')
                            ->label('Новый пароль')
                            ->password()
                            ->revealable()
                            ->minLength(8)
                            ->same('new_account_password_confirmation')
                            ->dehydrated(false)
                            ->visible(fn (string $operation): bool => $operation === 'edit'),
                        Forms\Components\TextInput::make('new_account_password_confirmation')
                            ->label('Подтвердите новый пароль')
                            ->password()
                            ->revealable()
                            ->minLength(8)
                            ->dehydrated(false)
                            ->visible(fn (string $operation): bool => $operation === 'edit'),
                    ])
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('display_name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('slug')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                Forms\Components\TextInput::make('phone')
                    ->tel()
                    ->maxLength(255),
                Forms\Components\TextInput::make('telegram')
                    ->maxLength(255),
                Forms\Components\TextInput::make('telegram_chat_id')
                    ->label('Telegram chat ID')
                    ->helperText('Вставь сюда numeric chat_id для уведомлений бота, например 123456789 или -100...')
                    ->maxLength(255),
                Forms\Components\TextInput::make('city')
                    ->maxLength(255),
                Forms\Components\Select::make('timezone')
                    ->options([
                        'Europe/Moscow' => 'Europe/Moscow (МСК)',
                    ])
                    ->default('Europe/Moscow')
                    ->required(),
                Forms\Components\Select::make('status')
                    ->options([
                        'offline' => 'Оффлайн',
                        'online' => 'Онлайн',
                        'busy' => 'Занята',
                        'paused' => 'Пауза',
                    ])
                    ->required(),
                Forms\Components\TextInput::make('rating')
                    ->numeric()
                    ->step(0.01)
                    ->default(0)
                    ->required(),
                Forms\Components\TextInput::make('completed_orders')
                    ->numeric()
                    ->default(0)
                    ->required(),
                Forms\Components\Toggle::make('is_active')
                    ->default(true)
                    ->required(),
                Forms\Components\Repeater::make('availabilities')
                    ->relationship()
                    ->label('Рабочие часы')
                    ->schema([
                        Forms\Components\Select::make('day_of_week')
                            ->options([
                                0 => 'Воскресенье',
                                1 => 'Понедельник',
                                2 => 'Вторник',
                                3 => 'Среда',
                                4 => 'Четверг',
                                5 => 'Пятница',
                                6 => 'Суббота',
                            ])
                            ->required(),
                        Forms\Components\TimePicker::make('start_time')
                            ->seconds(false)
                            ->required(),
                        Forms\Components\TimePicker::make('end_time')
                            ->seconds(false)
                            ->required(),
                        Forms\Components\Toggle::make('is_active')
                            ->default(true)
                            ->required(),
                    ])
                    ->defaultItems(0)
                    ->collapsible()
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('notes')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('display_name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.email')
                    ->label('Пользователь')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'gray' => 'offline',
                        'success' => 'online',
                        'warning' => 'busy',
                        'danger' => 'paused',
                    ]),
                Tables\Columns\TextColumn::make('city')
                    ->searchable(),
                Tables\Columns\TextColumn::make('timezone')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('phone')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('telegram_chat_id')
                    ->label('TG chat ID')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('rating')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),
                Tables\Columns\TextColumn::make('completed_orders')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime('d.m.Y H:i', 'Europe/Moscow')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'offline' => 'Оффлайн',
                        'online' => 'Онлайн',
                        'busy' => 'Занята',
                        'paused' => 'Пауза',
                    ]),
                SelectFilter::make('is_active')
                    ->options([
                        '1' => 'Активна',
                        '0' => 'Неактивна',
                    ]),
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
            'index' => Pages\ListWorkers::route('/'),
            'create' => Pages\CreateWorker::route('/create'),
            'edit' => Pages\EditWorker::route('/{record}/edit'),
        ];
    }
}
