<?php

namespace App\Filament\Worker\Pages;

use App\Models\Worker;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class OnboardingStep2 extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-briefcase';
    protected static ?string $navigationLabel = 'Услуги';
    protected static string $view = 'filament.worker.pages.onboarding-step2';
    protected static ?string $title = 'Шаг 2: Услуги и расписание';
    protected static ?string $slug = 'onboarding-step-2';
    protected static bool $shouldRegisterNavigation = false;

    public ?array $data = [];

    public function mount(): void
    {
        $worker = $this->getWorker();
        if (!$worker) {
            return;
        }

        if ($worker->onboarding_status !== 'step2') {
            $target = match ($worker->onboarding_status) {
                'step1' => '/worker/onboarding-step-1',
                'pending_approval' => '/worker/onboarding-pending',
                'completed' => '/worker',
                default => '/worker/onboarding-step-1',
            };
            $this->redirect($target);
            return;
        }

        $services = $worker->services ?? [];
        $schedulePrefs = $worker->schedule_preferences ?? [];

        $servicesCustom = $worker->services_custom ?? [];

        $this->form->fill([
            'session_options' => $servicesCustom['session_options'] ?? [],
            'trial_enabled' => $services['trial_enabled'] ?? false,
            'schedule_slots' => $schedulePrefs['slots'] ?? [],
            'session_benefits' => $services['session_benefits'] ?? [],

            'flirt_level' => $servicesCustom['flirt_level'] ?? null,
            'character_styles' => $servicesCustom['character_styles'] ?? [],

            'extra_services_voice' => $servicesCustom['extra_services']['voice'] ?? [],
            'extra_services_roleplay' => $servicesCustom['extra_services']['roleplay'] ?? [],
            'extra_services_gaming' => $servicesCustom['extra_services']['gaming'] ?? [],
            'extra_services_entertainment' => $servicesCustom['extra_services']['entertainment'] ?? [],
            'extra_services_creative' => $servicesCustom['extra_services']['creative'] ?? [],
            'extra_services_activities' => $servicesCustom['extra_services']['activities'] ?? [],

            'fan_club_enabled' => $servicesCustom['fan_club']['enabled'] ?? false,
            'fan_club_type' => $servicesCustom['fan_club']['type'] ?? null,
            'fan_club_format' => $servicesCustom['fan_club']['format'] ?? [],
            'fan_club_frequency' => $servicesCustom['fan_club']['frequency'] ?? null,
            'fan_club_price' => $servicesCustom['fan_club']['price'] ?? null,

            'boundaries' => $servicesCustom['boundaries']['list'] ?? [],
            'boundaries_custom' => $servicesCustom['boundaries']['custom'] ?? null,

            'client_interaction_mode' => $servicesCustom['client_interaction_mode'] ?? null,
            'promotion_mode' => $servicesCustom['promotion_mode'] ?? null,
            'content_permission' => $servicesCustom['content_permission'] ?? null,
            'custom_services' => $servicesCustom['custom_services'] ?? null,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Опции сессии')
                    ->description('Выбери, какие форматы сессий ты хочешь проводить.')
                    ->schema([
                        CheckboxList::make('session_options')
                            ->label('')
                            ->options([
                                'webcam' => 'Включить веб-камеру',
                                'photos' => 'Отправлять фото или кружочки в Telegram во время сессии',
                                'movies' => 'Смотреть фильмы или аниме вместе',
                                'night' => 'Доступна ночью',
                            ])
                            ->descriptions([
                                'webcam' => 'Профили с веб-камерой обычно получают больше заказов, потому что клиентам легче доверять и чувствовать связь.',
                                'photos' => 'Это делает общение более личным и часто увеличивает чаевые и повторные заказы.',
                                'movies' => 'Такие сессии обычно длятся дольше и часто превращаются в постоянные встречи.',
                                'night' => 'Ночью спрос обычно выше и конкуренция ниже, поэтому легче получать заказы.',
                            ])
                            ->columns(1),

                        Toggle::make('trial_enabled')
                            ->label('Предлагать пробный сеанс (99₽ / 10 мин)')
                            ->helperText('Клиент может попробовать короткую мини-сессию перед полной бронью.'),
                    ]),

                Section::make('Уровень флирта')
                    ->description('Выбирай только тот уровень общения, который тебе комфортен. Это помогает правильно подобрать клиентов.')
                    ->schema([
                        \Filament\Forms\Components\Radio::make('flirt_level')
                            ->label('')
                            ->options([
                                'friendly' => 'Только дружелюбное общение',
                                'playful' => 'Лёгкий игривый флирт',
                                'teasing' => 'Комфортно с поддразниванием',
                            ])
                            ->descriptions([
                                'friendly' => 'без флирта',
                                'playful' => 'комплименты, шутки, лёгкий teasing',
                                'teasing' => 'более смелый и игривый вайб',
                            ]),
                    ]),

                Section::make('Стиль персонажа')
                    ->description('Разные стили общения привлекают разных клиентов.')
                    ->schema([
                        CheckboxList::make('character_styles')
                            ->label('')
                            ->options([
                                'cute' => 'Милая / кавайная',
                                'gamer' => 'Дружелюбная геймерша',
                                'troll' => 'Игровой троллинг (лёгкий)',
                                'shy' => 'Стеснительная',
                                'caring' => 'Заботливая',
                                'confident' => 'Уверенная',
                            ])
                            ->columns(2),
                    ]),

                Section::make('Дополнительные платные услуги')
                    ->description('Это дополнительные услуги, за которые ты можешь брать отдельную плату во время сессий.')
                    ->schema([
                        Section::make('Голос и ASMR')
                            ->collapsed()
                            ->schema([
                                CheckboxList::make('extra_services_voice')
                                    ->label('')
                                    ->options([
                                        'asmr' => 'ASMR голос',
                                        'whispering' => 'Милые звуки / шёпот',
                                        'purr' => 'Помяукать / помурчать',
                                        'voiceover' => 'Озвучка фраз',
                                        'reading' => 'Чтение текста голосом',
                                        'vm' => 'Персональные голосовые сообщения',
                                    ])
                                    ->columns(2),
                            ]),

                        Section::make('Ролевые персонажи')
                            ->collapsed()
                            ->schema([
                                CheckboxList::make('extra_services_roleplay')
                                    ->label('')
                                    ->options([
                                        'roleplay' => 'Ролплей персонажа',
                                        'character_switch' => 'Смена роли / характера / голоса',
                                        'anime' => 'Аниме-персонаж',
                                        'tsundere' => 'Цундере стиль',
                                        'caring' => 'Заботливая девушка',
                                        'shy' => 'Стеснительная девушка',
                                        'strict' => 'Строгая девушка',
                                        'troll' => 'Игровой троллинг',
                                    ])
                                    ->columns(2),
                            ]),

                        Section::make('Игровые услуги')
                            ->collapsed()
                            ->schema([
                                CheckboxList::make('extra_services_gaming')
                                    ->label('')
                                    ->options([
                                        'coaching' => 'Коучинг по игре',
                                        'replay' => 'Разбор реплеев / каток',
                                        'tips' => 'Игровые советы',
                                        'tryhard' => 'Играем до победы',
                                        'challenge' => 'Челлендж режим',
                                        'training' => 'Тренировка по игре',
                                    ])
                                    ->columns(2),
                            ]),

                        Section::make('Развлечения')
                            ->collapsed()
                            ->schema([
                                CheckboxList::make('extra_services_entertainment')
                                    ->label('')
                                    ->options([
                                        'truth_dare' => 'Правда или действие',
                                        'quiz' => 'Викторины и тесты',
                                        'guess_game' => 'Угадай игру',
                                        'stories' => 'Расскажу истории из жизни',
                                        'jokes' => 'Анекдоты',
                                        'memes' => 'Смотрим мемы / тиктоки вместе',
                                    ])
                                    ->columns(2),
                            ]),

                        Section::make('Творческие услуги')
                            ->collapsed()
                            ->schema([
                                CheckboxList::make('extra_services_creative')
                                    ->label('')
                                    ->options([
                                        'sketch' => 'Нарисую скетч',
                                        'rate_art' => 'Оценю твоё творчество',
                                        'wish' => 'Напишу пожелание',
                                        'video_msg' => 'Персональное видео сообщение',
                                    ])
                                    ->columns(2),
                            ]),

                        Section::make('Совместные активности')
                            ->collapsed()
                            ->schema([
                                CheckboxList::make('extra_services_activities')
                                    ->label('')
                                    ->options([
                                        'youtube' => 'Совместный просмотр YouTube',
                                        'anime' => 'Совместный просмотр аниме',
                                        'movies' => 'Совместный просмотр фильмов',
                                        'stream' => 'Стрим экрана игры',
                                        'chat_only' => 'Сессия без игры (просто общение)',
                                        'deep_talk' => 'Разговор по душам',
                                    ])
                                    ->columns(2),
                            ]),
                    ]),

                Section::make('Фан-клуб (Подписка)')
                    ->description('Ты можешь создать приватный Telegram канал. Подписчики платят ежемесячно за доступ к твоим фото, постам и кружочкам.')
                    ->schema([
                        Toggle::make('fan_club_enabled')
                            ->label('Включить фан-клуб')
                            ->reactive(),
                        Select::make('fan_club_type')
                            ->label('Тип контента')
                            ->placeholder('Выберите вариант')
                            ->options([
                                'sfw' => 'SFW',
                                'mixed' => 'Mixed',
                                'nsfw' => '18+ (доступно только для клиентов 18+)',
                            ])
                            ->visible(fn(\Filament\Forms\Get $get) => $get('fan_club_enabled')),
                        CheckboxList::make('fan_club_format')
                            ->label('Формат контента')
                            ->options([
                                'photo' => 'фото',
                                'video' => 'кружочки',
                                'posts' => 'посты',
                                'voice' => 'голосовые',
                                'chat' => 'чат с подписчиками',
                            ])
                            ->columns(2)
                            ->visible(fn(\Filament\Forms\Get $get) => $get('fan_club_enabled')),
                        Select::make('fan_club_frequency')
                            ->label('Частота публикаций')
                            ->placeholder('Выберите вариант')
                            ->options([
                                'rarely' => '1-2 раза в неделю',
                                'often' => '3-5 раз в неделю',
                                'daily' => 'каждый день',
                            ])
                            ->visible(fn(\Filament\Forms\Get $get) => $get('fan_club_enabled')),
                        TextInput::make('fan_club_price')
                            ->label('Цена подписки (необязательно)')
                            ->numeric()
                            ->placeholder('Например: 500')
                            ->suffix('₽')
                            ->visible(fn(\Filament\Forms\Get $get) => $get('fan_club_enabled')),
                    ]),

                Section::make('Границы общения')
                    ->schema([
                        CheckboxList::make('boundaries')
                            ->label('')
                            ->options([
                                'no_nsfw' => 'Не обсуждаю интимные темы',
                                'no_flirt' => 'Без флирта',
                                'no_webcam' => 'Без веб-камеры',
                                'no_personal' => 'Без личных вопросов',
                                'no_roleplay' => 'Без ролевых сценариев',
                                'no_asmr' => 'Без ASMR',
                                'no_media' => 'Без фото / кружочков',
                                'no_night' => 'Без ночных сессий',
                            ])
                            ->columns(2),
                        TextInput::make('boundaries_custom')
                            ->label('Свой пункт')
                            ->placeholder('Например: Не играю в хорроры')
                            ->maxLength(200),
                    ]),

                Section::make('Стиль общения с клиентами')
                    ->description('Общение между сессиями помогает получать больше повторных заказов.')
                    ->schema([
                        Select::make('client_interaction_mode')
                            ->label('Общение вне сессий')
                            ->placeholder('Выберите вариант')
                            ->options([
                                'talk' => 'Я общаюсь с клиентами между сессиями',
                                'team' => 'Команда платформы отвечает за меня',
                            ]),
                    ]),

                Section::make('Продвижение в соцсетях')
                    ->description('Продвижение помогает быстрее получать клиентов.')
                    ->schema([
                        Select::make('promotion_mode')
                            ->label('Продвижение')
                            ->placeholder('Выберите вариант')
                            ->options([
                                'self' => 'Я могу создавать TikTok / контент для продвижения',
                                'platform' => 'Платформа может заниматься продвижением за меня',
                            ]),
                    ]),

                Section::make('Контент для промо')
                    ->description('Мы никогда не используем контент без твоего разрешения.')
                    ->schema([
                        Select::make('content_permission')
                            ->label('Разрешение на контент')
                            ->placeholder('Выберите вариант')
                            ->options([
                                'create_videos' => 'Я могу записывать видео для продвижения',
                                'record_clips' => 'Платформа может записывать клипы из сессий',
                                'no_content' => 'Я не хочу участвовать в промо',
                            ]),
                    ]),

                Section::make('Дополнительные идеи монетизации')
                    ->schema([
                        TextInput::make('custom_services')
                            ->label('Свои услуги')
                            ->helperText('Опиши любые уникальные услуги, которые ты хочешь предлагать.'),
                    ]),

                Section::make('Предпочтения по расписанию')
                    ->description('Чем больше свободного времени ты укажешь, тем выше шанс получить заказ.')
                    ->schema([
                        CheckboxList::make('schedule_slots')
                            ->label('')
                            ->options([
                                'morning' => '🌅 Утро (8:00–12:00)',
                                'afternoon' => '☀️ День (12:00–17:00)',
                                'evening' => '🌆 Вечер (17:00–22:00)',
                                'night' => '🌙 Ночь (22:00–03:00)',
                                'late_night' => '🦉 Глубокая ночь (03:00–08:00)',
                            ])
                            ->columns(3)
                            ->required(),
                    ]),

                Section::make('Что клиент получит от встречи')
                    ->description('Напиши 3–5 пунктов для профиля')
                    ->schema([
                        Repeater::make('session_benefits')
                            ->label('')
                            ->schema([
                                TextInput::make('icon')
                                    ->label('Эмодзи')
                                    ->maxLength(10)
                                    ->placeholder('🎮')
                                    ->default('✅'),
                                TextInput::make('text')
                                    ->label('Что получит клиент')
                                    ->required()
                                    ->maxLength(200)
                                    ->placeholder('компания в любимой игре'),
                            ])
                            ->columns(2)
                            ->minItems(3)
                            ->maxItems(7)
                            ->defaultItems(3)
                            ->addActionLabel('Добавить пункт')
                            ->collapsible()
                            ->itemLabel(
                                fn(array $state): ?string =>
                                ($state['icon'] ?? '') . ' ' . ($state['text'] ?? '')
                            ),
                    ]),
            ])
            ->statePath('data');
    }

    public function submit(): void
    {
        $worker = $this->getWorker();
        if (!$worker) {
            Notification::make()->title('Профиль не найден')->danger()->send();
            return;
        }

        $data = $this->form->getState();

        // Keep existing services array structure intact
        $services = $worker->services ?? [];

        $worker->update([
            'services' => [
                'addons' => $services['addons'] ?? [], // don't wipe out existing from previous saves if present
                'trial_enabled' => $data['trial_enabled'] ?? false,
                'session_benefits' => $data['session_benefits'] ?? [],
            ],
            'services_custom' => [
                'session_options' => $data['session_options'] ?? [],
                'flirt_level' => $data['flirt_level'] ?? null,
                'character_styles' => $data['character_styles'] ?? [],
                'extra_services' => [
                    'voice' => $data['extra_services_voice'] ?? [],
                    'roleplay' => $data['extra_services_roleplay'] ?? [],
                    'gaming' => $data['extra_services_gaming'] ?? [],
                    'entertainment' => $data['extra_services_entertainment'] ?? [],
                    'creative' => $data['extra_services_creative'] ?? [],
                    'activities' => $data['extra_services_activities'] ?? [],
                ],
                'fan_club' => [
                    'enabled' => $data['fan_club_enabled'] ?? false,
                    'type' => $data['fan_club_type'] ?? null,
                    'format' => $data['fan_club_format'] ?? [],
                    'frequency' => $data['fan_club_frequency'] ?? null,
                    'price' => $data['fan_club_price'] ?? null,
                ],
                'boundaries' => [
                    'list' => $data['boundaries'] ?? [],
                    'custom' => $data['boundaries_custom'] ?? null,
                ],
                'promotion_mode' => $data['promotion_mode'] ?? null,
                'content_permission' => $data['content_permission'] ?? null,
                'custom_services' => $data['custom_services'] ?? null,
                'client_interaction_mode' => $data['client_interaction_mode'] ?? null,
            ],
            'schedule_preferences' => [
                'slots' => $data['schedule_slots'] ?? [],
            ],
            'onboarding_status' => 'completed',
        ]);

        Notification::make()
            ->title('Профиль готов! 🎉')
            ->body('Твоя анкета готова! Профили с веб-камерой, множеством фото и дополнительными услугами обычно зарабатывают значительно больше.')
            ->success()
            ->send();

        $this->redirect('/worker');
    }

    private function getWorker(): ?Worker
    {
        return Filament::auth()->user()?->workerProfile;
    }

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();
        return $user && $user->role === 'worker';
    }
}
