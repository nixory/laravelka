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

        $this->form->fill([
            'plans' => $services['plans'] ?? [],
            'addons' => $services['addons'] ?? [],
            'trial_enabled' => $services['trial_enabled'] ?? false,
            'schedule_slots' => $schedulePrefs['slots'] ?? [],
            'session_benefits' => $services['session_benefits'] ?? [],
            'flirt_level' => $services['flirt_level'] ?? null,
            'character_styles' => $services['character_styles'] ?? [],
            'extra_services' => $services['extra_services'] ?? [],
            'fan_club_enabled' => $services['fan_club_enabled'] ?? false,
            'fan_club_type' => $services['fan_club_type'] ?? null,
            'client_interaction_mode' => $services['client_interaction_mode'] ?? null,
            'promotion_mode' => $services['promotion_mode'] ?? null,
            'content_permission' => $services['content_permission'] ?? null,
            'custom_services' => $services['custom_services'] ?? null,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Тарифы')
                    ->description('Каждый тариф — это тип сессии, который клиенты могут забронировать. Пример: \'Поиграть в Valorant вместе\', \'Просто поболтать\', \'Посмотреть аниме вместе\'.')
                    ->schema([
                        Repeater::make('plans')
                            ->label('')
                            ->schema([
                                Select::make('name')
                                    ->label('Тариф')
                                    ->required()
                                    ->options([
                                        'lite' => '⚡ Lite (1 час)',
                                        'medium' => '🔥 Medium (2-3 часа)',
                                        'hard' => '💎 Hard (5+ часов)',
                                    ]),
                                TextInput::make('price')
                                    ->label('Цена (₽)')
                                    ->numeric()
                                    ->required()
                                    ->minValue(100)
                                    ->suffix('₽')
                                    ->placeholder('500'),
                                TextInput::make('hours')
                                    ->label('Часов')
                                    ->numeric()
                                    ->required()
                                    ->minValue(1)
                                    ->maxValue(12)
                                    ->placeholder('1'),
                            ])
                            ->columns(3)
                            ->minItems(1)
                            ->maxItems(5)
                            ->defaultItems(1)
                            ->addActionLabel('Добавить тариф')
                            ->collapsible()
                            ->itemLabel(
                                fn(array $state): ?string =>
                                ($state['name'] ?? '') . ' — ' . ($state['price'] ?? '?') . '₽'
                            ),
                    ]),

                Section::make('Опции сессии')
                    ->description('Что ещё готова предложить?')
                    ->schema([
                        CheckboxList::make('addons')
                            ->label('')
                            ->options([
                                'webcam' => 'Включить веб-камеру',
                                'photos' => 'Отправлять фото или кружочки в Telegram во время сессий',
                                'movies' => 'Смотреть фильмы или аниме вместе',
                                'night' => 'Доступна ночью',
                            ])
                            ->descriptions([
                                'webcam' => 'Профили с веб-камерой получают до 3 раз больше заказов, так как клиенты чувствуют более сильную связь.',
                                'photos' => 'Это увеличивает чаевые и делает сессии более личными.',
                                'movies' => 'Это создает более длинные сессии и постоянных клиентов.',
                                'night' => 'Ночные сессии обычно пользуются более высоким спросом и приносят больше чаевых.',
                            ])
                            ->columns(1),

                        Toggle::make('trial_enabled')
                            ->label('Предлагать пробный сеанс (99₽ / 10 мин)')
                            ->helperText('Клиент сможет попробовать мини-встречу перед полной бронировкой'),
                    ]),

                Section::make('Уровень флирта')
                    ->schema([
                        Select::make('flirt_level')
                            ->label('Уровень флирта')
                            ->options([
                                'friendly' => 'Только дружелюбное общение',
                                'playful' => 'Легкий игривый флирт',
                                'teasing' => 'Комфортно с поддразниванием',
                            ])
                            ->helperText('Легкий флирт обычно увеличивает продолжительность сессии и чаевые.'),
                    ]),

                Section::make('Стиль персонажа')
                    ->description('Разные стили привлекают разных клиентов. Ты можешь менять их во время сессий.')
                    ->schema([
                        CheckboxList::make('character_styles')
                            ->label('')
                            ->options([
                                'cute' => 'Милая / кавайная',
                                'gamer' => 'Дружелюбная геймерша',
                                'troll' => 'Игривый тролль',
                                'shy' => 'Стеснительная',
                                'caring' => 'Заботливая',
                                'confident' => 'Уверенная',
                            ])
                            ->columns(2),
                    ]),

                Section::make('Дополнительные платные услуги')
                    ->description('Это дополнительные услуги, за которые ты можешь брать отдельную плату во время сессий.')
                    ->schema([
                        CheckboxList::make('extra_services')
                            ->label('')
                            ->options([
                                'asmr' => 'ASMR голос',
                                'whispering' => 'Милые звуки / шепот',
                                'roleplay' => 'Отыгрыш персонажа (Ролплей)',
                                'coaching' => 'Обучение игре (Коучинг)',
                                'replay' => 'Разбор реплеев игр',
                                'truth_dare' => 'Игры "Правда или действие"',
                                'private_stream' => 'Приватный стрим',
                                'voice_messages' => 'Персональные голосовые сообщения',
                                'reaction' => 'Реакция на геймплей клиента',
                            ])
                            ->columns(2),
                    ]),

                Section::make('Фан-клуб (Подписка)')
                    ->description('Ты можешь создать приватный Telegram канал. Подписчики платят ежемесячно за доступ к твоим фото, постам и кружочкам.')
                    ->schema([
                        Toggle::make('fan_club_enabled')
                            ->label('Включить фан-клуб')
                            ->reactive(),
                        Select::make('fan_club_type')
                            ->label('Тип контента')
                            ->options([
                                'sfw' => 'Обычный лайфстайл контент (SFW)',
                                'mixed' => 'Смешанный контент',
                                'nsfw' => '18+ контент (по желанию и обоюдному согласию)',
                            ])
                            ->visible(fn(\Filament\Forms\Get $get) => $get('fan_club_enabled')),
                    ]),

                Section::make('Стиль общения с клиентами')
                    ->schema([
                        Select::make('client_interaction_mode')
                            ->label('Общение вне сессий')
                            ->options([
                                'talk' => 'Я общаюсь с клиентами между сессиями',
                                'team' => 'Команда платформы управляет сообщениями за меня',
                            ])
                            ->helperText('Общение с клиентами между сессиями увеличивает количество повторных заказов.'),
                    ]),

                Section::make('Продвижение в соцсетях')
                    ->schema([
                        Select::make('promotion_mode')
                            ->label('Продвижение')
                            ->options([
                                'self' => 'Я могу делать TikTok / контент для своего продвижения',
                                'platform' => 'Платформа может заниматься продвижением за меня',
                            ])
                            ->helperText('Креаторы, которые продвигают себя сами, обычно получают больше заказов.'),
                    ]),

                Section::make('Контент для продвижения')
                    ->schema([
                        Select::make('content_permission')
                            ->label('Разрешение на контент')
                            ->options([
                                'create_videos' => 'Я могу записывать видео для продвижения',
                                'record_clips' => 'Платформа может записывать клипы с сессий',
                                'no_content' => 'Я предпочитаю не появляться в промо-контенте',
                            ])
                            ->helperText('Промо-клипы помогают привлечь больше клиентов в твой профиль.'),
                    ]),

                Section::make('Дополнительные идеи монетизации')
                    ->schema([
                        TextInput::make('custom_services')
                            ->label('Свои услуги')
                            ->helperText('Опиши любые уникальные услуги, которые ты хочешь предлагать клиентам.'),
                    ]),

                Section::make('Предпочтения по расписанию')
                    ->description('Чем больше свободного времени, тем выше шанс получить заказ.')
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

        $worker->update([
            'services' => [
                'plans' => $data['plans'] ?? [],
                'addons' => $data['addons'] ?? [],
                'trial_enabled' => $data['trial_enabled'] ?? false,
                'session_benefits' => $data['session_benefits'] ?? [],
                'flirt_level' => $data['flirt_level'] ?? null,
                'character_styles' => $data['character_styles'] ?? [],
                'extra_services' => $data['extra_services'] ?? [],
                'fan_club_enabled' => $data['fan_club_enabled'] ?? false,
                'fan_club_type' => $data['fan_club_type'] ?? null,
                'client_interaction_mode' => $data['client_interaction_mode'] ?? null,
                'promotion_mode' => $data['promotion_mode'] ?? null,
                'content_permission' => $data['content_permission'] ?? null,
                'custom_services' => $data['custom_services'] ?? null,
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
