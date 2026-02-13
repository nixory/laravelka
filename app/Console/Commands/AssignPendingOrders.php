<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\OrderAssignmentService;
use Illuminate\Console\Command;

class AssignPendingOrders extends Command
{
    protected $signature = 'ops:assign-pending-orders {--limit=50 : Max pending orders to process}';

    protected $description = 'Auto-assign pending new orders to available workers';

    public function handle(OrderAssignmentService $assignmentService): int
    {
        $limit = max((int) $this->option('limit'), 1);

        $orders = Order::query()
            ->where('status', Order::STATUS_NEW)
            ->whereNull('worker_id')
            ->orderBy('created_at')
            ->limit($limit)
            ->get();

        if ($orders->isEmpty()) {
            $this->info('No pending orders to assign.');

            return self::SUCCESS;
        }

        $assigned = 0;

        foreach ($orders as $order) {
            if ($assignmentService->assign($order)) {
                $assigned++;
            }
        }

        $this->info("Processed {$orders->count()} order(s), assigned {$assigned}.");

        return self::SUCCESS;
    }
}
