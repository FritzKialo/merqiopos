<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Daraja API Credentials
    |--------------------------------------------------------------------------
    | Obtain from https://developer.safaricom.co.ke
    | For sandbox use the sandbox app credentials.
    */

    'consumer_key'    => env('MPESA_CONSUMER_KEY', ''),
    'consumer_secret' => env('MPESA_CONSUMER_SECRET', ''),

    /*
    |--------------------------------------------------------------------------
    | Business Short Code (Pay Bill / Till Number)
    |--------------------------------------------------------------------------
    | Sandbox default: 174379
    */

    'shortcode' => env('MPESA_SHORTCODE', '174379'),

    /*
    |--------------------------------------------------------------------------
    | Lipa Na M-Pesa Online Passkey
    |--------------------------------------------------------------------------
    | Provided in the Daraja developer portal under your app.
    | Sandbox default passkey is provided by Safaricom.
    */

    'passkey' => env('MPESA_PASSKEY', ''),

    /*
    |--------------------------------------------------------------------------
    | Environment
    |--------------------------------------------------------------------------
    | 'sandbox' or 'production'
    */

    'environment' => env('MPESA_ENVIRONMENT', 'sandbox'),

    /*
    |--------------------------------------------------------------------------
    | Base URL (auto-derived from environment)
    |--------------------------------------------------------------------------
    */

    'base_url' => env('MPESA_ENVIRONMENT', 'sandbox') === 'production'
        ? 'https://api.safaricom.co.ke'
        : 'https://sandbox.safaricom.co.ke',

    /*
    |--------------------------------------------------------------------------
    | Callback URL
    |--------------------------------------------------------------------------
    | Must be a publicly accessible HTTPS URL.
    | In development, use ngrok: ngrok http 8000
    | Then set: MPESA_CALLBACK_URL=https://xxxx.ngrok.io/api/mpesa/callback
    */

    'callback_url' => env('MPESA_CALLBACK_URL', ''),

    /*
    |--------------------------------------------------------------------------
    | Till Number (For Buy Goods)
    |--------------------------------------------------------------------------
    */
    'till_number' => env('MPESA_TILL_NUMBER', ''),

];
