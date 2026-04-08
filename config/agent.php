<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Agent System Feature Flags
    |--------------------------------------------------------------------------
    |
    | These flags control the availability of agent system features.
    | Set to false to disable features during rollout or maintenance.
    |
    */

    'agent_system_enabled' => env('AGENT_SYSTEM_ENABLED', true),
    'agent_registration_enabled' => env('AGENT_REGISTRATION_ENABLED', true),
    'agent_shop_creation_enabled' => env('AGENT_SHOP_CREATION_ENABLED', true),
    'commission_system_enabled' => env('COMMISSION_SYSTEM_ENABLED', true),
    'referral_system_enabled' => env('REFERRAL_SYSTEM_ENABLED', true),
    'withdrawal_system_enabled' => env('WITHDRAWAL_SYSTEM_ENABLED', true),
    
    /*
    |--------------------------------------------------------------------------
    | Commission Settings
    |--------------------------------------------------------------------------
    */
    
    'default_referral_percentage' => env('DEFAULT_REFERRAL_PERCENTAGE', 5.00),
    'commission_availability_delay_hours' => env('COMMISSION_AVAILABILITY_DELAY_HOURS', 0),
    'minimum_withdrawal_amount' => env('MINIMUM_WITHDRAWAL_AMOUNT', 10.00),
];