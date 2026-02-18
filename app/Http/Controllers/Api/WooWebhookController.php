<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CalendarSlot;
use App\Models\Order;
use App\Services\OrderAssignmentService;
use App\Services\TelegramNotifier;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;

class WooWebhookController extends Controller
{
    private const DISPLAY_TIMEZONE = 'Europe/Moscow';

    public function orderCreated(
        Request $request,
        OrderAssignmentService $assignmentService,
        TelegramNotifier $telegramNotifier
    ): JsonResponse
    {
        $this->authorizeRequest($request);

        $payload = $request->validate([
            'id' => ['required'],
        ]);

        $raw = $request->all();
        $externalOrderId = (string) $payload['id'];
        $payloadHash = hash('sha256', (string) json_encode(Arr::sortRecursive($raw), JSON_UNESCAPED_UNICODE));

        $order = Order::query()->firstOrNew([
            'external_source' => 'woocommerce',
            'external_order_id' => $externalOrderId,
        ]);

        if ($order->exists && (string) data_get($order->meta, 'woo_payload_hash') === $payloadHash) {
            return response()->json([
                'ok' => true,
                'duplicate' => true,
                'order_id' => $order->id,
                'status' => $order->status,
                'worker_id' => $order->worker_id,
            ]);
        }

        $previousWooStatus = (string) data_get($order->meta ?? [], 'woo_status', '');

        $billing = $raw['billing'] ?? [];
        $lineItemsRaw = collect($raw['line_items'] ?? [])->values();
        $lineItems = $lineItemsRaw->pluck('name')->filter()->values()->implode(', ');
        $wooStatus = (string) ($raw['status'] ?? 'pending');

        $clientName = trim((($billing['first_name'] ?? '').' '.($billing['last_name'] ?? '')));

        $selectedWorkerId = $this->extractLineItemMetaValue($lineItemsRaw->all(), ['ID работницы', 'worker_id', 'booking_worker_id']);
        $sessionDate = $this->extractLineItemMetaValue($lineItemsRaw->all(), ['Дата сессии', 'booking_date']);
        $sessionTimeRange = $this->extractLineItemMetaValue($lineItemsRaw->all(), ['Время сессии', 'booking_time']);
        [$sessionStartFromRange, $sessionEndFromRange] = $this->parseSessionRange($sessionDate, $sessionTimeRange);
        $sessionStart = $sessionStartFromRange ?: $this->parseDate($this->extractMetaValue($raw, ['starts_at', 'service_start', 'appointment_start']));
        $sessionEnd = $sessionEndFromRange ?: $this->parseDate($this->extractMetaValue($raw, ['ends_at', 'service_end', 'appointment_end']));
        $orderMeta = $this->normalizeMetaData($raw['meta_data'] ?? []);
        $wooPlan = $this->extractLineItemMetaValue($lineItemsRaw->all(), ['План', 'plan', 'tariff']);
        $wooHours = $this->extractLineItemMetaValue($lineItemsRaw->all(), ['Часы', 'hours']);
        $wooAddons = $this->extractLineItemMetaValue($lineItemsRaw->all(), ['Дополнительно', 'addons', 'extra_services']);
        $wooClientTelegram = (string) ($orderMeta['billing_tg'] ?? $orderMeta['billing_telegram'] ?? '');
        $wooClientDiscord = (string) ($orderMeta['billing_ds'] ?? $orderMeta['billing_discord'] ?? '');
        $wooDesiredDateTime = (string) ($orderMeta['billing_time'] ?? '');

        if (
            is_numeric($selectedWorkerId)
            && $sessionStart instanceof Carbon
            && $sessionEnd instanceof Carbon
            && $this->hasCalendarConflict((int) $selectedWorkerId, $sessionStart, $sessionEnd, $order->id ?: null)
        ) {
            $selectedWorkerId = null;
        }

        $order->fill([
            'client_name' => $clientName !== '' ? $clientName : ($billing['email'] ?? 'Woo Client'),
            'client_phone' => $billing['phone'] ?? null,
            'client_email' => $billing['email'] ?? null,
            'service_name' => $lineItems !== '' ? $lineItems : 'Woo service',
            'service_price' => (float) ($raw['total'] ?? 0),
            'woo_status' => $wooStatus,
            'woo_currency' => $raw['currency'] ?? 'RUB',
            'woo_payment_method' => $raw['payment_method_title'] ?? null,
            'woo_plan' => is_string($wooPlan) ? trim($wooPlan) : null,
            'woo_hours' => is_string($wooHours) ? trim($wooHours) : null,
            'woo_addons' => is_string($wooAddons) ? trim($wooAddons) : null,
            'woo_session_date' => $this->parseDateOnly($sessionDate),
            'woo_session_time' => is_string($sessionTimeRange) ? trim($sessionTimeRange) : null,
            'woo_worker_id' => is_numeric($selectedWorkerId) ? (int) $selectedWorkerId : null,
            'woo_client_telegram' => $wooClientTelegram !== '' ? $wooClientTelegram : null,
            'woo_client_discord' => $wooClientDiscord !== '' ? $wooClientDiscord : null,
            'woo_desired_datetime' => $wooDesiredDateTime !== '' ? $wooDesiredDateTime : null,
            'starts_at' => $sessionStart,
            'ends_at' => $sessionEnd,
            'meta' => [
                'woo_status' => $wooStatus,
                'currency' => $raw['currency'] ?? null,
                'payment_method' => $raw['payment_method_title'] ?? null,
                'line_items' => $raw['line_items'] ?? [],
                'order_meta' => $orderMeta,
                'billing_tg' => $orderMeta['billing_tg'] ?? null,
                'billing_ds' => $orderMeta['billing_ds'] ?? null,
                'billing_time' => $orderMeta['billing_time'] ?? null,
                'woo_payload_hash' => $payloadHash,
            ],
        ]);

        if (! $order->exists) {
            $order->status = Order::STATUS_NEW;
        }

        if (is_numeric($selectedWorkerId)) {
            $order->worker_id = (int) $selectedWorkerId;

            if (in_array((string) $order->status, [Order::STATUS_NEW, Order::STATUS_ASSIGNED], true)) {
                $order->status = Order::STATUS_ASSIGNED;
            }
        }

        if (
            $order->worker_id
            && $sessionStart instanceof Carbon
            && $sessionEnd instanceof Carbon
            && $this->hasCalendarConflict((int) $order->worker_id, $sessionStart, $sessionEnd, $order->id ?: null)
        ) {
            $order->worker_id = null;
            if ($order->status !== Order::STATUS_CANCELLED) {
                $order->status = Order::STATUS_NEW;
            }
        }

        if (in_array($wooStatus, ['cancelled', 'failed', 'refunded'], true)) {
            $order->status = Order::STATUS_CANCELLED;
            $order->cancelled_at = $order->cancelled_at ?: now();
        }

        try {
            $order->save();
        } catch (QueryException $e) {
            // Idempotency race: another worker inserted same external order simultaneously.
            $existing = Order::query()->where([
                'external_source' => 'woocommerce',
                'external_order_id' => $externalOrderId,
            ])->first();

            if (! $existing) {
                throw $e;
            }

            $order = $existing;
        }

        if ($order->isAutoAssignable()) {
            $assignmentService->assign($order->fresh());
        }

        $this->syncCalendarSlotForWooStatus($order, $wooStatus);

        if ($wooStatus === 'processing' && $previousWooStatus !== 'processing') {
            $telegramNotifier->notifyAdminNewProcessingOrder($order->fresh(['worker']));
        }

        return response()->json([
            'ok' => true,
            'order_id' => $order->id,
            'status' => $order->status,
            'worker_id' => $order->worker_id,
        ]);
    }

