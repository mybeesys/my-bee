<?php

namespace App\Observers;

use App\Models\Order;
use App\Services\OrderNotificationService;
use Illuminate\Support\Facades\DB;

class OrderObserver
{
    public function created(Order $order): void
    {
        DB::afterCommit(function () use ($order) {
            OrderNotificationService::notifyNewOrder($order->fresh(['tenant', 'customer']));
        });
    }
}
