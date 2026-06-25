<?php

return [
    'store_id' => env('SSLCOMMERZ_STORE_ID'),
    'store_password' => env('SSLCOMMERZ_STORE_PASSWORD'),
    'sandbox' => env('SSLCOMMERZ_MODE', 'sandbox') === 'sandbox',
    'api_domain' => env('SSLCOMMERZ_MODE', 'sandbox') === 'sandbox' 
        ? 'https://sandbox.sslcommerz.com' 
        : 'https://securepay.sslcommerz.com',
];
