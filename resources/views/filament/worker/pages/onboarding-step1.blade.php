<x-filament-panels::page>
    <div class="max-w-3xl mx-auto">
        <div class="mb-8 p-6 rounded-2xl border border-white/10 bg-white/[.03]">
            <div class="flex items-center gap-3 mb-3">
                <span class="text-2xl">📋</span>
                <h2 class="text-lg font-bold text-white">Шаг 1 из 2</h2>
            </div>
            <p class="text-sm text-gray-400">
                Заполни информацию о себе — это будет основой твоего профиля на E-GIRLZ.
                После отправки мы проверим анкету и откроем доступ к настройке услуг.
            </p>
            <div class="mt-4 flex gap-2">
                <div class="h-1.5 rounded-full bg-amber-500 flex-1"></div>
                <div class="h-1.5 rounded-full bg-white/10 flex-1"></div>
            </div>
        </div>

        <form wire:submit="submit">
            {{ $this->form }}

            <div class="mt-6 flex gap-3">
                <x-filament::button type="submit" color="primary" icon="heroicon-o-paper-airplane" size="lg">
                    Отправить анкету на проверку
                </x-filament::button>
            </div>
        </form>
    </div>
</x-filament-panels::page>