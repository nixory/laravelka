<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
        $lineItems = collect($raw['line_items'] ?? [])->pluck('name')->filter()->values()->implode(', ');
        $wooStatus = (string) ($raw['status'] ?? 'pending');

        $clientName = trim((($billing['first_name'] ?? '').' '.($billing['last_name'] ?? '')));

        $order->fill([
            'client_name' => $clientName !== '' ? $clientName : ($billing['email'] ?? 'Woo Client'),
            'client_phone' => $billing['phone'] ?? null,
            'client_email' => $billing['email'] ?? null,
            'service_name' => $lineItems !== '' ? $lineItems : 'Woo service',
            'service_price' => (float) ($raw['total'] ?? 0),
            'starts_at' => $this->parseDate($this->extractMetaValue($raw, ['starts_at', 'service_start', 'appointment_start'])),
            'ends_at' => $this->parseDate($this->extractMetaValue($raw, ['ends_at', 'service_end', 'appointment_end'])),
            'meta' => [
                'woo_status' => $wooStatus,
                'currency' => $raw['currency'] ?? null,
                'payment_method' => $raw['payment_method_title'] ?? null,
                'line_items' => $raw['line_items'] ?? [],
            ],
        ]);

        if (! $order->exists) {
            $order->status = Order::STATUS_NEW;
        }

        if (in_array($wooStatus, ['cancelled', 'failed', 'refunded'], true)) {
            $order->status = Order::STATUS_CANCELLED;
            $order->cancelled_at = $order->cancelled_at ?: now();
        }

        $order->save();

        if ($order->isAutoAssignable()) {
            $assignmentService->assign($order->fresh());
        }

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
}
