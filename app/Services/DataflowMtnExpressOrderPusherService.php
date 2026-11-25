<?php

namespace App\Services;

class DataflowMtnExpressOrderPusherService extends DataflowOrderPusherService
{
    protected bool $expressOnly = true;
}
