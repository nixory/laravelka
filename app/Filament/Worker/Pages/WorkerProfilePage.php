<?php

namespace App\Filament\Worker\Pages;

use App\Models\Worker;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

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

        $this->form->fill([
            'display_name' => $worker->display_name,
            'phone' => $worker->phone,
            'city' => $worker->city,
            'telegram' => $worker->telegram,
            'timezone' => $worker->timezone ?? 'Europe/Moscow',
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Основная информация')
                    ->schema([
                        TextInput::make('display_name')
                            ->label('Имя (отображается клиентам)')
                            ->required()
                            ->maxLength(100),
                        TextInput::make('phone')
                            ->label('Телефон')
                            ->tel()
                            ->maxLength(30),
                        TextInput::make('city')
                            ->label('Город')
                            ->maxLength(100),
                        Select::make('timezone')
                            ->label('Часовой пояс')
                            ->options([
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
                        TextInput::make('telegram')
                            ->label('Telegram username (@username)')
                            ->prefix('@')
                            ->maxLength(100),
                    ])
                    ->columns(2),

                Section::make('Telegram-бот')
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
            ->statePath('data');
    }

    public function save(): void
    {
        $worker = $this->getWorker();
        if (!$worker) {
            Notification::make()->title('Профиль не найден')->danger()->send();
            return;
        }

        $data = $this->form->getState();

        $worker->update([
            'display_name' => $data['display_name'],
            'phone' => $data['phone'] ?? null,
            'city' => $data['city'] ?? null,
            'telegram' => $data['telegram'] ?? null,
            'timezone' => $data['timezone'] ?? 'Europe/Moscow',
        ]);

        Notification::make()->title('Профиль сохранён')->success()->send();
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Сохранить')
                ->submit('save')
                ->color('primary'),
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
