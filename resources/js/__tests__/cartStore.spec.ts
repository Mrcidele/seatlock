import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { useCartStore } from '@/stores/cart';
import { deckFrom } from './fixtures';

const seat = deckFrom(['CC'])[`cells`][0]![0]!.seat!;

function respond(status: number, body: unknown) {
    return Promise.resolve(new Response(status === 204 ? null : JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } }));
}

describe('cart store', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.restoreAllMocks();
    });

    it('locks the seat and keeps the server expiry', async () => {
        const fetch = vi.spyOn(globalThis, 'fetch').mockImplementation(() =>
            respond(201, { data: { expires_at: '2026-10-05T12:10:00Z', server_time: '2026-10-05T12:00:00Z', seat_ids: [seat.id] } }),
        );
        const cart = useCartStore();
        await cart.setContext('trip-1', { origin: 0, destination: 2 });

        await cart.toggleSeat(seat);

        expect(cart.seats.map((s) => s.number)).toEqual(['01']);
        expect(cart.expiresAt).toBe('2026-10-05T12:10:00Z');
        const [, init] = fetch.mock.calls[0]!;
        expect((init?.headers as Record<string, string>)['X-Cart-Id']).toMatch(/^cart-/);
    });

    it('exposes the conflict with alternatives when the seat was just taken', async () => {
        vi.spyOn(globalThis, 'fetch').mockImplementation(() =>
            respond(409, {
                type: 'http://localhost/problems/seat-unavailable',
                title: 'Assento indisponível',
                status: 409,
                conflicting_seat_ids: [seat.id],
                alternatives: [{ id: 'seat-9', number: '09', type: 'conventional', deck: 1 }],
            }),
        );
        const cart = useCartStore();
        await cart.setContext('trip-1', { origin: 0, destination: 2 });

        await cart.toggleSeat(seat);

        expect(cart.seats).toHaveLength(0);
        expect(cart.conflict?.alternatives?.[0]?.number).toBe('09');
    });
});
