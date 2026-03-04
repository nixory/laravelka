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
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Тарифы')
                    ->description('Какие тарифы хочешь предлагать клиентам?')
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

                Section::make('Дополнительные опции')
                    ->description('Что ещё готова предложить?')
                    ->schema([
                        CheckboxList::make('addons')
                            ->label('')
                            ->options([
                                'bring_friend' => '👥 Позвать друга (+50%)',
                                'bring_friends' => '👥👥 Позвать друзей (+100%)',
                                'watch_together' => '🎬 Совместный просмотр',
                                'voice_only' => '🎧 Только голосовое общение',
                                'text_chat' => '💬 Текстовый чат',
                            ])
                            ->columns(2),

                        Toggle::make('trial_enabled')
                            ->label('Предлагать пробный сеанс (99₽ / 10 мин)')
                            ->helperText('Клиент сможет попробовать мини-встречу перед полной бронировкой'),
                    ]),

                Section::make('Предпочтения по расписанию')
                    ->description('Когда тебе удобно работать?')
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
            ],
            'schedule_preferences' => [
                'slots' => $data['schedule_slots'] ?? [],
            ],
            'onboarding_status' => 'completed',
        ]);

        Notification::make()
            ->title('Профиль заполнен! 🎉')
            ->body('Добро пожаловать в команду E-GIRLZ! Теперь ты можешь начать работать.')
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
