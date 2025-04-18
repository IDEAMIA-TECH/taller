<?php
// Configuración de pasarelas de pago
return [
    'stripe' => [
        'enabled' => true,
        'test_mode' => true,
        'public_key' => getenv('STRIPE_PUBLIC_KEY'),
        'secret_key' => getenv('STRIPE_SECRET_KEY'),
        'webhook_secret' => getenv('STRIPE_WEBHOOK_SECRET'),
        'currency' => 'MXN',
        'max_retries' => 3,
        'retry_interval' => 24 * 60 * 60, // 24 horas en segundos
    ],
    'paypal' => [
        'enabled' => true,
        'test_mode' => true,
        'client_id' => getenv('PAYPAL_CLIENT_ID'),
        'client_secret' => getenv('PAYPAL_CLIENT_SECRET'),
        'webhook_id' => getenv('PAYPAL_WEBHOOK_ID'),
        'currency' => 'MXN',
        'max_retries' => 3,
        'retry_interval' => 24 * 60 * 60,
    ],
    'oxxo' => [
        'enabled' => true,
        'expiration_days' => 3,
        'max_retries' => 2,
        'retry_interval' => 12 * 60 * 60, // 12 horas en segundos
    ],
    'bank_transfer' => [
        'enabled' => true,
        'account_number' => getenv('BANK_ACCOUNT_NUMBER'),
        'bank_name' => getenv('BANK_NAME'),
        'clabe' => getenv('BANK_CLABE'),
        'expiration_days' => 3,
        'max_retries' => 2,
        'retry_interval' => 12 * 60 * 60,
    ]
]; 