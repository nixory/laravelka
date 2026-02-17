<?php

namespace App\Observers;

use App\Models\Order;
use App\Models\PayoutTransaction;
use App\Services\OrderAssignmentService;
use App\Services\TelegramNotifier;

class OrderObserver
{
    public function __construct(
        private readonly OrderAssignmentService $assignmentService,
        private readonly TelegramNotifier $telegramNotifier
    )
    {
    }

    public function created(Order $order): void
    {
        $this->assignmentService->assign($order);

        if ($order->worker_id && $order->status === Order::STATUS_ASSIGNED) {
            $this->telegramNotifier->notifyWorkerNewOrder($order->fresh(['worker']));
        }
    }

    public function updated(Order $order): void
    {
        if ($order->isAutoAssignable() && ($order->wasChanged('status') || $order->wasChanged('worker_id'))) {
            $this->assignmentService->assign($order);
        }

        if (
            ($order->wasChanged('worker_id') || $order->wasChanged('status'))
            && $order->worker_id
            && $order->status === Order::STATUS_ASSIGNED
        ) {
            $this->telegramNotifier->notifyWorkerNewOrder($order->fresh(['worker']));
        }

        if ($order->wasChanged('status') && $order->status === Order::STATUS_DONE) {
            $this->applyWorkerCompletionPayout($order);
        }
    }

    private function applyWorkerCompletionPayout(Order $order): void
    {
        if (! $order->worker_id) {
            return;
        }

        $alreadyExists = PayoutTransaction::query()
            ->where('order_id', $order->id)
            ->where('worker_id', $order->worker_id)
            ->where('type', 'credit')
            ->where('status', 'confirmed')
            ->exists();

        if ($alreadyExists) {
            return;
        }

        $baseAmount = (float) $order->service_price;
        $payoutAmount = round($baseAmount * 0.5, 2);

        if ($payoutAmount <= 0) {
            return;
        }

        PayoutTransaction::query()->create([
            'worker_id' => $order->worker_id,
            'order_id' => $order->id,
            'type' => 'credit',
            'status' => 'confirmed',
            'amount' => $payoutAmount,
            'currency' => 'RUB',
            'description' => sprintf('Order #%d completed payout (50%%)', $order->id),
            'meta' => [
                'rule' => 'order_completed_50_percent',
                'order_service_price' => $baseAmount,
            ],
            'occurred_at' => now(),
        ]);
    }
}
