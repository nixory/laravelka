<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderDeclineRequest;
use App\Models\Worker;
use App\Models\WithdrawalRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramNotifier
{
    public function notifyAdminNewProcessingOrder(Order $order): void
    {
        $text = implode("\n", [
            "🆕 <b>Новый заказ (processing)</b>",
            "Order #{$order->id} (Woo #{$order->external_order_id})",
            'Клиент: '.($order->client_name ?: '-'),
            'Товар: '.($order->service_name ?: '-'),
            'Сумма: '.number_format((float) $order->service_price, 2, '.', ' ').' RUB',
        ]);

        $this->sendToAdmin($text, [[
            ['text' => 'Открыть заказ', 'url' => $this->adminOrderUrl($order)],
        ]]);
    }

    public function notifyAdminWithdrawalRequested(WithdrawalRequest $request): void
    {
        $text = implode("\n", [
            "💸 <b>Новая заявка на вывод</b>",
            "Request #{$request->id}",
            'Воркер: '.($request->worker?->display_name ?: '-'),
            'Сумма: '.number_format((float) $request->amount, 2, '.', ' ').' '.($request->currency ?: 'RUB'),
            'Метод: '.($request->payment_method ?: '-'),
        ]);

        $this->sendToAdmin($text, [[
            ['text' => 'Открыть заявку', 'url' => $this->adminWithdrawalUrl($request)],
        ]]);
    }

    public function notifyAdminWorkerAccepted(Order $order): void
    {
        $text = implode("\n", [
            "✅ <b>Работница взялась за заказ</b>",
            "Order #{$order->id}",
            'Воркер: '.($order->worker?->display_name ?: '-'),
            'Клиент: '.($order->client_name ?: '-'),
        ]);

        $this->sendToAdmin($text, [[
            ['text' => 'Открыть заказ', 'url' => $this->adminOrderUrl($order)],
        ]]);
    }

    public function notifyAdminWorkerDeclined(Order $order, OrderDeclineRequest $declineRequest): void
    {
        $text = implode("\n", [
            "❌ <b>Работница отказалась от заказа</b>",
            "Order #{$order->id}",
            'Воркер: '.($declineRequest->worker?->display_name ?: '-'),
            'Причина: '.($declineRequest->reason_code ?: '-'),
            'Комментарий: '.(($declineRequest->reason_text ?: '-') ?: '-'),
        ]);

        $this->sendToAdmin($text, [[
            ['text' => 'Открыть заказ', 'url' => $this->adminOrderUrl($order)],
            ['text' => 'Открыть отказ', 'url' => $this->adminDeclineUrl($declineRequest)],
        ]]);
    }

    public function notifyWorkerNewOrder(Order $order): void
    {
        $worker = $order->worker;
        if (! $worker) {
            return;
        }

        $chatId = $this->resolveWorkerChatId($worker);
        if (! $chatId) {
            return;
        }

        $text = implode("\n", [
            "📥 <b>Новый заказ</b>",
            "Order #{$order->id}",
            'Клиент: '.($order->client_name ?: '-'),
            'Товар: '.($order->service_name ?: '-'),
            'Сумма: '.number_format((float) $order->service_price, 2, '.', ' ').' RUB',
        ]);

        $this->send(
            $chatId,
            $text,
            [[['text' => 'Открыть заказ', 'url' => $this->workerOrderUrl($order)]]]
        );
    }

    public function notifyWorkerStartReminder(Order $order, int $minutesBefore): void
    {
        $worker = $order->worker;
        if (! $worker || ! $order->starts_at) {
            return;
        }

        $chatId = $this->resolveWorkerChatId($worker);
        if (! $chatId) {
            return;
        }

        $text = implode("\n", [
            "⏰ <b>Напоминание о заказе</b>",
            "Order #{$order->id} стартует через {$minutesBefore} мин.",
            'Время старта: '.$order->starts_at->timezone($worker->timezone ?: 'UTC')->format('d.m.Y H:i'),
            'Клиент: '.($order->client_name ?: '-'),
        ]);

        $this->send(
            $chatId,
            $text,
            [[['text' => 'Открыть заказ', 'url' => $this->workerOrderUrl($order)]]]
        );
    }

    public function notifyAdminUnassignedOrder(Order $order, int $ageMinutes): void
    {
        $text = implode("\n", [
            "⚠️ <b>Заказ без назначенного воркера</b>",
            "Order #{$order->id} (Woo #{$order->external_order_id})",
            "Возраст: {$ageMinutes} мин",
            'Клиент: '.($order->client_name ?: '-'),
        ]);

        $this->sendToAdmin($text, [[
            ['text' => 'Открыть заказ', 'url' => $this->adminOrderUrl($order)],
        ]]);
    }

    public function notifyAdminOrderStartsSoonNotAccepted(Order $order, int $minutesLeft): void
    {
        $text = implode("\n", [
            "🚨 <b>Скоро старт заказа, но нет подтверждения</b>",
            "Order #{$order->id} стартует через {$minutesLeft} мин",
            'Текущий статус: '.($order->status ?: '-'),
            'Воркер: '.($order->worker?->display_name ?: '-'),
        ]);

        $this->sendToAdmin($text, [[
            ['text' => 'Открыть заказ', 'url' => $this->adminOrderUrl($order)],
        ]]);
    }

    public function notifyAdminWebhookFailed(array $payload): void
    {
        $event = (string) ($payload['event'] ?? 'order-updated');
        $orderId = (string) ($payload['order_id'] ?? '-');
        $attempt = (string) ($payload['attempt'] ?? '-');
        $httpCode = isset($payload['http_code']) ? (string) $payload['http_code'] : '-';
        $error = (string) ($payload['error'] ?? '');

        $text = implode("\n", [
            "🧯 <b>Webhook Woo -> OPS упал</b>",
            "Event: {$event}",
            "Order ID: {$orderId}",
            "Attempt: {$attempt}",
            "HTTP: {$httpCode}",
            'Error: '.($error !== '' ? mb_strimwidth($error, 0, 250, '...') : '-'),
        ]);

        $this->sendToAdmin($text, [[
            ['text' => 'Открыть админку', 'url' => rtrim((string) config('services.telegram.admin_panel_url'), '/')],
        ]]);
    }

    private function sendToAdmin(string $text, array $buttons = []): void
    {
        $chatId = (string) config('services.telegram.admin_chat_id');
        if ($chatId === '') {
            return;
        }

        $this->send($chatId, $text, $buttons);
    }

    private function send(string $chatId, string $text, array $buttons = []): void
    {
        $token = (string) config('services.telegram.bot_token');
        if ($token === '' || $chatId === '') {
            return;
        }

        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        ];

        if ($buttons !== []) {
            $payload['reply_markup'] = json_encode([
                'inline_keyboard' => $buttons,
            ], JSON_UNESCAPED_UNICODE);
        }

        try {
            Http::timeout(10)
                ->asForm()
                ->post("https://api.telegram.org/bot{$token}/sendMessage", $payload)
                ->throw();
        } catch (\Throwable $e) {
            Log::warning('Telegram notify failed', [
                'message' => $e->getMessage(),
                'chat_id' => $chatId,
            ]);
        }
    }

    private function resolveWorkerChatId(Worker $worker): ?string
    {
        foreach ([(string) ($worker->telegram_chat_id ?? ''), (string) ($worker->telegram ?? '')] as $value) {
            $value = trim($value);
            if ($value === '') {
                continue;
            }
            if (preg_match('/^-?[0-9]+$/', $value) === 1) {
                return $value;
            }
        }
        return null;
    }

    private function adminOrderUrl(Order $order): string
    {
        return rtrim((string) config('app.url', 'https://ops.egirlz.chat'), '/')."/tg/admin/orders/{$order->id}";
    }

    private function adminWithdrawalUrl(WithdrawalRequest $request): string
    {
        return rtrim((string) config('app.url', 'https://ops.egirlz.chat'), '/')."/tg/admin/withdrawals/{$request->id}";
    }

    private function adminDeclineUrl(OrderDeclineRequest $request): string
    {
        return rtrim((string) config('app.url', 'https://ops.egirlz.chat'), '/')."/tg/admin/declines/{$request->id}";
    }

    private function workerOrderUrl(Order $order): string
    {
        return rtrim((string) config('app.url', 'https://ops.egirlz.chat'), '/')."/tg/worker/orders/{$order->id}";
    }
}
