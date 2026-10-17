<?php

namespace App\Services;

use App\Models\Order;

class Skanka5OrderStatusSyncService
{
    public function syncSingleOrderStatus(Order $order): void
    {
        (new TskconnectOrderStatusSync())->syncSingleOrderStatus($order, 'MTN');
    }

    public function syncOrderStatuses(): void
    {
        (new TskconnectOrderStatusSync())->syncOrderStatuses('MTN');
    }
}
