<?php

use App\Http\Controllers\Api\WooWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/integrations/woo/order-created', [WooWebhookController::class, 'orderCreated'])
    ->middleware('throttle:60,1');

Route::post('/integrations/woo/order-updated', [WooWebhookController::class, 'orderCreated'])
    ->middleware('throttle:60,1');
