<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'bulkclix' => [
        'api_key' => env('BULKCLIX_API_KEY'),
        'sender_id' => env('BULKCLIX_SENDER_ID'),
    ],

    'moolre' => [
        'api_key' => env('MOOLRE_API_KEY'),
    ],

    'jesco' => [
        'base_url' => env('JESCO_BASE_URL', 'https://jesscostore.com/api/v1'),
        'api_key' => env('JESCO_API_KEY'),
    ],

    'easydata' => [
        'base_url' => env('EASYDATA_BASE_URL', 'https://easydatagh.pro/wp-json/custom/v1'),
        'username' => env('EASYDATA_USERNAME', 'Bright Sarpong'),
        'password' => env('EASYDATA_PASSWORD', 'PWiOCj43epVmwXsvpMq2dtZF'),
    ],

    'paystack' => [
        'public_key' => env('PAYSTACK_PUBLIC_KEY'),
        'secret_key' => env('PAYSTACK_SECRET_KEY'),
    ],

    'skanka5' => [
        'base_url' => env('SKANKA5_BASE_URL', 'https://tsk05.net/v1'),
        'api_key' => env('SKANKA5_API_KEY'),
    ],

    'codecraft' => [
        'base_url' => env('CODE_CRAFTER_BASE_URL', 'https://api.codecraftnetwork.com/api'),
        'api_key' => env('CODE_CRAFTER_API_KEY'),
    ],

    'codecraft_mtn' => [
        'base_url' => env('CODE_CRAFT_MTN_BASE_URL', 'https://api.codecraftnetwork.com/api'),
        'api_key' => env('CODE_CRAFT_MTN_API_KEY', '260120115831-cblWhB-6VZp1J-Z3xYeI-joVlSi-w1GNCx'),
    ],

    'dataflow' => [
        'base_url' => env('DATAFLOW_BASE_URL', 'https://dataflowghana.com/api/v1'),
        'api_key' => env('DATAFLOW_API_KEY'),
    ],

    'masternet' => [
        'base_url' => env('MASTERNET_BASE_URL', 'https://www.masternetdatahub.com/api/v1'),
        'api_key' => env('MASTERNET_API_KEY'),
    ],

    'masternetgh' => [
        'base_url' => env('MASTERNETGH_BASE_URL', 'https://masternetgh.com/api/v1'),
        'api_key' => env('MASTERNETGH_API_KEY'),
    ],

    'bundleportal' => [
        'base_url' => env('BUNDLEPORTAL_BASE_URL', 'https://api.bundleportal.com/v2'),
        'api_key'  => env('BUNDLEPORTAL_API_KEY'),
        'webhook_secret' => env('BUNDLEPORTAL_WEBHOOK_SECRET'),
    ],

    'webhooks' => [
        'masternetdatahub' => env('WEBHOOK_SECRET_MASTERNETDATAHUB'),
        'jessco' => env('WEBHOOK_SECRET_JESSCO'),
        'skanka5' => env('WEBHOOK_SECRET_SKANKA5'),
    ],

];
