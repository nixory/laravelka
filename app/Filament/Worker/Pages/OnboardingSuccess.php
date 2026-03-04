<?php

namespace App\Filament\Worker\Pages;

use Filament\Facades\Filament;
use Filament\Pages\Page;

class OnboardingSuccess extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-check-badge';
    protected static ?string $navigationLabel = 'Ожидание публикации';
    protected static ?string $title = 'Анкета на модерации';
    protected static string $view = 'filament.worker.pages.onboarding-success';
    protected static bool $shouldRegisterNavigation = false;

    public function mount(): void
    {
        $worker = Filament::auth()->user()?->workerProfile;

        if (!$worker || $worker->onboarding_status !== 'pending_publication') {
            $this->redirect('/worker');
        }
    }

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();
        return $user && $user->role === 'worker';
    }
}
