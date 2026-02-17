<?php

namespace App\Observers;

use App\Models\WithdrawalRequest;
use App\Services\TelegramNotifier;

class WithdrawalRequestObserver
{
    public function __construct(private readonly TelegramNotifier $telegramNotifier)
    {
    }

    public function created(WithdrawalRequest $withdrawalRequest): void
    {
        $this->telegramNotifier->notifyAdminWithdrawalRequested(
            $withdrawalRequest->fresh(['worker', 'user'])
        );
    }
}

