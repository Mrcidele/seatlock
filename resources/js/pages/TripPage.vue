<script setup lang="ts">
import { useQuery } from '@tanstack/vue-query';
import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { endpoints } from '@/api/endpoints';
import type { SeatAlternative, SeatInfo } from '@/api/types';
import CountdownTimer from '@/components/CountdownTimer.vue';
import SeatMapView from '@/components/SeatMapView.vue';
import SeatTakenDialog from '@/components/SeatTakenDialog.vue';
import { useSeatMap } from '@/composables/useSeatMap';
import { useTripChannel } from '@/composables/useTripChannel';
import { useAuthStore } from '@/stores/auth';
import { useCartStore } from '@/stores/cart';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const cart = useCartStore();

const tripId = computed(() => String(route.params.id));
const origin = ref(Number(route.query.origin ?? 0));
const destination = ref(Number(route.query.destination ?? 1));
const notice = ref<string | null>(null);

const { data: trip } = useQuery({
    queryKey: computed(() => ['trip', tripId.value]),
    queryFn: async () => (await endpoints.trip(tripId.value)).data,
});

const { data: seatMap, isLoading, refetch } = useSeatMap(tripId, origin, destination);
useTripChannel(tripId, origin, destination);

watch(trip, (value) => {
    if (value && destination.value >= value.stops.length) destination.value = value.stops.length - 1;
});

watch([tripId, origin, destination], ([id, o, d]) => {
    void cart.setContext(id, { origin: o, destination: d });
    void router.replace({ query: { origin: o, destination: d } });
}, { immediate: true });

const selectedIds = computed(() => cart.seats.map((s) => s.id));

function findSeat(id: string): SeatInfo | null {
    for (const deck of seatMap.value?.decks ?? []) {
        for (const row of deck.cells) {
            for (const cell of row) if (cell.seat?.id === id) return cell.seat;
        }
    }
    return null;
}

async function toggle(seat: SeatInfo): Promise<void> {
    if (!auth.isAuthenticated) {
        await router.push({ name: 'login', query: { redirect: route.fullPath } });
        return;
    }
    await cart.toggleSeat(seat);
    await refetch();
}

async function chooseAlternative(alternative: SeatAlternative): Promise<void> {
    cart.dismissConflict();
    await refetch();
    const seat = findSeat(alternative.id);
    if (seat) await toggle(seat);
}

function onCartExpired(): void {
    cart.clear();
    notice.value = 'O tempo de reserva acabou e os assentos foram liberados. Selecione novamente.';
    void refetch();
}
</script>

<template>
    <div class="grid gap-6 lg:grid-cols-[1fr_20rem]">
        <section class="space-y-4">
            <div v-if="trip" class="rounded-2xl bg-white p-4 shadow-sm">
                <h1 class="text-xl font-semibold">{{ trip.route.name }}</h1>
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    <label class="text-sm">Embarque
                        <select v-model.number="origin" class="mt-1 w-full rounded-lg border px-3 py-2">
                            <option v-for="stop in trip.stops.slice(0, -1)" :key="stop.index" :value="stop.index">{{ stop.city }} — {{ stop.name }}</option>
                        </select>
                    </label>
                    <label class="text-sm">Desembarque
                        <select v-model.number="destination" class="mt-1 w-full rounded-lg border px-3 py-2">
                            <option v-for="stop in trip.stops.filter((s) => s.index > origin)" :key="stop.index" :value="stop.index">{{ stop.city }} — {{ stop.name }}</option>
                        </select>
                    </label>
                </div>
            </div>

            <p v-if="notice" class="rounded-lg bg-amber-50 p-3 text-sm text-amber-800" role="alert">{{ notice }}</p>
            <p v-if="isLoading" role="status" class="text-sm text-slate-500">Carregando mapa…</p>
            <SeatMapView v-if="seatMap" :seat-map="seatMap" :selected-ids="selectedIds" :pending-id="cart.pendingSeatId" @toggle="toggle" />
        </section>

        <aside class="h-fit space-y-4 rounded-2xl bg-white p-4 shadow-sm lg:sticky lg:top-4" aria-label="Carrinho">
            <h2 class="font-semibold">Seus assentos</h2>
            <CountdownTimer :expires-at="cart.expiresAt" @expired="onCartExpired" />
            <p v-if="cart.seats.length === 0" class="text-sm text-slate-500">Selecione um assento no mapa.</p>
            <ul v-else class="divide-y text-sm">
                <li v-for="seat in cart.seats" :key="seat.id" class="flex justify-between py-2">
                    <span>Assento {{ seat.number }}</span><span>{{ seat.price.formatted }}</span>
                </li>
            </ul>
            <p v-if="cart.total" class="flex justify-between border-t pt-2 font-semibold"><span>Total</span><span>{{ cart.total.formatted }}</span></p>
            <RouterLink
                v-if="cart.seats.length > 0"
                :to="{ name: 'checkout' }"
                class="block rounded-lg bg-blue-600 px-4 py-2 text-center font-medium text-white hover:bg-blue-700"
            >Continuar</RouterLink>
        </aside>

        <SeatTakenDialog v-if="cart.conflict" :problem="cart.conflict" @choose="chooseAlternative" @close="cart.dismissConflict()" />
    </div>
</template>
