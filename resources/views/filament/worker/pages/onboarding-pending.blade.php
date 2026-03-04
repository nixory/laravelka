<x-filament-panels::page>
    <div class="max-w-2xl mx-auto text-center py-12">
        <div class="mb-6 p-8 rounded-2xl border border-amber-500/20 bg-amber-500/5">
            <span class="text-5xl block mb-4">⏳</span>
            <h2 class="text-xl font-bold text-white mb-3">Анкета на проверке</h2>
            <p class="text-gray-400 mb-6">
                Мы получили твою анкету и скоро её проверим. Обычно это занимает до 24 часов.
                Как только всё будет одобрено — ты получишь уведомление и сможешь перейти к настройке услуг.
            </p>

            <div class="flex gap-2 max-w-xs mx-auto mb-6">
                <div class="h-1.5 rounded-full bg-green-500 flex-1"></div>
                <div class="h-1.5 rounded-full bg-amber-500 flex-1 animate-pulse"></div>
            </div>

            <div class="grid grid-cols-3 gap-3 max-w-sm mx-auto text-xs text-gray-500">
                <div class="p-3 rounded-xl bg-white/5 border border-white/5">
                    <span class="text-green-400 block text-sm mb-1">✅</span>
                    Анкета
                </div>
                <div class="p-3 rounded-xl bg-amber-500/10 border border-amber-500/20">
                    <span class="text-amber-400 block text-sm mb-1">👀</span>
                    Проверка
                </div>
                <div class="p-3 rounded-xl bg-white/5 border border-white/5 opacity-50">
                    <span class="block text-sm mb-1">🔒</span>
                    Услуги
                </div>
            </div>
        </div>

        <p class="text-sm text-gray-500">
            Если возникнут вопросы, напиши в
            <a href="https://t.me/egirlz_support" target="_blank" rel="noopener"
                class="text-amber-400 hover:underline">поддержку Telegram</a>
        </p>

        <div class="mt-6">
            <x-filament::button color="gray" icon="heroicon-o-arrow-path" wire:click="$refresh">
                Обновить статус
            </x-filament::button>
        </div>
    </div>
</x-filament-panels::page>