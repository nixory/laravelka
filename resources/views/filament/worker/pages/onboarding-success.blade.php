<x-filament-panels::page>
    <x-filament-panels::page>
        <div class="max-w-2xl mx-auto text-center py-12">
            <div class="mb-6 p-8 rounded-2xl border border-primary-500/20 bg-primary-500/5">
                <span class="text-5xl block mb-4">🎉</span>
                <h2 class="text-xl font-bold text-white mb-3">Анкета на стадии публикации</h2>
                <p class="text-gray-400 mb-6">
                    Спасибо, что заполнила анкету. Твоя анкета сейчас на стадии модерации и публикации. Как только её
                    опубликуют, тебе откроется доступ к админпанели, и тебе придёт уведомление в Телеграм-бота. Ожидай.
                </p>

                <div class="flex gap-2 max-w-xs mx-auto mb-6">
                    <div class="h-1.5 rounded-full bg-green-500 flex-1"></div>
                    <div class="h-1.5 rounded-full bg-green-500 flex-1"></div>
                    <div class="h-1.5 rounded-full bg-primary-500 flex-1 animate-pulse"></div>
                </div>

                <div class="grid grid-cols-3 gap-3 max-w-sm mx-auto text-xs text-gray-500">
                    <div class="p-3 rounded-xl bg-green-500/10 border border-green-500/20">
                        <span class="text-green-400 block text-sm mb-1">✅</span>
                        Анкета
                    </div>
                    <div class="p-3 rounded-xl bg-green-500/10 border border-green-500/20">
                        <span class="text-green-400 block text-sm mb-1">✅</span>
                        Проверка
                    </div>
                    <div class="p-3 rounded-xl bg-primary-500/10 border border-primary-500/20">
                        <span class="text-primary-400 block text-sm mb-1">👀</span>
                        Публикация
                    </div>
                </div>
            </div>

            <div class="mt-6">
                <x-filament::button tag="a" href="https://t.me/egirlz_support" target="_blank" color="primary"
                    icon="heroicon-o-chat-bubble-left-right">
                    Перейти в чат с менеджером
                </x-filament::button>
            </div>

            <p class="text-sm text-gray-500 mt-4">
                В случае возникновения вопросов ты можешь связаться с нашим менеджером.
            </p>

            <div class="mt-6 hidden">
                <!-- Hidden refresh button for polling/updates if needed -->
                <x-filament::button color="gray" icon="heroicon-o-arrow-path" wire:click="$refresh">
                    Обновить статус
                </x-filament::button>
            </div>
        </div>
    </x-filament-panels::page>