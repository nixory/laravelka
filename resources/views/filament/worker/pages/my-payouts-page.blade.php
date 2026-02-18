<x-filament-panels::page>
    {{-- Balance Cards --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3 mb-6">
        {{-- Available --}}
        <div
            class="rounded-2xl border border-white/10 bg-gradient-to-br from-emerald-900/40 to-emerald-950/60 p-5 shadow-lg">
            <p class="text-xs font-semibold uppercase tracking-widest text-emerald-400 mb-1">Доступно к выводу</p>
            <p class="text-3xl font-bold text-white">{{ $this->getAvailableBalance() }}</p>
            <p class="text-xs text-white/40 mt-1">Подтверждённый баланс</p>
        </div>
        {{-- Expected --}}
        <div class="rounded-2xl border border-white/10 bg-gradient-to-br from-blue-900/40 to-blue-950/60 p-5 shadow-lg">
            <p class="text-xs font-semibold uppercase tracking-widest text-blue-400 mb-1">Ожидается</p>
            <p class="text-3xl font-bold text-white">{{ $this->getExpectedBalance() }}</p>
            <p class="text-xs text-white/40 mt-1">Заказы в работе</p>
        </div>
        {{-- In process --}}
        <div
            class="rounded-2xl border border-white/10 bg-gradient-to-br from-amber-900/40 to-amber-950/60 p-5 shadow-lg">
            <p class="text-xs font-semibold uppercase tracking-widest text-amber-400 mb-1">В обработке</p>
            <p class="text-3xl font-bold text-white">{{ $this->getInProcessBalance() }}</p>
            <p class="text-xs text-white/40 mt-1">Заявки на вывод</p>
        </div>
    </div>

    {{-- Recent Withdrawals --}}
    @php $withdrawals = $this->getRecentWithdrawals(); @endphp
    @if($withdrawals->isNotEmpty())
        <div class="mb-6">
            <h2 class="text-sm font-semibold uppercase tracking-widest text-white/50 mb-3">Последние заявки на вывод</h2>
            <div class="rounded-2xl border border-white/10 overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-white/10 text-white/40 text-xs uppercase">
                            <th class="px-4 py-3 text-left">Дата</th>
                            <th class="px-4 py-3 text-left">Сумма</th>
                            <th class="px-4 py-3 text-left">Метод</th>
                            <th class="px-4 py-3 text-left">Статус</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($withdrawals as $wr)
                            <tr class="border-b border-white/5 hover:bg-white/5 transition-colors">
                                <td class="px-4 py-3 text-white/70">
                                    {{ $wr->requested_at?->setTimezone('Europe/Moscow')->format('d.m.Y H:i') ?? '—' }}</td>
                                <td class="px-4 py-3 font-semibold text-white">
                                    {{ number_format((float) $wr->amount, 2, '.', ' ') }} {{ $wr->currency }}</td>
                                <td class="px-4 py-3 text-white/60">
                                    {{ match ($wr->payment_method) { 'sbp' => 'СБП', 'card' => 'Карта', 'usdt' => 'USDT', default => $wr->payment_method} }}
                                </td>
                                <td class="px-4 py-3">
                                    @php
                                        $statusColor = match ($wr->status) {
                                            'pending' => 'text-amber-400 bg-amber-400/10',
                                            'approved' => 'text-blue-400 bg-blue-400/10',
                                            'paid' => 'text-emerald-400 bg-emerald-400/10',
                                            'rejected' => 'text-red-400 bg-red-400/10',
                                            default => 'text-white/40 bg-white/5',
                                        };
                                        $statusLabel = match ($wr->status) {
                                            'pending' => 'На рассмотрении',
                                            'approved' => 'Одобрено',
                                            'paid' => 'Выплачено',
                                            'rejected' => 'Отклонено',
                                            default => $wr->status,
                                        };
                                    @endphp
                                    <span
                                        class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $statusColor }}">{{ $statusLabel }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Recent Transactions --}}
    @php $transactions = $this->getRecentTransactions(); @endphp
    @if($transactions->isNotEmpty())
        <div>
            <h2 class="text-sm font-semibold uppercase tracking-widest text-white/50 mb-3">История транзакций</h2>
            <div class="rounded-2xl border border-white/10 overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-white/10 text-white/40 text-xs uppercase">
                            <th class="px-4 py-3 text-left">Дата</th>
                            <th class="px-4 py-3 text-left">Тип</th>
                            <th class="px-4 py-3 text-left">Сумма</th>
                            <th class="px-4 py-3 text-left">Статус</th>
                            <th class="px-4 py-3 text-left">Описание</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($transactions as $tx)
                            <tr class="border-b border-white/5 hover:bg-white/5 transition-colors">
                                <td class="px-4 py-3 text-white/70">
                                    {{ $tx->occurred_at?->setTimezone('Europe/Moscow')->format('d.m.Y H:i') ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    @if($tx->type === 'credit')
                                        <span class="text-emerald-400 font-medium">+ Начисление</span>
                                    @else
                                        <span class="text-red-400 font-medium">− Списание</span>
                                    @endif
                                </td>
                                <td
                                    class="px-4 py-3 font-semibold {{ $tx->type === 'credit' ? 'text-emerald-300' : 'text-red-300' }}">
                                    {{ $tx->type === 'credit' ? '+' : '-' }}{{ number_format((float) $tx->amount, 2, '.', ' ') }}
                                    {{ $tx->currency }}
                                </td>
                                <td class="px-4 py-3">
                                    @php
                                        $txColor = match ($tx->status) {
                                            'pending' => 'text-amber-400 bg-amber-400/10',
                                            'confirmed' => 'text-emerald-400 bg-emerald-400/10',
                                            'cancelled' => 'text-red-400 bg-red-400/10',
                                            default => 'text-white/40 bg-white/5',
                                        };
                                        $txLabel = match ($tx->status) {
                                            'pending' => 'Ожидает',
                                            'confirmed' => 'Подтверждено',
                                            'cancelled' => 'Отменено',
                                            default => $tx->status,
                                        };
                                    @endphp
                                    <span
                                        class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $txColor }}">{{ $txLabel }}</span>
                                </td>
                                <td class="px-4 py-3 text-white/50 truncate max-w-xs">{{ $tx->description ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if($transactions->isEmpty() && $withdrawals->isEmpty())
        <div class="text-center py-16 text-white/30">
            <x-heroicon-o-banknotes class="w-12 h-12 mx-auto mb-3 opacity-30" />
            <p class="text-lg font-medium">Транзакций пока нет</p>
            <p class="text-sm mt-1">Здесь появится история выплат</p>
        </div>
    @endif

    <x-filament-actions::modals />
</x-filament-panels::page>