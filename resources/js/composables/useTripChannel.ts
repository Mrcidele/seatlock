import { useQueryClient } from '@tanstack/vue-query';
import { onScopeDispose, toValue, watch, type MaybeRefOrGetter } from 'vue';
import type { SeatMap } from '@/api/types';
import { echo } from '@/lib/echo';
import { applySeatEvent, type SeatEvent, type SeatEventName } from '@/lib/seatMapPatch';
import { useCartStore } from '@/stores/cart';
import { seatMapKey } from './useSeatMap';

const EVENTS: SeatEventName[] = ['SeatLocked', 'SeatReleased', 'SeatSold'];

/**
 * Atualiza o mapa ao vivo quando outro usuário trava, libera ou compra um
 * assento. Aplica o evento no cache na hora e, em seguida, reconcilia com o
 * servidor (um assento liberado pode continuar travado por outro trecho).
 */
export function useTripChannel(tripId: MaybeRefOrGetter<string>, origin: MaybeRefOrGetter<number>, destination: MaybeRefOrGetter<number>): void {
    const queryClient = useQueryClient();
    const cart = useCartStore();
    let reconcileTimer: ReturnType<typeof setTimeout> | null = null;
    let current: string | null = null;

    const key = () => seatMapKey(toValue(tripId), toValue(origin), toValue(destination));

    function onEvent(name: SeatEventName, event: SeatEvent): void {
        const map = queryClient.getQueryData<SeatMap>(key());
        if (!map) return;

        const patched = applySeatEvent(map, name, event, cart.seats.map((s) => s.id));
        if (!patched) return;

        queryClient.setQueryData(key(), patched);

        if (reconcileTimer) clearTimeout(reconcileTimer);
        reconcileTimer = setTimeout(() => void queryClient.invalidateQueries({ queryKey: key() }), 750);
    }

    function leave(): void {
        if (current) echo()?.leave(current);
        current = null;
    }

    watch(
        () => toValue(tripId),
        (id) => {
            const connection = echo();
            if (!connection) return;

            leave();
            current = `trip.${id}`;
            const channel = connection.channel(current);
            for (const name of EVENTS) {
                channel.listen(`.${name}`, (event: SeatEvent) => onEvent(name, event));
            }
        },
        { immediate: true },
    );

    onScopeDispose(() => {
        leave();
        if (reconcileTimer) clearTimeout(reconcileTimer);
    });
}
