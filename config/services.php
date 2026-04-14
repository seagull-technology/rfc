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
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'gov_sms' => [
        'base' => env('GOV_SMS_BASE', 'https://bulk-sms.gov.jo'),
        'username' => env('GOV_SMS_USER'),
        'password' => env('GOV_SMS_PASS'),
        'header' => env('GOV_SMS_HEADER', 'rfc'),
        'message_type_id' => (int) env('GOV_SMS_MESSAGE_TYPE_ID', 3),
    ],

    'gov_company_registry' => [
        'enabled' => filter_var(env('GOV_COMPANY_REGISTRY_ENABLED', false), FILTER_VALIDATE_BOOL),
        'host' => env('GOV_COMPANY_REGISTRY_HOST', env('GSB_HOST', '')),
        'port' => (int) env('GOV_COMPANY_REGISTRY_PORT', env('GSB_PORT', 9443)),
        'ip' => env('GOV_COMPANY_REGISTRY_FORCE_IP', env('GSB_FORCE_IP', '')),
        'path' => env('GOV_COMPANY_REGISTRY_PATH', '/porg-g2g/g2g/MoaApi/api/GetDataByNationalId'),
        'timeout' => (int) env('GOV_COMPANY_REGISTRY_TIMEOUT', 25),
        'client_id' => env('GOV_COMPANY_REGISTRY_CLIENT_ID', env('GSB_CLIENT_ID')),
        'client_secret' => env('GOV_COMPANY_REGISTRY_CLIENT_SECRET', env('GSB_CLIENT_SECRET')),
        'modee_client_id' => env('GOV_COMPANY_REGISTRY_MODEE_CLIENT_ID', env('GSB_CLIENT_ID')),
        'modee_client_secret' => env('GOV_COMPANY_REGISTRY_MODEE_CLIENT_SECRET', env('GSB_CLIENT_SECRET')),
        'send_ibm_headers' => filter_var(env('GOV_COMPANY_REGISTRY_SEND_IBM_HEADERS', true), FILTER_VALIDATE_BOOL),
        'send_modee_headers' => filter_var(env('GOV_COMPANY_REGISTRY_SEND_MODEE_HEADERS', true), FILTER_VALIDATE_BOOL),
        'basic_user' => env('GOV_COMPANY_REGISTRY_BASIC_USER', ''),
        'basic_pass' => env('GOV_COMPANY_REGISTRY_BASIC_PASS', ''),
        'bearer' => env('GOV_COMPANY_REGISTRY_BEARER', ''),
        'cache_minutes' => (int) env('GOV_COMPANY_REGISTRY_CACHE_MINUTES', 5),
    ],

];
