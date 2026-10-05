<script setup lang="ts">
import { useQuery } from '@tanstack/vue-query';
import { endpoints } from '@/api/endpoints';

const { data } = useQuery({ queryKey: ['orders'], queryFn: async () => (await endpoints.orders()).data });
</script>

<template>
    <section class="mx-auto max-w-2xl space-y-3">
        <h1 class="text-xl font-semibold">Meus pedidos</h1>
        <p v-if="data?.length === 0" class="text-sm text-slate-500">Nenhum pedido ainda.</p>
        <RouterLink
            v-for="order in data"
            :key="order.id"
            :to="{ name: 'order', params: { id: order.id } }"
            class="flex justify-between rounded-2xl bg-white p-4 text-sm shadow-sm hover:ring-2 hover:ring-blue-200"
        >
            <span>{{ order.reservations.length }} assento(s) · {{ order.status }}</span>
            <span class="font-semibold">{{ order.total.formatted }}</span>
        </RouterLink>
    </section>
</template>
