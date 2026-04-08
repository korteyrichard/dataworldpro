<?php

namespace App\Observers;

use App\Models\Order;
use App\Services\CommissionService;

class OrderObserver
{
    public function __construct(private CommissionService $commissionService) {}

    public function created(Order $order): void
    {
        // Create commissions when order is created
        $this->commissionService->calculateAndCreateCommissions($order);
    }

    public function updated(Order $order): void
    {
        // Handle status changes
        if ($order->wasChanged('status')) {
            if ($order->status === 'completed') {
                $this->commissionService->makeCommissionsAvailable($order);
            } elseif (in_array($order->status, ['cancelled', 'refunded'])) {
                $this->commissionService->reverseCommissions($order);
            }
        }
    }
}