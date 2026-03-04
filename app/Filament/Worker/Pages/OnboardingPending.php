<?php

namespace App\Filament\Worker\Pages;

use Filament\Facades\Filament;
use Filament\Pages\Page;

class OnboardingPending extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-clock';
    protected static ?string $navigationLabel = 'Ожидание';
    protected static string $view = 'filament.worker.pages.onboarding-pending';
    protected static ?string $title = 'Анкета на проверке';
    protected static ?string $slug = 'onboarding-pending';
    protected static bool $shouldRegisterNavigation = false;

    public function mount(): void
    {
        $worker = Filament::auth()->user()?->workerProfile;

        if (!$worker || $worker->onboarding_status !== 'pending_approval') {
            $target = match ($worker?->onboarding_status) {
                'step1' => '/worker/onboarding-step-1',
                'step2' => '/worker/onboarding-step-2',
                'completed' => '/worker',
                default => '/worker/onboarding-step-1',
            };
            $this->redirect($target);
        }
    }

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();
        return $user && $user->role === 'worker';
    }
}
