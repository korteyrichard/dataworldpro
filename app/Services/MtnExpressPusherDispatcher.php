<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;

/** Sends an MTN Express order through the first enabled provider only. */
class MtnExpressPusherDispatcher
{
    public function push(Order $order): bool
    {
        $bundlePortalEnabled = (bool) Setting::get('bundleportal_mtn_express_api_enabled', 0)
            || (bool) Setting::get('bundleportal_mtn_express_2_api_enabled', 0)
            || (bool) Setting::get('bundleportal_mtn_express_3_api_enabled', 0);

        if ($bundlePortalEnabled) {
            $successful = (new BundlePortalMtnExpressPusherDispatcher())->push($order);
            $order->update(['order_pusher_status' => $successful ? 'success' : 'failed']);
            return $successful;
        }

        $providers = [
            ['setting' => 'jesco_mtn_express_order_pusher_enabled', 'class' => JescoMtnExpressOrderPusherService::class],
            ['setting' => 'skanka5_mtn_express_order_pusher_enabled', 'class' => Skanka5MtnExpressOrderPusherService::class],
            ['setting' => 'masternetgh_mtn_express_order_pusher_enabled', 'class' => MasterNetGhMtnExpressOrderPusherService::class],
            ['setting' => 'dataflow_mtn_express_order_pusher_enabled', 'class' => DataflowMtnExpressOrderPusherService::class],
        ];

        foreach ($providers as $provider) {
            if (!(bool) Setting::get($provider['setting'], 0)) {
                continue;
            }

            try {
                $providerSucceeded = (new $provider['class']())->pushOrderToApi($order);
                $successful = $providerSucceeded;
                Log::log($providerSucceeded ? 'info' : 'warning', 'MTN Express provider push finished', [
                    'order_id' => $order->id,
                    'provider' => $provider['class'],
                    'submitted' => $providerSucceeded,
                ]);
            } catch (\Throwable $exception) {
                $successful = false;
                Log::error('MTN Express provider push threw an exception', [
                    'order_id' => $order->id,
                    'provider' => $provider['class'],
                    'error' => $exception->getMessage(),
                ]);
            }

            $order->update(['order_pusher_status' => $successful ? 'success' : 'failed']);
            return $successful;
        }

        $order->update(['order_pusher_status' => 'disabled']);
        return false;
    }
}
