<?php

use App\Http\Controllers\Api\WooWebhookController;
use App\Http\Controllers\Api\BookingController;
use Illuminate\Support\Facades\Route;

Route::post('/integrations/woo/order-created', [WooWebhookController::class, 'orderCreated'])
    ->middleware('throttle:60,1');

Route::post('/integrations/woo/order-updated', [WooWebhookController::class, 'orderCreated'])
    ->middleware('throttle:60,1');

Route::post('/integrations/woo/report-failure', [WooWebhookController::class, 'reportFailure'])
    ->middleware('throttle:60,1');

Route::get('/bookings/slots-range', [BookingController::class, 'slotsRange'])
    ->middleware('throttle:120,1');

Route::post('/bookings/holds', [BookingController::class, 'hold'])
    ->middleware('throttle:120,1');

Route::post('/bookings/holds/confirm', [BookingController::class, 'confirm'])
    ->middleware('throttle:120,1');

Route::post('/bookings/holds/release', [BookingController::class, 'release'])
    ->middleware('throttle:120,1');
