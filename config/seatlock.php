<?php

declare(strict_types=1);

return [

    'currency' => env('SEATLOCK_CURRENCY', 'BRL'),

    'locks' => [
        // TTL do lock temporário (e do pedido pendente), em segundos.
        'ttl' => (int) env('SEATLOCK_LOCK_TTL', 600),

        // Quantas vezes o cliente pode renovar o lock de um pedido.
        'max_renewals' => (int) env('SEATLOCK_LOCK_MAX_RENEWALS', 1),

        // Se o Redis cair, deixamos a reserva seguir: o UNIQUE de seat_segments
        // continua impedindo venda dupla na confirmação.
        'fail_open' => (bool) env('SEATLOCK_LOCK_FAIL_OPEN', true),

        'redis_connection' => env('SEATLOCK_LOCK_REDIS_CONNECTION', 'locks'),

        // Máximo de assentos por pedido.
        'max_seats_per_order' => (int) env('SEATLOCK_MAX_SEATS_PER_ORDER', 6),
    ],

    // Requisições por minuto. Os endpoints de lock têm limite por usuário e por IP.
    'rate_limits' => [
        'api' => (int) env('RATE_LIMIT_API', 120),
        'locks_per_user' => (int) env('RATE_LIMIT_LOCKS_PER_USER', 30),
        'locks_per_ip' => (int) env('RATE_LIMIT_LOCKS_PER_IP', 60),
        'orders' => (int) env('RATE_LIMIT_ORDERS', 20),
    ],

    'orders' => [
        // Folga antes de a reconciliação considerar um pedido pendente vencido.
        'reconciliation_grace_seconds' => (int) env('SEATLOCK_RECONCILIATION_GRACE', 30),
    ],

    'tickets' => [
        // Vazio no .env cai para a APP_KEY.
        'signing_key' => env('TICKET_SIGNING_KEY') ?: env('APP_KEY'),
    ],

    // Política de reembolso por antecedência (horas antes da partida => % em pontos-base).
    // Ordenada da maior para a menor antecedência; abaixo da última faixa não há cancelamento.
    'refund_policy' => [
        ['min_hours_before' => 72, 'refund_basis_points' => 10_000],
        ['min_hours_before' => 24, 'refund_basis_points' => 9_500],
        ['min_hours_before' => 3, 'refund_basis_points' => 8_000],
    ],

    // Remarcação permitida até N horas antes da partida original.
    'rebooking_min_hours_before' => (int) env('SEATLOCK_REBOOKING_MIN_HOURS', 3),

    'pix' => [
        'key' => env('PIX_KEY', 'pix@seatlock.test'),
        'merchant_name' => env('PIX_MERCHANT_NAME', 'SEATLOCK'),
        'merchant_city' => env('PIX_MERCHANT_CITY', 'SAO PAULO'),
    ],

];
