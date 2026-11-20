<?php

namespace App\Services;

class BundlePortalMtnExpressOrderPusherService extends BundlePortalMtnOrderPusherService
{
    protected function acceptsProductName(string $name): bool
    {
        return strcasecmp(trim($name), 'mtn express') === 0;
    }
}