    public function reportFailure(Request $request, TelegramNotifier $telegramNotifier): JsonResponse
    {
        $this->authorizeRequest($request);

        $payload = $request->validate([
            'event' => ['nullable', 'string', 'max:100'],
            'order_id' => ['nullable'],
            'attempt' => ['nullable'],
            'http_code' => ['nullable'],
            'error' => ['nullable', 'string'],
            'response_body' => ['nullable', 'string'],
        ]);

        $telegramNotifier->notifyAdminWebhookFailed($payload);

        return response()->json([
            'ok' => true,
        ]);
    }

    private function authorizeRequest(Request $request): void
    {
        $secret = (string) config('services.woo.webhook_secret');
        $incomingSignature = (string) $request->header('X-Wc-Webhook-Signature');

        if ($secret !== '' && $incomingSignature !== '') {
            $expectedSignature = base64_encode(hash_hmac('sha256', $request->getContent(), $secret, true));

            if (hash_equals($expectedSignature, $incomingSignature)) {
                return;
            }
        }

        $configuredToken = (string) config('services.woo.webhook_token');
        $incomingToken = (string) ($request->header('X-Ops-Webhook-Token') ?: $request->bearerToken());

        if ($configuredToken !== '' && $incomingToken !== '' && hash_equals($configuredToken, $incomingToken)) {
            return;
        }

        response()->json([
            'ok' => false,
            'message' => 'Invalid webhook signature/token.',
        ], 401)->throwResponse();
    }

