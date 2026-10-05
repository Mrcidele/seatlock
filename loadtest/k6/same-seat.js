// Teste de carga: N usuários tentam comprar o MESMO assento ao mesmo tempo.
//
// Preparação:
//   php artisan seatlock:loadtest-prepare --users=500
// Execução:
//   k6 run -e BASE_URL=http://localhost:8000 loadtest/k6/same-seat.js
// Verificação:
//   php artisan seatlock:verify --trip=<trip_id>
//
// Critério de sucesso: exatamente 1 compra confirmada e zero vendas duplicadas.

import http from 'k6/http';
import { check } from 'k6';
import { Counter } from 'k6/metrics';
import { SharedArray } from 'k6/data';
import exec from 'k6/execution';

const fixtures = JSON.parse(open(__ENV.FIXTURES || './fixtures.json'));
const tokens = new SharedArray('tokens', () => fixtures.tokens);
const BASE_URL = __ENV.BASE_URL || 'http://localhost:8000';

const purchases = new Counter('purchases_confirmed');
const lockConflicts = new Counter('lock_conflicts');
const orderRejected = new Counter('orders_rejected');
const paymentsNotConfirmed = new Counter('payments_not_confirmed');
const unexpected = new Counter('unexpected_responses');

export const options = {
    scenarios: {
        same_seat: {
            executor: 'per-vu-iterations',
            vus: tokens.length,
            iterations: 1,
            maxDuration: '2m',
        },
    },
    thresholds: {
        purchases_confirmed: ['count==1'],
        unexpected_responses: ['count==0'],
        // Ajuste ao hardware; o critério principal é de corretude (acima).
        'http_req_duration{name:lock}': [`p(95)<${__ENV.LOCK_P95_MS || 1500}`],
    },
};

function headers(token, cart, extra = {}) {
    return {
        headers: {
            Authorization: `Bearer ${token}`,
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Cart-Id': cart,
            ...extra,
        },
    };
}

export default function () {
    const index = exec.vu.idInTest - 1;
    const token = tokens[index];
    const cart = `k6-cart-${index}-${Date.now()}`;
    const leg = { origin: fixtures.origin, destination: fixtures.destination };

    const lock = http.post(
        `${BASE_URL}/api/v1/trips/${fixtures.trip_id}/locks`,
        JSON.stringify({ ...leg, seat_ids: [fixtures.seat_id] }),
        { ...headers(token, cart), tags: { name: 'lock' } },
    );

    if (lock.status === 409 || lock.status === 429) {
        lockConflicts.add(1);
        return;
    }
    if (!check(lock, { 'lock 201': (r) => r.status === 201 })) {
        unexpected.add(1);
        return;
    }

    const order = http.post(
        `${BASE_URL}/api/v1/orders`,
        JSON.stringify({
            trip_id: fixtures.trip_id,
            ...leg,
            passengers: [{ seat_id: fixtures.seat_id, name: `Passageiro ${index}`, document: '12345678909' }],
        }),
        { ...headers(token, cart, { 'Idempotency-Key': `${cart}-order` }), tags: { name: 'order' } },
    );

    if (order.status === 409) {
        orderRejected.add(1);
        return;
    }
    if (!check(order, { 'order 201': (r) => r.status === 201 })) {
        unexpected.add(1);
        return;
    }

    const orderId = order.json('data.id');
    const payment = http.post(
        `${BASE_URL}/api/v1/orders/${orderId}/payments`,
        JSON.stringify({ method: 'card', card_token: 'tok_approved' }),
        { ...headers(token, cart, { 'Idempotency-Key': `${cart}-pay` }), tags: { name: 'payment' } },
    );

    if (payment.status === 201 && payment.json('data.status') === 'paid') {
        purchases.add(1);
    } else if (payment.status === 201 || payment.status === 409) {
        paymentsNotConfirmed.add(1);
    } else {
        unexpected.add(1);
    }
}
