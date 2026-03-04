<?php

namespace App\Filament\Worker\Pages;

use App\Models\Worker;
use App\Models\WorkerProfileEditRequest;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WorkerProfilePage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-user-circle';
    protected static ?string $navigationLabel = 'Мой профиль';
    protected static ?string $navigationGroup = 'Аккаунт';
    protected static ?int $navigationSort = 10;
    protected static string $view = 'filament.worker.pages.worker-profile-page';
    protected static ?string $title = 'Мой профиль';

    public ?array $data = [];

    public function mount(): void
    {
        $worker = $this->getWorker();
        if (!$worker) {
            return;
        }

        // Check for pending requests
        $hasPendingRequest = WorkerProfileEditRequest::where('worker_id', $worker->id)
            ->where('status', 'pending')
            ->exists();

        if ($hasPendingRequest) {
            Notification::make()
                ->title('Заявка на модерации')
                ->body('Твои предыдущие изменения еще проверяются. Ты не можешь редактировать профиль до их одобрения.')
                ->warning()
                ->persistent()
                ->send();
        }

        $servicesCustom = $worker->services_custom ?? [];
        $schedulePrefs = $worker->schedule_preferences ?? [];

        $this->form->fill([
            'display_name' => $worker->display_name,
            'phone' => $worker->phone,
            'city' => $worker->city,
            'telegram' => $worker->telegram,
            'timezone' => $worker->timezone ?? 'Europe/Moscow',
            'age' => $worker->age,
            'description' => $worker->description,
            'experience' => $worker->experience,
            'preferred_format' => $worker->preferred_format,
            'favorite_games' => $worker->favorite_games ?? [],
            'favorite_anime' => $worker->favorite_anime ?? [],
            'photo_main' => $worker->photo_main,
            'photos_gallery' => $worker->photos_gallery ?? [],
            'audio_path' => $worker->audio_path,

            'session_options' => $servicesCustom['session_options'] ?? [],
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

            'schedule_slots' => $schedulePrefs['slots'] ?? [],
        ]);
    }

    public function form(Form $form): Form
    {
        $worker = $this->getWorker();
        $hasPendingRequest = false;
        if ($worker) {
            $hasPendingRequest = WorkerProfileEditRequest::where('worker_id', $worker->id)
                ->where('status', 'pending')
                ->exists();
        }

        return $form
            ->schema([
                Section::make('Основная информация & О себе')
                    ->collapsed()
                    ->schema([
                        TextInput::make('display_name')->label('Имя (отображается клиентам)')->required()->maxLength(100),
                        TextInput::make('phone')->label('Телефон')->tel()->maxLength(30),
                        TextInput::make('city')->label('Город')->maxLength(100),
                        Select::make('timezone')->label('Часовой пояс')->options([
                            'Europe/Moscow' => 'Москва (UTC+3)',
                            'Europe/Samara' => 'Самара (UTC+4)',
                            'Asia/Yekaterinburg' => 'Екатеринбург (UTC+5)',
                            'Asia/Omsk' => 'Омск (UTC+6)',
                            'Asia/Krasnoyarsk' => 'Красноярск (UTC+7)',
                            'Asia/Irkutsk' => 'Иркутск (UTC+8)',
                            'Asia/Yakutsk' => 'Якутск (UTC+9)',
                            'Asia/Vladivostok' => 'Владивосток (UTC+10)',
                        ])->default('Europe/Moscow'),
                        TextInput::make('telegram')->label('Telegram username (@username)')->prefix('@')->maxLength(100)->columnSpanFull(),
                        TextInput::make('age')->label('Возраст')->numeric()->minValue(18)->maxValue(99),
                        Select::make('experience')->label('Опыт')->options(['none' => 'Без опыта', 'some' => 'Немного', 'experienced' => 'Есть опыт']),
                        Select::make('preferred_format')->label('Формат')->options(['chat' => 'Просто общение', 'games' => 'Игры', 'anime' => 'Аниме', 'any' => 'Любое']),
                        Textarea::make('description')->label('Описание (Обо мне)')->rows(4)->columnSpanFull(),
                        Repeater::make('favorite_games')->label('Любимые игры')->schema([TextInput::make('name')->required()])->columnSpanFull(),
                        Repeater::make('favorite_anime')->label('Любимые аниме')->schema([TextInput::make('title')->required()])->columnSpanFull(),
                        FileUpload::make('photo_main')->label('Основное фото')->image()->disk('public')->directory('workers/photos'),
                        FileUpload::make('photos_gallery')->label('Галерея')->image()->multiple()->maxFiles(5)->disk('public')->directory('workers/photos'),
                        FileUpload::make('audio_path')->label('Аудио приветствие')->acceptedFileTypes(['audio/mpeg', 'audio/mp4', 'audio/ogg', 'audio/wav', 'audio/webm'])->disk('public')->directory('workers/audio')->columnSpanFull(),
                    ])->columns(2),

                Section::make('Форматы и услуги')
                    ->collapsed()
                    ->schema([
                        CheckboxList::make('session_options')->label('Разовые опции')->options(['webcam' => 'Включить веб-камеру', 'movies' => 'Смотреть фильмы/аниме вместе', 'night' => 'Доступна ночью'])->columns(3)->columnSpanFull(),
                        Radio::make('flirt_level')->label('Флирт')->options(['friendly' => 'Только дружба', 'playful' => 'Игривый флирт', 'teasing' => 'Комфортно с флиртом'])->columnSpanFull(),
                        CheckboxList::make('character_styles')->label('Стиль общения')->options(['cute' => 'Милая', 'gamer' => 'Геймерша', 'troll' => 'Тролль', 'shy' => 'Стесняшка', 'caring' => 'Заботливая', 'confident' => 'Уверенная'])->columns(3)->columnSpanFull(),

                        Section::make('Платные услуги (Дополнения)')->collapsed()->schema([
                            CheckboxList::make('extra_services_voice')->label('Голос и ASMR')->options(['asmr' => 'ASMR', 'whispering' => 'Шёпот', 'purr' => 'Мурчание', 'voiceover' => 'Озвучка', 'reading' => 'Чтение', 'vm' => 'ГС']),
                            CheckboxList::make('extra_services_roleplay')->label('Ролевые')->options(['roleplay' => 'Ролплей', 'character_switch' => 'Смена роли', 'anime' => 'Аниме', 'tsundere' => 'Цундере', 'caring' => 'Заботливая', 'shy' => 'Стесняшка', 'strict' => 'Строгая', 'troll' => 'Тролль']),
                            CheckboxList::make('extra_services_gaming')->label('Игровые')->options(['coaching' => 'Коучинг', 'replay' => 'Разбор', 'tips' => 'Советы', 'tryhard' => 'Трайхард', 'challenge' => 'Челлендж', 'training' => 'Тренировка']),
                            CheckboxList::make('extra_services_entertainment')->label('Развлечения')->options(['truth_dare' => 'Правда/Действие', 'quiz' => 'Викторины', 'guess_game' => 'Угадай', 'stories' => 'Истории', 'jokes' => 'Анекдоты', 'memes' => 'Мемы']),
                            CheckboxList::make('extra_services_creative')->label('Творчество')->options(['sketch' => 'Скетч', 'rate_art' => 'Оценка творчества', 'wish' => 'Пожелание', 'video_msg' => 'Видеосообщение']),
                            CheckboxList::make('extra_services_activities')->label('Активности')->options(['youtube' => 'YouTube', 'anime' => 'Аниме', 'movies' => 'Фильмы', 'stream' => 'Стрим экрана', 'chat_only' => 'Без игры', 'deep_talk' => 'Дип-ток']),
                        ])->columns(2),

                        Section::make('Фан-клуб')->collapsed()->schema([
                            Toggle::make('fan_club_enabled')->label('Включен')->reactive(),
                            Select::make('fan_club_type')->label('Тип')->options(['sfw' => 'SFW', 'mixed' => 'Смешанный', 'nsfw' => 'NSFW'])->visible(fn(\Filament\Forms\Get $get) => $get('fan_club_enabled')),
                            CheckboxList::make('fan_club_format')->label('Формат')->options(['photo' => 'Фото', 'video' => 'Кружочки', 'posts' => 'Посты', 'voice' => 'ГС', 'chat' => 'Чат'])->visible(fn(\Filament\Forms\Get $get) => $get('fan_club_enabled'))->columns(2),
                            Select::make('fan_club_frequency')->label('Частота')->options(['rarely' => 'Редко', 'often' => 'Часто', 'daily' => 'Каждый день'])->visible(fn(\Filament\Forms\Get $get) => $get('fan_club_enabled')),
                            TextInput::make('fan_club_price')->label('Цена')->numeric()->suffix('₽')->visible(fn(\Filament\Forms\Get $get) => $get('fan_club_enabled')),
                        ])->columns(2),

                        CheckboxList::make('boundaries')->label('Границы')->options(['no_nsfw' => 'Нет интима', 'no_flirt' => 'Без флирта', 'no_webcam' => 'Без вебки', 'no_personal' => 'Без личного', 'no_roleplay' => 'Без ролевых', 'no_asmr' => 'Без ASMR', 'no_media' => 'Без медиа', 'no_night' => 'Без ночных'])->columns(3)->columnSpanFull(),
                        TextInput::make('boundaries_custom')->label('Своя граница')->columnSpanFull(),
                        Select::make('client_interaction_mode')->label('Общение')->options(['talk' => 'Общаюсь', 'team' => 'Команда']),
                        Select::make('promotion_mode')->label('Продвижение')->options(['self' => 'Сама', 'platform' => 'Платформа']),
                        Select::make('content_permission')->label('Контент')->options(['create_videos' => 'Снимаю', 'record_clips' => 'Клипы', 'no_content' => 'Нет']),
                        TextInput::make('custom_services')->label('Свои услуги')->columnSpanFull(),
                    ])->columns(2),

                Section::make('Расписание')
                    ->collapsed()
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
                            ->columns(3),
                    ]),

                Section::make('Telegram-бот')
                    ->collapsed()
                    ->description('Подключи бота чтобы получать уведомления о новых заказах')
                    ->schema([
                        Placeholder::make('telegram_chat_id_info')
                            ->label('Telegram Chat ID')
                            ->content(function (): string {
                                $worker = $this->getWorker();
                                $chatId = $worker?->telegram_chat_id;
                                if ($chatId) {
                                    return "✅ Подключено (ID: {$chatId})";
                                }
                                return '❌ Не подключено';
                            }),
                        Placeholder::make('bot_instructions')
                            ->label('Как подключить')
                            ->content(function (): \Illuminate\Support\HtmlString {
                                $botUrl = config('services.telegram.bot_url', 'https://t.me/your_bot');
                                return new \Illuminate\Support\HtmlString(
                                    "1. Открой бота: <a href=\"{$botUrl}\" target=\"_blank\" style=\"color: #f59e0b; text-decoration: underline;\">{$botUrl}</a><br>" .
                                    '2. Нажми <strong>/start</strong><br>' .
                                    '3. Бот автоматически привяжет твой аккаунт'
                                );
                            }),
                    ])
                    ->columns(2),
            ])
            ->statePath('data')
            ->disabled($hasPendingRequest);
    }

    public function save(): void
    {
        $worker = $this->getWorker();
        if (!$worker) {
            Notification::make()->title('Профиль не найден')->danger()->send();
            return;
        }

        $hasPendingRequest = WorkerProfileEditRequest::where('worker_id', $worker->id)
            ->where('status', 'pending')
            ->exists();

        if ($hasPendingRequest) {
            Notification::make()->title('Ошибка')->body('У тебя уже есть активная заявка на модерации.')->danger()->send();
            return;
        }

        $data = $this->form->getState();

        // If worker is fully completed and published, changes go to moderation
        if ($worker->onboarding_status === 'completed') {
            WorkerProfileEditRequest::create([
                'worker_id' => $worker->id,
                'data' => $data,
                'status' => 'pending',
            ]);

            $this->notifyAdmins($worker);

            Notification::make()
                ->title('Изменения отправлены!')
                ->body('Твои изменения отправлены на проверку модераторам. Они появятся на сайте после одобрения.')
                ->success()
                ->persistent()
                ->send();

            $this->redirect('/worker/worker-profile-page');
            return;
        }

        // Otherwise (e.g. they somehow accessed this page before), we update directly, though this shouldn't happen usually
        $servicesCustom = $worker->services_custom ?? [];
        $servicesCustom['session_options'] = $data['session_options'];
        $servicesCustom['flirt_level'] = $data['flirt_level'];
        $servicesCustom['character_styles'] = $data['character_styles'];
        $servicesCustom['extra_services'] = [
            'voice' => $data['extra_services_voice'],
            'roleplay' => $data['extra_services_roleplay'],
            'gaming' => $data['extra_services_gaming'],
            'entertainment' => $data['extra_services_entertainment'],
            'creative' => $data['extra_services_creative'],
            'activities' => $data['extra_services_activities'],
        ];
        $servicesCustom['fan_club'] = [
            'enabled' => $data['fan_club_enabled'],
            'type' => $data['fan_club_type'],
            'format' => $data['fan_club_format'],
            'frequency' => $data['fan_club_frequency'],
            'price' => $data['fan_club_price'],
        ];
        $servicesCustom['boundaries'] = [
            'list' => $data['boundaries'],
            'custom' => $data['boundaries_custom'],
        ];
        $servicesCustom['client_interaction_mode'] = $data['client_interaction_mode'];
        $servicesCustom['promotion_mode'] = $data['promotion_mode'];
        $servicesCustom['content_permission'] = $data['content_permission'];
        $servicesCustom['custom_services'] = $data['custom_services'];

        $schedulePrefs = $worker->schedule_preferences ?? [];
        $schedulePrefs['slots'] = $data['schedule_slots'];

        $worker->update([
            'display_name' => $data['display_name'],
            'phone' => $data['phone'] ?? null,
            'city' => $data['city'] ?? null,
            'telegram' => $data['telegram'] ?? null,
            'timezone' => $data['timezone'] ?? 'Europe/Moscow',
            'age' => $data['age'] ?? null,
            'description' => $data['description'] ?? null,
            'experience' => $data['experience'] ?? null,
            'preferred_format' => $data['preferred_format'] ?? null,
            'favorite_games' => $data['favorite_games'] ?? null,
            'favorite_anime' => $data['favorite_anime'] ?? null,
            'photo_main' => $data['photo_main'] ?? null,
            'photos_gallery' => $data['photos_gallery'] ?? null,
            'audio_path' => $data['audio_path'] ?? null,
            'services_custom' => $servicesCustom,
            'schedule_preferences' => $schedulePrefs,
        ]);

        Notification::make()->title('Профиль сохранён')->success()->send();
        $this->redirect('/worker/worker-profile-page');
    }

    private function notifyAdmins($worker): void
    {
        $botToken = config('services.telegram.admin_bot_token') ?: config('services.telegram.bot_token');
        $adminChatId = config('services.telegram.admin_chat_id');

        if (!$botToken || !$adminChatId) {
            Log::warning('Telegram admin_bot_token or admin_chat_id not configured for profile edit notification.');
            return;
        }

        $opsUrl = config('app.url', 'https://ops.egirlz.chat');
        $text = "📝 *Новая заявка на изменение профиля!*\n\n"
            . "👩 *Имя:* {$worker->display_name}\n"
            . "📱 *Telegram:* @{$worker->telegram}\n\n"
            . "Воркер отправил изменения в свою анкету. Проверь и одобри или отклони их в админке!\n\n"
            . "[Перейти к Worker Profiles]({$opsUrl}/admin/workers/{$worker->id}/edit)";

        try {
            Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                'chat_id' => $adminChatId,
                'text' => $text,
                'parse_mode' => 'Markdown',
                'disable_web_page_preview' => true,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to send profile edit TG notification: ' . $e->getMessage());
        }
    }

    protected function getFormActions(): array
    {
        $worker = $this->getWorker();
        $hasPendingRequest = false;
        if ($worker) {
            $hasPendingRequest = WorkerProfileEditRequest::where('worker_id', $worker->id)
                ->where('status', 'pending')
                ->exists();
        }

        return [
            Action::make('save')
                ->label('Сохранить профиль')
                ->submit('save')
                ->color('primary')
                ->disabled($hasPendingRequest),
        ];
    }

    private function getWorker(): ?Worker
    {
        return Filament::auth()->user()?->workerProfile;
    }

    public static function canAccess(): bool
    {
        return Filament::auth()->check() && Filament::auth()->user()?->workerProfile !== null;
    }
}
