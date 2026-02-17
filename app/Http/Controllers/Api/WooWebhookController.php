<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CalendarSlot;
use App\Models\Order;
use App\Services\OrderAssignmentService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WooWebhookController extends Controller
{
    public function orderCreated(Request $request, OrderAssignmentService $assignmentService): JsonResponse
    {
        $this->authorizeRequest($request);

        $payload = $request->validate([
            'id' => ['required'],
        ]);

        $raw = $request->all();
        $externalOrderId = (string) $payload['id'];

        $order = Order::query()->firstOrNew([
            'external_source' => 'woocommerce',
            'external_order_id' => $externalOrderId,
        ]);

        $billing = $raw['billing'] ?? [];
        $lineItemsRaw = collect($raw['line_items'] ?? [])->values();
        $lineItems = $lineItemsRaw->pluck('name')->filter()->values()->implode(', ');
        $wooStatus = (string) ($raw['status'] ?? 'pending');

        $clientName = trim((($billing['first_name'] ?? '').' '.($billing['last_name'] ?? '')));

        $selectedWorkerId = $this->extractLineItemMetaValue($lineItemsRaw->all(), ['ID работницы', 'worker_id', 'booking_worker_id']);
        $sessionDate = $this->extractLineItemMetaValue($lineItemsRaw->all(), ['Дата сессии', 'booking_date']);
        $sessionTimeRange = $this->extractLineItemMetaValue($lineItemsRaw->all(), ['Время сессии', 'booking_time']);
        [$sessionStartFromRange, $sessionEndFromRange] = $this->parseSessionRange($sessionDate, $sessionTimeRange);
        $orderMeta = $this->normalizeMetaData($raw['meta_data'] ?? []);

        $order->fill([
            'client_name' => $clientName !== '' ? $clientName : ($billing['email'] ?? 'Woo Client'),
            'client_phone' => $billing['phone'] ?? null,
            'client_email' => $billing['email'] ?? null,
            'service_name' => $lineItems !== '' ? $lineItems : 'Woo service',
            'service_price' => (float) ($raw['total'] ?? 0),
            'starts_at' => $sessionStartFromRange ?: $this->parseDate($this->extractMetaValue($raw, ['starts_at', 'service_start', 'appointment_start'])),
            'ends_at' => $sessionEndFromRange ?: $this->parseDate($this->extractMetaValue($raw, ['ends_at', 'service_end', 'appointment_end'])),
            'meta' => [
                'woo_status' => $wooStatus,
                'currency' => $raw['currency'] ?? null,
                'payment_method' => $raw['payment_method_title'] ?? null,
                'line_items' => $raw['line_items'] ?? [],
                'order_meta' => $orderMeta,
                'billing_tg' => $orderMeta['billing_tg'] ?? null,
                'billing_ds' => $orderMeta['billing_ds'] ?? null,
                'billing_time' => $orderMeta['billing_time'] ?? null,
            ],
        ]);

        if (! $order->exists) {
            $order->status = Order::STATUS_NEW;
        }

        if (is_numeric($selectedWorkerId)) {
            $order->worker_id = (int) $selectedWorkerId;
        }

        if (in_array($wooStatus, ['cancelled', 'failed', 'refunded'], true)) {
            $order->status = Order::STATUS_CANCELLED;
            $order->cancelled_at = $order->cancelled_at ?: now();
        }

        $order->save();

        if ($order->isAutoAssignable()) {
            $assignmentService->assign($order->fresh());
        }

        $this->syncCalendarSlotForWooStatus($order, $wooStatus);

        return response()->json([
            'ok' => true,
            'order_id' => $order->id,
            'status' => $order->status,
            'worker_id' => $order->worker_id,
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
            $start = Carbon::parse(trim($date).' '.trim($matches[1]));
            $end = Carbon::parse(trim($date).' '.trim($matches[2]));

            if ($end->lessThanOrEqualTo($start)) {
                $end = $end->copy()->addDay();
            }

            return [$start, $end];
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
            return Carbon::parse($value);
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
}
