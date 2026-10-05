import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import { ApiProblem, setCartId } from '@/api/client';
import { endpoints } from '@/api/endpoints';
import type { Leg, Money, ProblemDetails, SeatInfo } from '@/api/types';
import { readStorage, writeStorage } from '@/lib/storage';

const CART_KEY = 'seatlock.cart';

export interface SelectedSeat {
    id: string;
    number: string;
    type: SeatInfo['type'];
    price: Money;
}

function newCartId(): string {
    return `cart-${crypto.randomUUID()}`;
}

/**
 * Carrinho: assentos travados no servidor para este navegador. O ID do
 * carrinho vai no header X-Cart-Id e identifica o dono dos locks.
 */
export const useCartStore = defineStore('cart', () => {
    const cartId = ref<string>(readStorage(CART_KEY) ?? newCartId());
    writeStorage(CART_KEY, cartId.value);
    setCartId(cartId.value);

    const tripId = ref<string | null>(null);
    const leg = ref<Leg | null>(null);
    const seats = ref<SelectedSeat[]>([]);
    const expiresAt = ref<string | null>(null);
    const conflict = ref<ProblemDetails | null>(null);
    const pendingSeatId = ref<string | null>(null);

    const total = computed<Money | null>(() => {
        const first = seats.value[0];
        if (!first) return null;
        const cents = seats.value.reduce((sum, seat) => sum + seat.price.cents, 0);
        return { cents, currency: first.price.currency, formatted: formatCents(cents) };
    });

    const isSelected = (seatId: string): boolean => seats.value.some((seat) => seat.id === seatId);

    async function setContext(newTripId: string, newLeg: Leg): Promise<void> {
        const changed = tripId.value !== newTripId || leg.value?.origin !== newLeg.origin || leg.value?.destination !== newLeg.destination;

        if (changed) {
            await releaseAll();
            tripId.value = newTripId;
            leg.value = { ...newLeg };
        }
    }

    async function toggleSeat(seat: SeatInfo): Promise<void> {
        if (!tripId.value || !leg.value || pendingSeatId.value) return;

        pendingSeatId.value = seat.id;
        conflict.value = null;

        try {
            if (isSelected(seat.id)) {
                await endpoints.releaseSeats(tripId.value, leg.value.origin, leg.value.destination, [seat.id]);
                seats.value = seats.value.filter((s) => s.id !== seat.id);
                if (seats.value.length === 0) expiresAt.value = null;
                return;
            }

            const { data } = await endpoints.lockSeats(tripId.value, leg.value.origin, leg.value.destination, [seat.id]);
            seats.value = [...seats.value, { id: seat.id, number: seat.number, type: seat.type, price: seat.price }];

            // O prazo do carrinho é o do lock que expira primeiro.
            if (data.expires_at && (!expiresAt.value || Date.parse(data.expires_at) < Date.parse(expiresAt.value))) {
                expiresAt.value = data.expires_at;
            }
        } catch (error) {
            if (error instanceof ApiProblem && error.status === 409) {
                conflict.value = error.problem;
                return;
            }
            throw error;
        } finally {
            pendingSeatId.value = null;
        }
    }

    async function releaseAll(): Promise<void> {
        if (tripId.value && leg.value && seats.value.length > 0) {
            const ids = seats.value.map((s) => s.id);
            await endpoints.releaseSeats(tripId.value, leg.value.origin, leg.value.destination, ids).catch(() => undefined);
        }
        clear();
    }

    /** Após criar/pagar o pedido, os locks passam a ser do pedido. */
    function clear(): void {
        seats.value = [];
        expiresAt.value = null;
        conflict.value = null;
    }

    function dismissConflict(): void {
        conflict.value = null;
    }

    return { cartId, tripId, leg, seats, expiresAt, conflict, pendingSeatId, total, isSelected, setContext, toggleSeat, releaseAll, clear, dismissConflict };
});

export function formatCents(cents: number): string {
    return (cents / 100).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}
