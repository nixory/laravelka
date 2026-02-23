<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientOrderController extends Controller
{
    /**
     * GET /api/client/order-status?external_order_id=1027
     *
     * Returns the current OPS status of an order for the client-facing thank you page.
     * Secured by X-Ops-Webhook-Token (same token used by WP plugin).
     */
    public function status(Request $request): JsonResponse
    {
        $this->authorizeRequest($request);

        $externalOrderId = (string) $request->query('external_order_id', '');
        if ($externalOrderId === '') {
            return response()->json(['ok' => false, 'message' => 'external_order_id required'], 400);
        }

        $order = Order::query()
            ->where('external_source', 'woocommerce')
            ->where('external_order_id', $externalOrderId)
            ->with('worker')
            ->first();

        if (!$order) {
            return response()->json(['ok' => false, 'message' => 'Order not found'], 404);
        }

        $stepMap = [
            Order::STATUS_NEW => 0,
            Order::STATUS_ASSIGNED => 1,
            Order::STATUS_ACCEPTED => 2,
            Order::STATUS_IN_PROGRESS => 3,
            Order::STATUS_DONE => 4,
            Order::STATUS_CANCELLED => -1,
        ];

        $step = $stepMap[$order->status] ?? 0;

        return response()->json([
            'ok' => true,
            'status' => $order->status,
            'status_label' => $order->statusLabel(),
            'step' => $step,
            'worker_name' => $order->worker?->display_name,
        ]);
    }

    private function authorizeRequest(Request $request): void
    {
        $configuredToken = (string) config('services.woo.webhook_token');
        $incomingToken = (string) ($request->header('X-Ops-Webhook-Token') ?: $request->bearerToken());

        if ($configuredToken !== '' && $incomingToken !== '' && hash_equals($configuredToken, $incomingToken)) {
            return;
        }

        response()->json([
            'ok' => false,
            'message' => 'Unauthorized',
        ], 401)->throwResponse();
    }
}
