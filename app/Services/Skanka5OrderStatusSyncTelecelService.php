<?php

namespace App\Services;

use App\Models\Order;

class Skanka5OrderStatusSyncTelecelService
{
    public function syncSingleOrderStatus(Order $order): void
    {
        (new TskconnectOrderStatusSync())->syncSingleOrderStatus($order, 'TELECEL');
    }

    public function syncOrderStatuses(): void
    {
        (new TskconnectOrderStatusSync())->syncOrderStatuses('TELECEL');
    }
}
