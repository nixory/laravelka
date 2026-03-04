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
    protected static ?string $navigationLabel = 'Работницы';
    protected static ?string $modelLabel = 'Работница';
    protected static ?string $pluralModelLabel = 'Работницы';

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
                            ->visible(fn(string $operation): bool => $operation === 'create'),
                        Forms\Components\TextInput::make('account_name')
                            ->label('Имя аккаунта')
                            ->maxLength(255)
                            ->default(fn(Get $get): string => (string) ($get('display_name') ?? ''))
                            ->dehydrated(true)
                            ->visible(fn(Get $get, string $operation): bool => $operation === 'create' && (bool) $get('create_user_account') && !$get('user_id')),
                        Forms\Components\TextInput::make('account_email')
                            ->label('Email для входа')
                            ->email()
                            ->maxLength(255)
                            ->unique(User::class, 'email')
                            ->required(fn(Get $get, string $operation): bool => $operation === 'create' && (bool) $get('create_user_account') && !$get('user_id'))
                            ->dehydrated(true)
                            ->visible(fn(Get $get, string $operation): bool => $operation === 'create' && (bool) $get('create_user_account') && !$get('user_id')),
                        Forms\Components\TextInput::make('account_password')
                            ->label('Пароль')
                            ->password()
                            ->revealable()
                            ->minLength(8)
                            ->same('account_password_confirmation')
                            ->required(fn(Get $get, string $operation): bool => $operation === 'create' && (bool) $get('create_user_account') && !$get('user_id'))
                            ->dehydrated(true)
                            ->visible(fn(Get $get, string $operation): bool => $operation === 'create' && (bool) $get('create_user_account') && !$get('user_id')),
                        Forms\Components\TextInput::make('account_password_confirmation')
                            ->label('Подтвердите пароль')
                            ->password()
                            ->revealable()
                            ->minLength(8)
                            ->required(fn(Get $get, string $operation): bool => $operation === 'create' && (bool) $get('create_user_account') && !$get('user_id'))
                            ->dehydrated(false)
                            ->visible(fn(Get $get, string $operation): bool => $operation === 'create' && (bool) $get('create_user_account') && !$get('user_id')),
                        Forms\Components\TextInput::make('current_account_email')
                            ->label('Текущий email для входа')
                            ->disabled()
                            ->dehydrated(false)
                            ->formatStateUsing(fn(?Worker $record): string => (string) ($record?->user?->email ?? 'Аккаунт не привязан'))
                            ->visible(fn(string $operation): bool => $operation === 'edit'),
                        Forms\Components\TextInput::make('new_account_password')
                            ->label('Новый пароль')
                            ->password()
                            ->revealable()
                            ->minLength(8)
                            ->same('new_account_password_confirmation')
                            ->dehydrated(false)
                            ->visible(fn(string $operation): bool => $operation === 'edit'),
                        Forms\Components\TextInput::make('new_account_password_confirmation')
                            ->label('Подтвердите новый пароль')
                            ->password()
                            ->revealable()
                            ->minLength(8)
                            ->dehydrated(false)
                            ->visible(fn(string $operation): bool => $operation === 'edit'),
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
                Forms\Components\Section::make('Onboarding (Служебное)')
                    ->schema([
                        Forms\Components\Select::make('onboarding_status')
                            ->options([
                                'step1' => 'Шаг 1 (Заполнение)',
                                'pending_approval' => 'Ожидает проверки',
                                'step2' => 'Шаг 2 (Услуги)',
                                'pending_publication' => 'Ожидает публикации',
                                'completed' => 'Опубликован',
                            ])
                            ->default('completed')
                            ->required(),
                        Forms\Components\Textarea::make('onboarding_notes')
                            ->label('Заметки админа (для доработки)')
                            ->columnSpanFull(),
                    ]),
                Forms\Components\Section::make('Шаг 1: О себе')
                    ->collapsed()
                    ->schema([
                        Forms\Components\TextInput::make('age')
                            ->label('Возраст')
                            ->numeric()
                            ->minValue(18)
                            ->maxValue(99),
                        Forms\Components\Select::make('experience')
                            ->label('Опыт')
                            ->options([
                                'none' => 'Без опыта',
                                'some' => 'Немного (стримы/общение)',
                                'experienced' => 'Есть опыт (аналогичное)',
                            ]),
                        Forms\Components\Select::make('preferred_format')
                            ->label('Что ближе')
                            ->options([
                                'chat' => 'Просто общение',
                                'games' => 'Игры + общение',
                                'anime' => 'Аниме/фильмы + общение',
                                'any' => 'Любое',
                            ]),
                        Forms\Components\Textarea::make('description')
                            ->label('Описание (Обо мне)')
                            ->rows(4)
                            ->columnSpanFull(),
                        Forms\Components\Repeater::make('favorite_games')
                            ->label('Любимые игры')
                            ->schema([
                                Forms\Components\TextInput::make('name')->label('Название игры')->required(),
                            ])
                            ->columnSpanFull(),
                        Forms\Components\Repeater::make('favorite_anime')
                            ->label('Любимые аниме/фильмы')
                            ->schema([
                                Forms\Components\TextInput::make('title')->label('Название')->required(),
                            ])
                            ->columnSpanFull(),
                        Forms\Components\FileUpload::make('photo_main')
                            ->label('Основное фото')
                            ->image()
                            ->disk('public')
                            ->directory('workers/photos')
                            ->downloadable()
                            ->openable(),
                        Forms\Components\FileUpload::make('photos_gallery')
                            ->label('Дополнительные фото')
                            ->image()
                            ->multiple()
                            ->disk('public')
                            ->directory('workers/photos')
                            ->downloadable()
                            ->openable(),
                        Forms\Components\FileUpload::make('audio_path')
                            ->label('Аудио приветствие')
                            ->disk('public')
                            ->directory('workers/audio')
                            ->downloadable()
                            ->openable()
                            ->columnSpanFull(),
                    ])->columns(3),
                Forms\Components\Section::make('Шаг 2: Услуги и расписание')
                    ->collapsed()
                    ->schema([
                        Forms\Components\CheckboxList::make('services_custom.session_options')
                            ->label('Опции сессии')
                            ->options([
                                'webcam' => 'Включить веб-камеру',
                                'movies' => 'Смотреть фильмы/аниме вместе',
                                'night' => 'Доступна ночью',
                            ])->columns(3),
                        Forms\Components\Radio::make('services_custom.flirt_level')
                            ->label('Уровень флирта')
                            ->options([
                                'friendly' => 'Только дружелюбное общение',
                                'playful' => 'Лёгкий игривый флирт',
                                'teasing' => 'Комфортно с флиртом',
                            ]),
                        Forms\Components\CheckboxList::make('services_custom.character_styles')
                            ->label('Стиль общения')
                            ->options([
                                'cute' => 'Милая',
                                'gamer' => 'Геймерша',
                                'troll' => 'Тролль',
                                'shy' => 'Стесняшка',
                                'caring' => 'Заботливая',
                                'confident' => 'Уверенная',
                            ])->columns(3),
                        Forms\Components\Section::make('Платные услуги (Дополнения)')
                            ->collapsed()
                            ->schema([
                                Forms\Components\CheckboxList::make('services_custom.extra_services.voice')
                                    ->label('Голос и ASMR')->options(['asmr' => 'ASMR', 'whispering' => 'Шёпот', 'purr' => 'Мурчание', 'voiceover' => 'Озвучка', 'reading' => 'Чтение', 'vm' => 'Персональные ГС']),
                                Forms\Components\CheckboxList::make('services_custom.extra_services.roleplay')
                                    ->label('Ролевые')->options(['roleplay' => 'Ролплей', 'character_switch' => 'Смена роли', 'anime' => 'Аниме', 'tsundere' => 'Цундере', 'caring' => 'Заботливая', 'shy' => 'Стесняшка', 'strict' => 'Строгая', 'troll' => 'Тролль']),
                                Forms\Components\CheckboxList::make('services_custom.extra_services.gaming')
                                    ->label('Игровые')->options(['coaching' => 'Коучинг', 'replay' => 'Разбор', 'tips' => 'Советы', 'tryhard' => 'Трайхард', 'challenge' => 'Челлендж', 'training' => 'Тренировка']),
                                Forms\Components\CheckboxList::make('services_custom.extra_services.entertainment')
                                    ->label('Развлечения')->options(['truth_dare' => 'Правда/Действие', 'quiz' => 'Викторины', 'guess_game' => 'Угадай', 'stories' => 'Истории', 'jokes' => 'Анекдоты', 'memes' => 'Мемы']),
                                Forms\Components\CheckboxList::make('services_custom.extra_services.creative')
                                    ->label('Творческие')->options(['sketch' => 'Скетч', 'rate_art' => 'Оценка творчества', 'wish' => 'Пожелание', 'video_msg' => 'Видеосообщение']),
                                Forms\Components\CheckboxList::make('services_custom.extra_services.activities')
                                    ->label('Стивности')->options(['youtube' => 'YouTube', 'anime' => 'Аниме', 'movies' => 'Фильмы', 'stream' => 'Стрим экрана', 'chat_only' => 'Без игры', 'deep_talk' => 'Дип-ток']),
                            ])->columns(2),
                        Forms\Components\Section::make('Фан-клуб')
                            ->collapsed()
                            ->schema([
                                Forms\Components\Toggle::make('services_custom.fan_club.enabled')->label('Включен'),
                                Forms\Components\Select::make('services_custom.fan_club.type')->label('Тип контента')->options(['sfw' => 'SFW', 'mixed' => 'Смешанный', 'nsfw' => 'NSFW']),
                                Forms\Components\CheckboxList::make('services_custom.fan_club.format')->label('Форматы')->options(['photo' => 'Фото', 'video' => 'Кружочки', 'posts' => 'Посты', 'voice' => 'ГС', 'chat' => 'Чат']),
                                Forms\Components\Select::make('services_custom.fan_club.frequency')->label('Частота')->options(['rarely' => 'Редко', 'often' => 'Часто', 'daily' => 'Каждый день']),
                                Forms\Components\TextInput::make('services_custom.fan_club.price')->label('Цена подписки')->numeric()->suffix('₽'),
                            ])->columns(2),
                        Forms\Components\CheckboxList::make('services_custom.boundaries.list')
                            ->label('Границы')->options(['no_nsfw' => 'Нет интима', 'no_flirt' => 'Без флирта', 'no_webcam' => 'Без вебки', 'no_personal' => 'Без личного', 'no_roleplay' => 'Без ролевых', 'no_asmr' => 'Без ASMR', 'no_media' => 'Без медиа', 'no_night' => 'Без ночных'])->columns(3),
                        Forms\Components\TextInput::make('services_custom.boundaries.custom')->label('Своя граница')->columnSpanFull(),
                        Forms\Components\Select::make('services_custom.client_interaction_mode')->label('Общение')->options(['talk' => 'Общаюсь', 'team' => 'Команда']),
                        Forms\Components\Select::make('services_custom.promotion_mode')->label('Продвижение')->options(['self' => 'Сама', 'platform' => 'Платформа']),
                        Forms\Components\Select::make('services_custom.content_permission')->label('Контент')->options(['create_videos' => 'Снимаю', 'record_clips' => 'Клипы', 'no_content' => 'Нет']),
                        Forms\Components\TextInput::make('services_custom.custom_services')->label('Свои услуги')->columnSpanFull(),
                        Forms\Components\CheckboxList::make('schedule_preferences.slots')
                            ->label('Расписание')
                            ->options([
                                'morning' => 'Утро (8–12)',
                                'afternoon' => 'День (12–17)',
                                'evening' => 'Вечер (17–22)',
                                'night' => 'Ночь (22–3)',
                                'late_night' => 'Глубокая (3–8)',
                            ])->columns(3)->columnSpanFull(),
                    ])->columns(2),
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
                Tables\Columns\TextColumn::make('onboarding_status')
                    ->label('Onboarding')
                    ->badge()
                    ->colors([
                        'gray' => 'step1',
                        'warning' => 'pending_approval',
                        'primary' => 'step2',
                        'success' => 'completed',
                    ])
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'step1' => 'Шаг 1',
                        'pending_approval' => 'Проверка',
                        'step2' => 'Шаг 2',
                        'completed' => 'Готов',
                        default => $state,
                    })
                    ->sortable(),
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
                SelectFilter::make('onboarding_status')
                    ->options([
                        'step1' => 'Шаг 1',
                        'pending_approval' => 'На проверке',
                        'step2' => 'Шаг 2',
                        'completed' => 'Завершён',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->color('success')
                    ->icon('heroicon-o-check-circle')
                    ->visible(fn(Worker $record): bool => $record->onboarding_status === 'pending_approval')
                    ->requiresConfirmation()
                    ->action(function (Worker $record) {
                        $record->update(['onboarding_status' => 'step2']);

                        // Если у девушки уже привязан Telegram, отправить уведомление
                        if ($record->telegram_chat_id) {
                            $botToken = config('services.telegram.client_bot_token') ?: config('services.telegram.bot_token');
                            if ($botToken) {
                                try {
                                    \Illuminate\Support\Facades\Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                                        'chat_id' => $record->telegram_chat_id,
                                        'text' => "🎉 Твоя анкета (Шаг 1) одобрена!\n\nПереходи к следующему шагу настройки профиля (Услуги и расписание): " . env('APP_URL') . "/worker",
                                    ]);
                                } catch (\Exception $e) {
                                    \Illuminate\Support\Facades\Log::error('Failed to send approve notification: ' . $e->getMessage());
                                }
                            }
                        }
                    }),
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
