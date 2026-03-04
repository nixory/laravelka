<x-filament-panels::page>
    <div class="max-w-3xl mx-auto">
        <div class="mb-8 p-6 rounded-2xl border border-white/10 bg-white/[.03]">
            <div class="flex items-center gap-3 mb-3">
                <span class="text-2xl">⚡</span>
                <h2 class="text-lg font-bold text-white">Шаг 2 из 2 — почти готово!</h2>
            </div>
            <p class="text-sm text-gray-400">
                Твоя анкета одобрена! 🎉 Теперь настрой услуги, которые хочешь предоставлять.
                После этого твой профиль появится в каталоге.
            </p>
            <div class="mt-4 flex gap-2">
                <div class="h-1.5 rounded-full bg-green-500 flex-1"></div>
                <div class="h-1.5 rounded-full bg-amber-500 flex-1"></div>
            </div>
        </div>

        <form wire:submit="submit">
            {{ $this->form }}

            <div class="mt-6 flex gap-3">
                <x-filament::button type="submit" color="primary" icon="heroicon-o-check-circle" size="lg">
                    Завершить настройку профиля
                </x-filament::button>
            </div>
        </form>
    </div>
</x-filament-panels::page>