<?php

namespace App\Services;

use App\Models\Order;

class Skanka5MtnExpressOrderPusherService extends Skanka5OrderPusherService
{
    public function pushOrderToApi(Order $order): bool
    {
        return (new TskconnectOrderService())->submitOrder($order, 'MTN', true);
    }
}
