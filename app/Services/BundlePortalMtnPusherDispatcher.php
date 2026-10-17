<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;

/** Select one enabled Bundle Portal route per order to prevent duplicate paid deliveries. */
class BundlePortalMtnPusherDispatcher
{
    public function push(Order $order): bool
    {
        $routes = [
            ['setting' => 'bundleportal_mtn_api_enabled', 'network' => 'mtn', 'class' => BundlePortalMtnOrderPusherService::class],
            ['setting' => 'bundleportal_mtn_2_api_enabled', 'network' => 'mtn_2', 'class' => BundlePortalMtn2OrderPusherService::class],
            ['setting' => 'bundleportal_mtn_3_api_enabled', 'network' => 'mtn_3', 'class' => BundlePortalMtn3OrderPusherService::class],
        ];

        foreach ($routes as $route) {
            if (!(bool) Setting::get($route['setting'], 0)) {
                continue;
            }

            Log::info('Bundle Portal MTN route selected', [
                'order_id' => $order->id,
                'setting' => $route['setting'],
                'network' => $route['network'],
            ]);
            $pusher = new $route['class']();
            $pushed = $pusher->pushOrderToApi($order);
            Log::log($pushed ? 'info' : 'warning', 'Bundle Portal MTN route submission result', [
                'order_id' => $order->id,
                'setting' => $route['setting'],
                'submitted' => $pushed,
            ]);
            return $pushed;
        }

        return false;
    }
}
