<?php

namespace App\Console\Commands;

use App\Models\Worker;
use Illuminate\Console\Command;

class UpdateWorkerStatuses extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ops:update-worker-statuses';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Updates worker online/offline status dynamically based on their schedule.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $workers = Worker::whereIn('status', ['online', 'offline'])
            ->where('is_active', true)
            ->whereNotNull('timezone')
            ->with('availabilities')
            ->get();

        $updatedCount = 0;

        foreach ($workers as $worker) {
            $isOnline = $worker->isAvailableWithinWindow(2);
            $newStatus = $isOnline ? 'online' : 'offline';

            if ($worker->status !== $newStatus) {
                $worker->update(['status' => $newStatus]);
                $updatedCount++;
            }
        }

        $this->info("Updated {$updatedCount} worker statuses based on schedule.");
    }
}
