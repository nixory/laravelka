<?php

namespace App\Observers;

use App\Models\Order;
use App\Services\OrderAssignmentService;

class OrderObserver
{
    public function __construct(private readonly OrderAssignmentService $assignmentService)
    {
    }

    public function created(Order $order): void
    {
        $this->assignmentService->assign($order);
    }

    public function updated(Order $order): void
    {
        if ($order->isAutoAssignable() && ($order->wasChanged('status') || $order->wasChanged('worker_id'))) {
            $this->assignmentService->assign($order);
        }
    }
}
