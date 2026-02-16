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

    protected static ?string $navigationGroup = 'Operations';

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
                Forms\Components\Section::make('Worker account')
                    ->description('Данные для входа работницы в личный кабинет.')
                    ->schema([
                        Forms\Components\Toggle::make('create_user_account')
                            ->label('Create login account')
                            ->default(true)
                            ->live()
                            ->dehydrated(true)
                            ->visible(fn (string $operation): bool => $operation === 'create'),
                        Forms\Components\TextInput::make('account_name')
                            ->label('Account name')
                            ->maxLength(255)
                            ->default(fn (Get $get): string => (string) ($get('display_name') ?? ''))
                            ->dehydrated(true)
                            ->visible(fn (Get $get, string $operation): bool => $operation === 'create' && (bool) $get('create_user_account') && ! $get('user_id')),
                        Forms\Components\TextInput::make('account_email')
                            ->label('Login email')
                            ->email()
                            ->maxLength(255)
                            ->unique(User::class, 'email')
                            ->required(fn (Get $get, string $operation): bool => $operation === 'create' && (bool) $get('create_user_account') && ! $get('user_id'))
                            ->dehydrated(true)
                            ->visible(fn (Get $get, string $operation): bool => $operation === 'create' && (bool) $get('create_user_account') && ! $get('user_id')),
                        Forms\Components\TextInput::make('account_password')
                            ->label('Password')
                            ->password()
                            ->revealable()
                            ->minLength(8)
                            ->same('account_password_confirmation')
                            ->required(fn (Get $get, string $operation): bool => $operation === 'create' && (bool) $get('create_user_account') && ! $get('user_id'))
                            ->dehydrated(true)
                            ->visible(fn (Get $get, string $operation): bool => $operation === 'create' && (bool) $get('create_user_account') && ! $get('user_id')),
                        Forms\Components\TextInput::make('account_password_confirmation')
                            ->label('Confirm password')
                            ->password()
                            ->revealable()
                            ->minLength(8)
                            ->required(fn (Get $get, string $operation): bool => $operation === 'create' && (bool) $get('create_user_account') && ! $get('user_id'))
                            ->dehydrated(false)
                            ->visible(fn (Get $get, string $operation): bool => $operation === 'create' && (bool) $get('create_user_account') && ! $get('user_id')),
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
                Forms\Components\TextInput::make('city')
                    ->maxLength(255),
                Forms\Components\Select::make('timezone')
                    ->options([
                        'UTC' => 'UTC',
                        'Europe/Kiev' => 'Europe/Kiev',
                        'Europe/Warsaw' => 'Europe/Warsaw',
                        'Europe/Berlin' => 'Europe/Berlin',
                        'Europe/London' => 'Europe/London',
                        'America/New_York' => 'America/New_York',
                        'America/Los_Angeles' => 'America/Los_Angeles',
                    ])
                    ->searchable()
                    ->default('UTC')
                    ->required(),
                Forms\Components\Select::make('status')
                    ->options([
                        'offline' => 'Offline',
                        'online' => 'Online',
                        'busy' => 'Busy',
                        'paused' => 'Paused',
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
                    ->label('Working hours')
                    ->schema([
                        Forms\Components\Select::make('day_of_week')
                            ->options([
                                0 => 'Sunday',
                                1 => 'Monday',
                                2 => 'Tuesday',
                                3 => 'Wednesday',
                                4 => 'Thursday',
                                5 => 'Friday',
                                6 => 'Saturday',
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
                    ->label('User')
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
                Tables\Columns\TextColumn::make('rating')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),
                Tables\Columns\TextColumn::make('completed_orders')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'offline' => 'Offline',
                        'online' => 'Online',
                        'busy' => 'Busy',
                        'paused' => 'Paused',
                    ]),
                SelectFilter::make('is_active')
                    ->options([
                        '1' => 'Active',
                        '0' => 'Inactive',
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
