<?php

namespace App\Services;

use App\Models\Order;

class Skanka5OrderPusherService
{
    public function pushOrderToApi(Order $order): bool
    {
        return (new TskconnectOrderService())->submitOrder($order, 'MTN');
    }

    public function checkOrderStatus($referenceId): ?array
    {
        return (new TskconnectOrderService())->fetchOrderStatus((string) $referenceId);
    }
}