    private function extractMetaValue(array $payload, array $keys): mixed
    {
        $meta = $payload['meta_data'] ?? [];

        foreach ($meta as $item) {
            $key = $item['key'] ?? null;

            if ($key && in_array($key, $keys, true)) {
                return $item['value'] ?? null;
            }
        }

        return null;
    }

    private function extractLineItemMetaValue(array $lineItems, array $keys): mixed
    {
        foreach ($lineItems as $lineItem) {
            $meta = $lineItem['meta'] ?? null;
            if (! is_array($meta)) {
                continue;
            }

            foreach ($keys as $key) {
                if (array_key_exists($key, $meta) && $meta[$key] !== null && $meta[$key] !== '') {
                    return $meta[$key];
                }
            }
        }

        return null;
    }

    private function parseSessionRange(mixed $date, mixed $timeRange): array
    {
        if (! is_string($date) || trim($date) === '') {
            return [null, null];
        }
        if (! is_string($timeRange) || trim($timeRange) === '') {
            return [null, null];
        }

        if (! preg_match('/^\\s*(\\d{1,2}:\\d{2})\\s*-\\s*(\\d{1,2}:\\d{2})\\s*$/', $timeRange, $matches)) {
            return [null, null];
        }

        try {
            $start = Carbon::parse(trim($date).' '.trim($matches[1]), self::DISPLAY_TIMEZONE);
            $end = Carbon::parse(trim($date).' '.trim($matches[2]), self::DISPLAY_TIMEZONE);

            if ($end->lessThanOrEqualTo($start)) {
                $end = $end->copy()->addDay();
            }

            return [$start->copy()->utc(), $end->copy()->utc()];
        } catch (\Throwable) {
            return [null, null];
        }
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value, self::DISPLAY_TIMEZONE)->utc();
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseDateOnly(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse(trim($value), self::DISPLAY_TIMEZONE)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizeMetaData(mixed $metaData): array
    {
        if (! is_array($metaData)) {
            return [];
        }

        $normalized = [];
        foreach ($metaData as $item) {
            if (! is_array($item)) {
                continue;
            }

            $key = isset($item['key']) ? (string) $item['key'] : '';
            if ($key === '') {
                continue;
            }

            $value = $item['value'] ?? null;
            if (is_array($value)) {
                $normalized[$key] = json_encode($value, JSON_UNESCAPED_UNICODE);
                continue;
            }

            if (is_object($value)) {
                $normalized[$key] = json_encode($value, JSON_UNESCAPED_UNICODE);
                continue;
            }

            $normalized[$key] = $value !== null ? (string) $value : null;
        }

        return $normalized;
    }

    private function syncCalendarSlotForWooStatus(Order $order, string $wooStatus): void
    {
        if (! $order->worker_id || ! $order->starts_at || ! $order->ends_at) {
            return;
        }

        if (in_array($wooStatus, ['processing', 'completed'], true)) {
            CalendarSlot::query()->updateOrCreate(
                ['order_id' => $order->id],
                [
                    'worker_id' => $order->worker_id,
                    'starts_at' => $order->starts_at,
                    'ends_at' => $order->ends_at,
                    'status' => 'booked',
                    'source' => 'order',
                ]
            );
            return;
        }

        if (in_array($wooStatus, ['cancelled', 'failed', 'refunded'], true)) {
            CalendarSlot::query()
                ->where('order_id', $order->id)
                ->update([
                    'status' => 'available',
                    'order_id' => null,
                    'source' => 'manual',
                ]);
        }
    }

    private function hasCalendarConflict(int $workerId, Carbon $startsAtUtc, Carbon $endsAtUtc, ?int $ignoreOrderId = null): bool
    {
        return CalendarSlot::query()
            ->where('worker_id', $workerId)
            ->whereIn('status', ['reserved', 'booked', 'blocked'])
            ->where('starts_at', '<', $endsAtUtc)
            ->where('ends_at', '>', $startsAtUtc)
            ->when($ignoreOrderId, fn ($q) => $q->where(fn ($qq) => $qq->whereNull('order_id')->orWhere('order_id', '!=', $ignoreOrderId)))
            ->exists();
    }
}
