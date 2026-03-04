<?php

namespace App\Filament\Worker\Pages;

use App\Models\Worker;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OnboardingStep1 extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationLabel = 'Анкета';
    protected static string $view = 'filament.worker.pages.onboarding-step1';
    protected static ?string $title = 'Шаг 1: Расскажи о себе';
    protected static ?string $slug = 'onboarding-step-1';
    protected static bool $shouldRegisterNavigation = false;

    public ?array $data = [];

    public function mount(): void
    {
        $worker = $this->getWorker();
        if (!$worker) {
            return;
        }

        // If already past step1 — redirect
        if (!in_array($worker->onboarding_status, ['step1'])) {
            $this->redirect($this->getRedirectForStatus($worker->onboarding_status));
            return;
        }

        $this->form->fill([
            'display_name' => $worker->display_name,
            'age' => $worker->age,
            'telegram' => $worker->telegram,
            'city' => $worker->city,
            'timezone' => $worker->timezone ?? 'Europe/Moscow',
            'description' => $worker->description,
            'experience' => $worker->experience,
            'preferred_format' => $worker->preferred_format,
            'favorite_games' => $worker->favorite_games ?? [],
            'favorite_anime' => $worker->favorite_anime ?? [],
            'photo_main' => $worker->photo_main,
            'photos_gallery' => $worker->photos_gallery ?? [],
            'audio_path' => $worker->audio_path,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Основная информация')
                    ->description('Как ты будешь представлена клиентам')
                    ->schema([
                        TextInput::make('display_name')
                            ->label('Ник / имя')
                            ->required()
                            ->maxLength(100)
                            ->placeholder('Например: NekoLera')
                            ->helperText('Это имя будут видеть клиенты. Выбери что-то запоминающееся.'),

                        TextInput::make('age')
                            ->label('Возраст')
                            ->numeric()
                            ->required()
                            ->minValue(18)
                            ->maxValue(99)
                            ->placeholder('18')
                            ->helperText('Видно только модераторам.'),

                        TextInput::make('telegram')
                            ->label('Telegram')
                            ->prefix('@')
                            ->required()
                            ->maxLength(100)
                            ->placeholder('Привяжи аккаунт ->')
                            ->disabled()
                            ->dehydrated(false)
                            ->hint(fn() => $this->getWorker()?->telegram_chat_id ? '✅ Привязан' : '❌ Не привязан')
                            ->hintColor(fn() => $this->getWorker()?->telegram_chat_id ? 'success' : 'danger')
                            ->helperText('Телеграм нужен, чтобы клиенты и платформа могли связываться с тобой по заказам.')
                            ->suffixAction(
                                FormAction::make('confirm_tg')
                                    ->label('Подтвердить Telegram')
                                    ->color('primary')
                                    ->icon('heroicon-o-paper-airplane')
                                    ->url(fn() => rtrim(config('services.telegram.bot_url', 'https://t.me/egirlz_bot'), '/') . '?start=worker_' . $this->getWorker()?->id)
                                    ->openUrlInNewTab()
                            ),

                        TextInput::make('city')
                            ->label('Город')
                            ->required()
                            ->maxLength(100)
                            ->placeholder('Москва'),

                        Select::make('timezone')
                            ->label('Часовой пояс')
                            ->required()
                            ->options([
                                'Europe/Kaliningrad' => 'Калининград (UTC+2)',
                                'Europe/Moscow' => 'Москва (UTC+3)',
                                'Europe/Samara' => 'Самара (UTC+4)',
                                'Asia/Yekaterinburg' => 'Екатеринбург (UTC+5)',
                                'Asia/Omsk' => 'Омск (UTC+6)',
                                'Asia/Krasnoyarsk' => 'Красноярск (UTC+7)',
                                'Asia/Irkutsk' => 'Иркутск (UTC+8)',
                                'Asia/Yakutsk' => 'Якутск (UTC+9)',
                                'Asia/Vladivostok' => 'Владивосток (UTC+10)',
                                'Asia/Magadan' => 'Магадан (UTC+11)',
                                'Asia/Kamchatka' => 'Камчатка (UTC+12)',
                            ])
                            ->default('Europe/Moscow'),
                    ])
                    ->columns(2),

                Section::make('О себе')
                    ->description('Расскажи немного — это будет в твоём профиле')
                    ->schema([
                        Textarea::make('description')
                            ->label('Описание (Обо мне)')
                            ->required()
                            ->minLength(20)
                            ->maxLength(2000)
                            ->rows(4)
                            ->placeholder('Какой у тебя вайб? Что любишь? Какие игры/жанры?')
                            ->helperText('Профили с подробным описанием получают до 40% больше заказов.'),

                        Select::make('experience')
                            ->label('Опыт')
                            ->required()
                            ->options([
                                'none' => 'Без опыта',
                                'some' => 'Немного (стримы/общение)',
                                'experienced' => 'Есть опыт (аналогичное)',
                            ]),

                        Select::make('preferred_format')
                            ->label('Что тебе ближе')
                            ->required()
                            ->options([
                                'chat' => 'Просто общение',
                                'games' => 'Игры + общение',
                                'anime' => 'Аниме/фильмы + общение',
                                'any' => 'Любое (как скажут)',
                            ]),
                    ])
                    ->columns(2),

                Section::make('Фотографии')
                    ->description('Загрузи фотографии для профиля')
                    ->schema([
                        FileUpload::make('photo_main')
                            ->label('Основное фото')
                            ->image()
                            ->required()
                            ->maxSize(5120)
                            ->disk('public')
                            ->directory('workers/photos')
                            ->imageResizeMode('cover')
                            ->imageCropAspectRatio('3:4')
                            ->imageResizeTargetWidth(600)
                            ->imageResizeTargetHeight(800)
                            ->helperText('Профили с четким главным фото получают значительно больше заказов.'),

                        FileUpload::make('photos_gallery')
                            ->label('Дополнительные фото (до 5)')
                            ->image()
                            ->multiple()
                            ->maxFiles(5)
                            ->maxSize(5120)
                            ->disk('public')
                            ->directory('workers/photos')
                            ->reorderable()
                            ->helperText('Много фото увеличивают доверие и конверсию профиля.'),
                    ])
                    ->columns(2),

                Section::make('Голосовое приветствие')
                    ->description('Запиши короткое аудио-приветствие (необязательно)')
                    ->schema([
                        FileUpload::make('audio_path')
                            ->label('Аудио-файл')
                            ->acceptedFileTypes(['audio/mpeg', 'audio/mp4', 'audio/ogg', 'audio/wav', 'audio/webm'])
                            ->maxSize(10240)
                            ->disk('public')
                            ->directory('workers/audio')
                            ->helperText('Голосовое приветствие помогает клиентам выбрать тебя быстрее.'),
                    ]),

                Section::make('Любимые игры')
                    ->description('Какие игры ты любишь? (минимум 1)')
                    ->schema([
                        Repeater::make('favorite_games')
                            ->label('')
                            ->schema([
                                TextInput::make('name')
                                    ->label('Название игры')
                                    ->required()
                                    ->maxLength(100)
                                    ->placeholder('Valorant, Genshin, CS2...'),
                            ])
                            ->minItems(1)
                            ->maxItems(10)
                            ->defaultItems(1)
                            ->addActionLabel('Добавить игру')
                            ->collapsible()
                            ->itemLabel(fn(array $state): ?string => $state['name'] ?? null)
                            ->helperText('Больше игр = больше потенциальных клиентов.'),
                    ]),

                Section::make('Любимые аниме / фильмы')
                    ->description('Что тебе нравится смотреть? (необязательно)')
                    ->schema([
                        Repeater::make('favorite_anime')
                            ->label('')
                            ->schema([
                                TextInput::make('title')
                                    ->label('Название')
                                    ->required()
                                    ->maxLength(100)
                                    ->placeholder('Наруто, Тетрадь Смерти...'),
                            ])
                            ->maxItems(10)
                            ->defaultItems(0)
                            ->addActionLabel('Добавить')
                            ->collapsible()
                            ->itemLabel(fn(array $state): ?string => $state['title'] ?? null)
                            ->helperText('Общие интересы увеличивают количество запросов на сессии.'),
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

        if (!$worker->telegram_chat_id) {
            Notification::make()
                ->title('Telegram не подтверждён')
                ->body('Пожалуйста, нажми "Подтвердить Telegram" и запусти бота перед отправкой анкеты.')
                ->danger()
                ->send();
            return;
        }

        $data = $this->form->getState();

        $worker->update([
            'display_name' => $data['display_name'],
            'age' => $data['age'],
            'city' => $data['city'],
            'timezone' => $data['timezone'],
            'description' => $data['description'],
            'experience' => $data['experience'],
            'preferred_format' => $data['preferred_format'],
            'photo_main' => $data['photo_main'],
            'photos_gallery' => $data['photos_gallery'],
            'audio_path' => $data['audio_path'],
            'favorite_games' => $data['favorite_games'],
            'favorite_anime' => $data['favorite_anime'],
            'onboarding_status' => 'pending_approval',
        ]);

        // Send TG notification to admins
        $this->notifyAdmins($worker);

        Notification::make()
            ->title('Анкета отправлена! ✅')
            ->body('Мы проверим твою анкету и свяжемся.')
            ->success()
            ->send();

        $this->redirect('/worker/onboarding-pending');
    }

    private function notifyAdmins(Worker $worker): void
    {
        $botToken = config('services.telegram.admin_bot_token') ?: config('services.telegram.bot_token');
        $adminChatId = config('services.telegram.admin_chat_id');

        if (!$botToken || !$adminChatId) {
            Log::warning('Telegram admin_bot_token or admin_chat_id not configured for onboarding notification.');
            return;
        }

        $opsUrl = config('app.url', 'https://ops.egirlz.chat');
        $text = "📋 *Новая анкета E-Girl!*\n\n"
            . "👩 *Имя:* {$worker->display_name}\n"
            . "📱 *Telegram:* @{$worker->telegram}\n"
            . "🏙 *Город:* {$worker->city}\n"
            . "🎂 *Возраст:* {$worker->age}\n\n"
            . "[Открыть в OPS]({$opsUrl}/admin/workers/{$worker->id}/edit)";

        try {
            Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                'chat_id' => $adminChatId,
                'text' => $text,
                'parse_mode' => 'Markdown',
                'disable_web_page_preview' => true,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to send onboarding TG notification: ' . $e->getMessage());
        }
    }

    private function getWorker(): ?Worker
    {
        return Filament::auth()->user()?->workerProfile;
    }

    private function getRedirectForStatus(string $status): string
    {
        return match ($status) {
            'pending_approval' => '/worker/onboarding-pending',
            'step2' => '/worker/onboarding-step-2',
            'completed' => '/worker',
            default => '/worker/onboarding-step-1',
        };
    }

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();
        return $user && $user->role === 'worker';
    }
}
