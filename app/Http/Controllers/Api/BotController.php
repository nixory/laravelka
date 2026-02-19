<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\TelegramNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BotController extends Controller
{
    /**
     * POST /api/bot/link-order
     *
     * Called by the Telegram bot when a client sends /start order_{woo_order_id}.
     * Links the client's Telegram chat_id to the matching order.
     *
     * Headers:
     *   X-Bot-Secret: <TELEGRAM_BOT_SECRET>
     *
     * Body (JSON):
     *   woo_order_id  int     – WooCommerce order ID (external_order_id)
     *   tg_chat_id    string  – Telegram chat_id of the client
     *   tg_username   string? – optional @username
     *   tg_first_name string? – optional first name
     */
    public function linkOrder(Request $request): JsonResponse
    {
        // Simple shared-secret auth
        $secret = config('services.telegram.bot_secret');
        if ($secret && $request->header('X-Bot-Secret') !== $secret) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $data = $request->validate([
            'woo_order_id' => ['required', 'integer'],
            'tg_chat_id' => ['required', 'string', 'max:64'],
            'tg_username' => ['nullable', 'string', 'max:255'],
            'tg_first_name' => ['nullable', 'string', 'max:255'],
        ]);

        $order = Order::query()
            ->where('external_order_id', (string) $data['woo_order_id'])
            ->first();

        if (!$order) {
            return response()->json(['error' => 'Order not found'], 404);
        }

        $order->update(['client_tg_chat_id' => $data['tg_chat_id']]);

        // Send welcome message to client
        app(TelegramNotifier::class)->notifyClientOrderLinked($order->fresh(['worker']));

        return response()->json([
            'ok' => true,
            'order_id' => $order->id,
            'status' => $order->status,
            'worker_name' => $order->worker?->display_name,
        ]);
    }
}
