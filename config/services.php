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

    'gsb' => [
        'enabled' => filter_var(env('GSB_ENABLED', false), FILTER_VALIDATE_BOOL),
        'environment' => env('GSB_ENVIRONMENT', 'stg'),
        'base_url' => env('GSB_BASE_URL', 'https://api-gateway.stg.gsb.gov.jo:9443'),
        'force_ip' => env('GSB_FORCE_IP', ''),
        'timeout' => (int) env('GSB_TIMEOUT', 25),
        'cache_minutes' => (int) env('GSB_CACHE_MINUTES', 10),
        'client_id' => env('GSB_CLIENT_ID'),
        'client_secret' => env('GSB_CLIENT_SECRET'),
        'basic_user' => env('GSB_BASIC_USER', ''),
        'basic_pass' => env('GSB_BASIC_PASS', ''),
        'bearer' => env('GSB_BEARER', ''),
        'send_modee_headers' => filter_var(env('GSB_SEND_MODEE_HEADERS', true), FILTER_VALIDATE_BOOL),
        'send_ibm_headers' => filter_var(env('GSB_SEND_IBM_HEADERS', false), FILTER_VALIDATE_BOOL),
        'services' => [
            'mohe_undergraduate_students' => [
                'enabled' => filter_var(env(
                    'GSB_MOHE_UNDERGRADUATE_ENABLED',
                    env('GSB_MOHE_SANAD_ENABLED', env('GSB_ENABLED', false)),
                ), FILTER_VALIDATE_BOOL),
                'base_url' => env(
                    'GSB_MOHE_UNDERGRADUATE_BASE_URL',
                    env('GSB_MOHE_SANAD_BASE_URL', env('GSB_BASE_URL', 'https://api-gateway.stg.gsb.gov.jo:9443')),
                ),
                'path' => env('GSB_MOHE_UNDERGRADUATE_PATH', '/porg-g2g/g2g/api/LastSemester'),
                'method' => env('GSB_MOHE_UNDERGRADUATE_METHOD', 'POST'),
            ],
            'cspd_personal_info_masked' => [
                'enabled' => filter_var(env('GSB_CSPD_MASKED_ENABLED', env('GSB_ENABLED', false)), FILTER_VALIDATE_BOOL),
                'base_url' => env('GSB_CSPD_MASKED_BASE_URL', env('GSB_BASE_URL', 'https://api-gateway.stg.gsb.gov.jo:9443')),
                'path' => env('GSB_CSPD_MASKED_PATH', '/porg-g2g/g2g/masked/api/person-info'),
                'method' => env('GSB_CSPD_MASKED_METHOD', 'GET'),
                'psd_path' => env('GSB_CSPD_MASKED_PSD_PATH', '/porg-g2g/g2g/masked/api/psd'),
            ],
            'cspd_personal_info_token' => [
                'enabled' => filter_var(env('GSB_CSPD_TOKEN_ENABLED', env('GSB_ENABLED', false)), FILTER_VALIDATE_BOOL),
                'base_url' => env('GSB_CSPD_TOKEN_BASE_URL', env('GSB_BASE_URL', 'https://api-gateway.stg.gsb.gov.jo:9443')),
                'path' => env('GSB_CSPD_TOKEN_PATH', '/porg-g2g/g2g/token/person-info'),
                'method' => env('GSB_CSPD_TOKEN_METHOD', 'GET'),
                'bearer' => env('GSB_CSPD_TOKEN_BEARER', env('GSB_BEARER', '')),
            ],
            'psd_basic_info_token' => [
                'enabled' => filter_var(env('GSB_PSD_BASIC_INFO_ENABLED', env('GSB_ENABLED', false)), FILTER_VALIDATE_BOOL),
                'base_url' => env('GSB_PSD_BASIC_INFO_BASE_URL', env('GSB_BASE_URL', 'https://api-gateway.stg.gsb.gov.jo:9443')),
                'path' => env('GSB_PSD_BASIC_INFO_PATH', '/porg-g2g/g2g/token/psd'),
                'method' => env('GSB_PSD_BASIC_INFO_METHOD', 'POST'),
                'bearer' => env('GSB_PSD_BASIC_INFO_BEARER', env('GSB_BEARER', '')),
            ],
            'ccd_company' => [
                'enabled' => filter_var(env('GSB_CCD_COMPANY_ENABLED', env('GSB_ENABLED', false)), FILTER_VALIDATE_BOOL),
                'base_url' => env('GSB_CCD_COMPANY_BASE_URL', env('GSB_BASE_URL', 'https://api-gateway.stg.gsb.gov.jo:9443')),
                'path' => env('GSB_CCD_COMPANY_PATH', '/porg-g2g/g2g/api/companies/CompanybyNo/{nationalNo}'),
                'method' => env('GSB_CCD_COMPANY_METHOD', 'GET'),
            ],
            'mit_services' => [
                'enabled' => filter_var(env('GSB_MIT_SERVICES_ENABLED', env('GSB_ENABLED', false)), FILTER_VALIDATE_BOOL),
                'base_url' => env('GSB_MIT_SERVICES_BASE_URL', env('GSB_BASE_URL', 'https://api-gateway.stg.gsb.gov.jo:9443')),
                'path' => env('GSB_MIT_SERVICES_PATH', '/porg-g2g/g2g/api/Registry/getIndividualRegistry'),
                'method' => env('GSB_MIT_SERVICES_METHOD', 'POST'),
            ],
            'signflow_v2' => [
                'enabled' => filter_var(env('GSB_SIGNFLOW_V2_ENABLED', env('GSB_ENABLED', false)), FILTER_VALIDATE_BOOL),
                'base_url' => env('GSB_SIGNFLOW_V2_BASE_URL', env('GSB_BASE_URL', 'https://api-gateway.stg.gsb.gov.jo:9443')),
                'path' => env('GSB_SIGNFLOW_V2_PATH', '/porg-g2g/g2g/signflow/v2'),
                'method' => env('GSB_SIGNFLOW_V2_METHOD', 'POST'),
                'send_modee_headers' => false,
                'send_ibm_headers' => true,
                'accept' => 'text/plain',
                'client_id' => env('SANAD_CLIENT_ID', env('GSB_CLIENT_ID')),
                'client_secret' => env('SANAD_CLIENT_SECRET', env('GSB_CLIENT_SECRET')),
            ],
            'signflow_v2_open' => [
                'enabled' => filter_var(env('GSB_SIGNFLOW_V2_OPEN_ENABLED', env('GSB_ENABLED', false)), FILTER_VALIDATE_BOOL),
                'base_url' => env('GSB_SIGNFLOW_V2_OPEN_BASE_URL', env('GSB_BASE_URL', 'https://api-gateway.stg.gsb.gov.jo:9443')),
                'path' => env('GSB_SIGNFLOW_V2_OPEN_PATH', '/porg-g2g/g2g/signflow/v2'),
                'method' => env('GSB_SIGNFLOW_V2_OPEN_METHOD', 'POST'),
                'send_modee_headers' => false,
                'send_ibm_headers' => true,
                'accept' => 'text/plain',
                'client_id' => env('SANAD_CLIENT_ID', env('GSB_CLIENT_ID')),
                'client_secret' => env('SANAD_CLIENT_SECRET', env('GSB_CLIENT_SECRET')),
            ],
        ],
    ],

    'sanad' => [
        'authorization_url' => env('SANAD_AUTHORIZATION_URL', ''),
        'client_id' => env('SANAD_CLIENT_ID', env('GSB_CLIENT_ID')),
        'client_secret' => env('SANAD_CLIENT_SECRET', env('GSB_CLIENT_SECRET')),
        'redirect_uri' => env('SANAD_REDIRECT_URI', ''),
        'scope' => env('SANAD_SCOPE', 'openid'),
    ],

    'otp_debug_fallback' => filter_var(env('OTP_DEBUG_FALLBACK', false), FILTER_VALIDATE_BOOL),

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
