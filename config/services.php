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

    'woocommerce' => [
        'base_url' => env('WC_BASE_URL'),
        'key' => env('WC_KEY'),
        'secret' => env('WC_SECRET'),
        'locale' => 'nl',
        'connect_timeout' => (int) env('WC_CONNECT_TIMEOUT', 10),
        'timeout' => (int) env('WC_TIMEOUT', 60),
        'discount_groups_endpoint' => env('WC_DISCOUNT_GROUPS_ENDPOINT', '/wp-json/wp/v2/discount_group'),
    ],

    'zeker_gemak_dhl' => [
        'base_url' => env('DHL_API_BASE_URL', 'https://api-gw.dhlparcel.nl/'),
        'user_id' => env('DHL_USER_ID'),
        'key' => env('DHL_API_KEY'),
        'account_id' => env('DHL_ACCOUNT_ID'),
        'product' => env('DHL_PRODUCT', 'DFY-B2C'),
        'parcel_type' => env('DHL_PARCEL_TYPE', 'SMALL'),
        'connect_timeout' => (int) env('DHL_CONNECT_TIMEOUT', 2),
        'timeout' => (int) env('DHL_TIMEOUT', 3),
        'sender' => [
            'company' => env('DHL_SENDER_COMPANY', 'Deurbeslaggigant'),
            'first_name' => env('DHL_SENDER_FIRST_NAME', 'Deurbeslaggigant'),
            'last_name' => env('DHL_SENDER_LAST_NAME', ''),
            'street' => env('DHL_SENDER_STREET', 'Oenerweg'),
            'house_number' => env('DHL_SENDER_HOUSE_NUMBER', '30'),
            'house_number_addition' => env('DHL_SENDER_HOUSE_NUMBER_ADDITION'),
            'postal_code' => env('DHL_SENDER_POSTAL_CODE', '8181RJ'),
            'city' => env('DHL_SENDER_CITY', 'Heerde'),
            'country_code' => env('DHL_SENDER_COUNTRY_CODE', 'NL'),
            'email' => env('DHL_SENDER_EMAIL', 'administratie@deurbeslaggigant.nl'),
            'phone' => env('DHL_SENDER_PHONE'),
            'vat_number' => env('DHL_SENDER_VAT_NUMBER'),
            'eori_number' => env('DHL_SENDER_EORI_NUMBER'),
        ],
    ],

    'zeker_gemak_moneybird' => [
        'client_id' => env('ZEKER_GEMAK_MONEYBIRD_CLIENT_ID'),
        'client_secret' => env('ZEKER_GEMAK_MONEYBIRD_CLIENT_SECRET'),
        'redirect_uri' => env('ZEKER_GEMAK_MONEYBIRD_REDIRECT_URI', env('APP_URL').'/moneybird/callback'),
        'api_url' => env('ZEKER_GEMAK_MONEYBIRD_API_URL', 'https://moneybird.com/api/v2'),
        'authorize_url' => env('ZEKER_GEMAK_MONEYBIRD_AUTHORIZE_URL', 'https://moneybird.com/oauth/authorize'),
        'token_url' => env('ZEKER_GEMAK_MONEYBIRD_TOKEN_URL', 'https://moneybird.com/oauth/token'),
        'dashboard_url' => env('ZEKER_GEMAK_MONEYBIRD_DASHBOARD_URL', 'https://moneybird.com'),
        'connect_timeout' => (int) env('ZEKER_GEMAK_MONEYBIRD_CONNECT_TIMEOUT', 5),
        'timeout' => (int) env('ZEKER_GEMAK_MONEYBIRD_TIMEOUT', 15),
        'scopes' => ['sales_invoices', 'settings'],
    ],

    'dropbox' => [
        'api_url' => env('DROPBOX_API_URL', 'https://api.dropboxapi.com'),
        'content_url' => env('DROPBOX_CONTENT_URL', 'https://content.dropboxapi.com'),
        'oauth_url' => env('DROPBOX_OAUTH_URL', 'https://api.dropbox.com/oauth2/token'),
        'authorization_token' => env('DROPBOX_AUTH_TOKEN'),
        'app_key' => env('DROPBOX_APP_KEY'),
        'app_secret' => env('DROPBOX_APP_SECRET'),
        'refresh_token' => env('DROPBOX_REFRESH_TOKEN'),
        'connect_timeout' => (int) env('DROPBOX_CONNECT_TIMEOUT', 2),
        'timeout' => (int) env('DROPBOX_TIMEOUT', 3),
    ],

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-4-6'),
        'max_tokens' => 2048,
    ],

];
