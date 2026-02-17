<?php

use App\Models\Order;
use App\Models\OrderDeclineRequest;
use App\Models\User;
use App\Models\WithdrawalRequest;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/tg/admin/orders/{order}', function (Order $order) {
    $user = auth()->user();

    if (! $user) {
        session(['url.intended' => url("/admin/orders/{$order->id}/view")]);
        return redirect('/admin/login');
    }

    if ($user->role === User::ROLE_ADMIN) {
        return redirect("/admin/orders/{$order->id}/view");
    }

    if ($user->role === User::ROLE_WORKER) {
        return redirect("/worker/orders/{$order->id}");
    }

    return redirect('/');
});

Route::get('/tg/admin/withdrawals/{withdrawalRequest}', function (WithdrawalRequest $withdrawalRequest) {
    $user = auth()->user();

    if (! $user) {
        session(['url.intended' => url("/admin/withdrawal-requests/{$withdrawalRequest->id}/edit")]);
        return redirect('/admin/login');
    }

    if ($user->role === User::ROLE_ADMIN) {
        return redirect("/admin/withdrawal-requests/{$withdrawalRequest->id}/edit");
    }

    return redirect('/');
});

Route::get('/tg/admin/declines/{orderDeclineRequest}', function (OrderDeclineRequest $orderDeclineRequest) {
    $user = auth()->user();

    if (! $user) {
        session(['url.intended' => url("/admin/order-decline-requests/{$orderDeclineRequest->id}/edit")]);
        return redirect('/admin/login');
    }

    if ($user->role === User::ROLE_ADMIN) {
        return redirect("/admin/order-decline-requests/{$orderDeclineRequest->id}/edit");
    }

    return redirect('/');
});

Route::get('/tg/worker/orders/{order}', function (Order $order) {
    $user = auth()->user();

    if (! $user) {
        session(['url.intended' => url("/worker/orders/{$order->id}")]);
        return redirect('/worker/login');
    }

    if ($user->role === User::ROLE_WORKER) {
        return redirect("/worker/orders/{$order->id}");
    }

    if ($user->role === User::ROLE_ADMIN) {
        return redirect("/admin/orders/{$order->id}/view");
    }

    return redirect('/');
});
