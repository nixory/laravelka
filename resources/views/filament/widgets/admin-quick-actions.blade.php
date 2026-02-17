<x-filament-widgets::widget>
    <x-filament::section>
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            <a href="{{ $newOrdersUrl }}" class="fi-btn fi-btn-color-warning fi-color-warning fi-size-md inline-flex items-center justify-between gap-3 rounded-xl px-4 py-3">
                <span class="text-sm font-medium">Новые заказы</span>
                <span class="rounded-lg bg-white/10 px-2 py-1 text-xs font-semibold">{{ $newOrders }}</span>
            </a>

            <a href="{{ $unassignedOrdersUrl }}" class="fi-btn fi-btn-color-info fi-color-info fi-size-md inline-flex items-center justify-between gap-3 rounded-xl px-4 py-3">
                <span class="text-sm font-medium">Без работницы</span>
                <span class="rounded-lg bg-white/10 px-2 py-1 text-xs font-semibold">{{ $unassignedOrders }}</span>
            </a>

            <a href="{{ $withdrawalsUrl }}" class="fi-btn fi-btn-color-danger fi-color-danger fi-size-md inline-flex items-center justify-between gap-3 rounded-xl px-4 py-3">
                <span class="text-sm font-medium">Заявки на вывод</span>
                <span class="rounded-lg bg-white/10 px-2 py-1 text-xs font-semibold">{{ $pendingWithdrawals }}</span>
            </a>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
