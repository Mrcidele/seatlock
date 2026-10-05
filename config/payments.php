<?php

declare(strict_types=1);

return [

    // Gateway usado para novos pagamentos: fake | mercadopago | stripe
    'default' => env('PAYMENT_GATEWAY', 'fake'),

    // Gateway preferido por método (sobrepõe o default quando definido).
    'methods' => [
        'pix' => env('PAYMENT_GATEWAY_PIX'),
        'card' => env('PAYMENT_GATEWAY_CARD'),
    ],

    'gateways' => [
        'fake' => [
            'webhook_secret' => env('FAKE_GATEWAY_WEBHOOK_SECRET', 'local-fake-secret'),
        ],

        'mercadopago' => [
            'base_url' => env('MERCADOPAGO_BASE_URL', 'https://api.mercadopago.com'),
            'access_token' => env('MERCADOPAGO_ACCESS_TOKEN'),
            'webhook_secret' => env('MERCADOPAGO_WEBHOOK_SECRET'),
            'notification_url' => env('MERCADOPAGO_NOTIFICATION_URL'),
        ],

        'stripe' => [
            'base_url' => env('STRIPE_BASE_URL', 'https://api.stripe.com'),
            'secret' => env('STRIPE_SECRET'),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
            'webhook_tolerance' => (int) env('STRIPE_WEBHOOK_TOLERANCE', 300),
        ],
    ],

    // Validade do QR Code Pix, em segundos (limitada ao prazo do pedido).
    'pix_ttl' => (int) env('PIX_TTL', 600),

];
