<script setup lang="ts">
import { useQuery } from '@tanstack/vue-query';
import { computed, reactive, ref } from 'vue';
import { endpoints } from '@/api/endpoints';

const form = reactive({ origin: '', destination: '', date: '' });
const params = ref({ ...form });

const { data, isFetching, isError } = useQuery({
    queryKey: computed(() => ['trips', params.value]),
    queryFn: async () => (await endpoints.searchTrips(params.value)).data,
});

const time = (iso: string) => new Date(iso).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' });
</script>

<template>
    <section class="space-y-6">
        <form class="grid gap-3 rounded-2xl bg-white p-4 shadow-sm sm:grid-cols-4" @submit.prevent="params = { ...form }">
            <label class="text-sm">Origem<input v-model="form.origin" class="mt-1 w-full rounded-lg border px-3 py-2" placeholder="São Paulo" /></label>
            <label class="text-sm">Destino<input v-model="form.destination" class="mt-1 w-full rounded-lg border px-3 py-2" placeholder="Uberaba" /></label>
            <label class="text-sm">Data<input v-model="form.date" type="date" class="mt-1 w-full rounded-lg border px-3 py-2" /></label>
            <button class="self-end rounded-lg bg-blue-600 px-4 py-2 font-medium text-white hover:bg-blue-700">Buscar</button>
        </form>

        <p v-if="isFetching" class="text-sm text-slate-500" role="status">Buscando viagens…</p>
        <p v-else-if="isError" class="text-sm text-red-600" role="alert">Não foi possível buscar as viagens.</p>
        <p v-else-if="data?.length === 0" class="text-sm text-slate-500">Nenhuma viagem encontrada.</p>

        <ul class="space-y-3">
            <li v-for="trip in data" :key="`${trip.id}-${trip.leg.origin}`">
                <RouterLink
                    :to="{ name: 'trip', params: { id: trip.id }, query: { origin: trip.leg.origin, destination: trip.leg.destination } }"
                    class="flex flex-wrap items-center justify-between gap-2 rounded-2xl bg-white p-4 shadow-sm hover:ring-2 hover:ring-blue-200"
                >
                    <div>
                        <p class="font-semibold">{{ trip.stops[trip.leg.origin]?.city }} → {{ trip.stops[trip.leg.destination]?.city }}</p>
                        <p class="text-sm text-slate-500">{{ time(trip.stops[trip.leg.origin]?.departure_at ?? trip.departure_at) }} · {{ trip.vehicle.name }} · {{ trip.route.name }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-lg font-semibold">a partir de {{ trip.from_price.formatted }}</p>
                        <p class="text-sm text-slate-500">{{ trip.available_seats }} assentos livres</p>
                    </div>
                </RouterLink>
            </li>
        </ul>
    </section>
</template>
