<?php

$orderId = 99999;
$worker_id = 6;

try {
    // Find the latest hold
    $hold = App\Models\BookingHold::query()->orderBy('id', 'desc')->first();

    if (!$hold) {
        echo "No holds found\n";
        exit;
    }

    echo "Using Hold ID: " . $hold->id . " Token: " . $hold->token . " starts_at: " . $hold->starts_at . "\n";

    $slot = App\Models\CalendarSlot::query()->firstOrNew([
        'worker_id' => (int) $hold->worker_id,
        'starts_at' => $hold->starts_at,
        'ends_at' => $hold->ends_at,
    ]);

    $slot->status = 'booked';
    $slot->source = 'order';
    $slot->order_id = $orderId;
    $slot->save();

    echo "Slot successfully saved with ID: " . $slot->id . "\n";
} catch (\Exception $e) {
    echo "Exception saving slot:\n" . $e->getMessage() . "\n";
}
