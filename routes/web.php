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
        session(['url.intended' => url("/tg/admin/orders/{$order->id}")]);
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

Route::get('/tg/admin', function () {
    $user = auth()->user();

    if (! $user) {
        session(['url.intended' => url('/admin')]);
        return redirect('/admin/login');
    }

    if ($user->role === User::ROLE_ADMIN) {
        return redirect('/admin');
    }

    if ($user->role === User::ROLE_WORKER) {
        return redirect('/worker');
    }

    return redirect('/');
});

Route::get('/tg/admin/withdrawals/{withdrawalRequest}', function (WithdrawalRequest $withdrawalRequest) {
    $user = auth()->user();

    if (! $user) {
        session(['url.intended' => url("/tg/admin/withdrawals/{$withdrawalRequest->id}")]);
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
        session(['url.intended' => url("/tg/admin/declines/{$orderDeclineRequest->id}")]);
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
        session(['url.intended' => url("/tg/worker/orders/{$order->id}")]);
        return redirect('/worker/login');
    }

    if ($user->role === User::ROLE_WORKER) {
        $workerId = $user->workerProfile?->id;
        if (! $workerId || (int) $order->worker_id !== (int) $workerId) {
            return redirect('/worker');
        }

        return redirect("/worker/orders/{$order->id}");
    }

    if ($user->role === User::ROLE_ADMIN) {
        return redirect("/admin/orders/{$order->id}/view");
    }

    return redirect('/');
});
