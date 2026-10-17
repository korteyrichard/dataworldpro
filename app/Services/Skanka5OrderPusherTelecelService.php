<?php

namespace App\Services;

use App\Models\Order;

class Skanka5OrderPusherTelecelService
{
    public function pushOrderToApi(Order $order): bool
    {
        return (new TskconnectOrderService())->submitOrder($order, 'TELECEL');
    }

    public function checkOrderStatus($referenceId): ?array
    {
        return (new TskconnectOrderService())->fetchOrderStatus((string) $referenceId);
    }
}
