<?php

namespace App\Filament\Worker\Pages;

use App\Models\User;
use App\Models\Worker;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Http\Responses\Auth\Contracts\RegistrationResponse;
use Filament\Notifications\Notification;
use Filament\Pages\Auth\Register;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class WorkerRegistration extends Register
{
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->label('Имя / ник')
                    ->required()
                    ->maxLength(100)
                    ->placeholder('Например: Lera'),

                $this->getEmailFormComponent()
                    ->label('Email')
                    ->placeholder('your@email.com'),

                $this->getPasswordFormComponent()
                    ->label('Пароль'),

                $this->getPasswordConfirmationFormComponent()
                    ->label('Подтвердите пароль'),
            ]);
    }

    public function register(): ?RegistrationResponse
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            Notification::make()
                ->title('Слишком много попыток')
                ->body("Попробуйте через {$exception->secondsUntilAvailable} секунд.")
                ->danger()
                ->send();

            return null;
        }

        $data = $this->form->getState();

        // Create user with worker role
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => User::ROLE_WORKER,
        ]);

        // Create worker profile with step1 status
        $slug = Str::slug($data['name']) . '-' . $user->id;

        Worker::create([
            'user_id' => $user->id,
            'display_name' => $data['name'],
            'slug' => $slug,
            'onboarding_status' => 'step1',
        ]);

        // Log in
        Filament::auth()->login($user);

        session()->regenerate();

        return app(RegistrationResponse::class);
    }
}
