<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;

/** Select one enabled Bundle Portal route for MTN Express orders. */
class BundlePortalMtnExpressPusherDispatcher
{
    public static function isExpressOrder(Order $order): bool
    {
        return $order->products()->whereRaw('LOWER(TRIM(name)) = ?', ['mtn express'])->exists();
    }

    public function push(Order $order): bool
    {
        $routes = [
            ['setting' => 'bundleportal_mtn_express_api_enabled', 'network' => 'mtn', 'class' => BundlePortalMtnExpressOrderPusherService::class],
            ['setting' => 'bundleportal_mtn_express_2_api_enabled', 'network' => 'mtn_2', 'class' => BundlePortalMtnExpress2OrderPusherService::class],
            ['setting' => 'bundleportal_mtn_express_3_api_enabled', 'network' => 'mtn_3', 'class' => BundlePortalMtnExpress3OrderPusherService::class],
        ];

        foreach ($routes as $route) {
            if (!(bool) Setting::get($route['setting'], 0)) {
                continue;
            }

            Log::info('Bundle Portal MTN Express route selected', [
                'order_id' => $order->id,
                'setting' => $route['setting'],
                'network' => $route['network'],
            ]);

            return (new $route['class']())->pushOrderToApi($order);
        }

        $order->update(['order_pusher_status' => 'disabled']);
        return false;
    }
}
