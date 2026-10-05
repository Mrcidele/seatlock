<script setup lang="ts">
import { useQuery, useQueryClient } from '@tanstack/vue-query';
import { computed, ref } from 'vue';
import { useRoute } from 'vue-router';
import { ApiProblem, download, newIdempotencyKey } from '@/api/client';
import { endpoints } from '@/api/endpoints';
import type { Order } from '@/api/types';
import CountdownTimer from '@/components/CountdownTimer.vue';
import PixQrCode from '@/components/PixQrCode.vue';

const route = useRoute();
const queryClient = useQueryClient();
const orderId = computed(() => String(route.params.id));
const error = ref<string | null>(null);
const busy = ref(false);
const cardToken = ref('tok_approved');
const isDev = import.meta.env.DEV;

const { data: order } = useQuery({
    queryKey: computed(() => ['order', orderId.value]),
    queryFn: async () => (await endpoints.order(orderId.value)).data,
    // Enquanto aguarda o Pix, consulta periodicamente (o webhook confirma no servidor).
    refetchInterval: (query) => (query.state.data?.status === 'pending' ? 3_000 : false),
});

const pendingPix = computed(() => order.value?.payments.find((p) => p.method === 'pix' && p.status === 'pending') ?? null);
const statusLabel: Record<Order['status'], string> = {
    pending: 'Aguardando pagamento',
    paid: 'Pago',
    expired: 'Expirado',
    cancelled: 'Cancelado',
    refunded: 'Reembolsado',
};

async function run(action: () => Promise<{ data: Order }>): Promise<void> {
    busy.value = true;
    error.value = null;
    try {
        const { data } = await action();
        queryClient.setQueryData(['order', orderId.value], data);
    } catch (e) {
        error.value = e instanceof ApiProblem ? (e.problem.detail ?? e.problem.title) : 'Falha de conexão.';
    } finally {
        busy.value = false;
    }
}

const payPix = () => run(() => endpoints.pay(orderId.value, { method: 'pix' }, newIdempotencyKey()));
// Em produção o token vem do SDK do provedor (Stripe.js / Mercado Pago); aqui é o gateway fake.
const payCard = () => run(() => endpoints.pay(orderId.value, { method: 'card', card_token: cardToken.value }, newIdempotencyKey()));
const renew = () => run(() => endpoints.renewOrder(orderId.value));
const cancel = () => run(() => endpoints.cancelOrder(orderId.value));
async function downloadTicket(ticketId: string, seatNumber: string): Promise<void> {
    error.value = null;
    try {
        await download(`/tickets/${ticketId}/pdf`, `bilhete-${seatNumber}.pdf`);
    } catch {
        error.value = 'Não foi possível baixar o bilhete. Tente novamente.';
    }
}

const confirmCancel = () => {
    if (window.confirm('Cancelar a passagem? O reembolso segue a política de antecedência.')) void cancel();
};

async function simulatePix(): Promise<void> {
    if (!pendingPix.value) return;
    await endpoints.simulatePayment(pendingPix.value.id, 'approved');
    await queryClient.invalidateQueries({ queryKey: ['order', orderId.value] });
}
</script>

<template>
    <section v-if="order" class="mx-auto max-w-2xl space-y-4">
        <header class="flex items-center justify-between">
            <h1 class="text-xl font-semibold">Pedido</h1>
            <span class="rounded-full px-3 py-1 text-sm font-medium" :class="order.status === 'paid' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100'" role="status">
                {{ statusLabel[order.status] }}
            </span>
        </header>

        <p v-if="error" class="rounded-lg bg-red-50 p-3 text-sm text-red-700" role="alert">{{ error }}</p>
        <p v-if="order.cancellation_reason === 'seat_unavailable'" class="rounded-lg bg-amber-50 p-3 text-sm text-amber-800" role="alert">
            O assento foi vendido para outra pessoa antes da confirmação. O valor pago será estornado automaticamente.
        </p>

        <div class="rounded-2xl bg-white p-4 shadow-sm">
            <ul class="divide-y text-sm">
                <li v-for="r in order.reservations" :key="r.id" class="flex justify-between py-2">
                    <span>Assento {{ r.seat.number }} · {{ r.passenger.name }}</span>
                    <span class="flex gap-3">
                        {{ r.price.formatted }}
                        <button
                            v-if="r.ticket_id && order.status === 'paid'"
                            type="button"
                            class="text-blue-700 underline"
                            @click="downloadTicket(r.ticket_id, r.seat.number)"
                        >Bilhete (PDF)</button>
                    </span>
                </li>
            </ul>
            <p class="mt-2 flex justify-between border-t pt-2 font-semibold"><span>Total</span><span>{{ order.total.formatted }}</span></p>
        </div>

        <template v-if="order.status === 'pending'">
            <CountdownTimer :expires-at="order.expires_at" label="Pague antes de" />
            <button v-if="order.renewals_left > 0" type="button" :disabled="busy" class="text-sm text-blue-700 underline" @click="renew">
                Preciso de mais tempo
            </button>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="space-y-3 rounded-2xl bg-white p-4 shadow-sm">
                    <h2 class="font-semibold">Pix</h2>
                    <PixQrCode v-if="pendingPix?.pix" :payload="pendingPix.pix.copy_paste" />
                    <button v-else type="button" :disabled="busy" class="w-full rounded-lg bg-emerald-600 px-4 py-2 font-medium text-white" @click="payPix">Gerar QR Code</button>
                    <button v-if="pendingPix && isDev" type="button" class="w-full rounded-lg border px-4 py-2 text-sm" @click="simulatePix">Simular pagamento (dev)</button>
                </div>
                <div class="space-y-3 rounded-2xl bg-white p-4 shadow-sm">
                    <h2 class="font-semibold">Cartão</h2>
                    <label class="block text-sm">Cartão de teste
                        <select v-model="cardToken" class="mt-1 w-full rounded-lg border px-3 py-2">
                            <option value="tok_approved">Aprovado</option>
                            <option value="tok_declined">Recusado</option>
                        </select>
                    </label>
                    <button type="button" :disabled="busy" class="w-full rounded-lg bg-blue-600 px-4 py-2 font-medium text-white" @click="payCard">Pagar {{ order.total.formatted }}</button>
                </div>
            </div>

            <button type="button" :disabled="busy" class="text-sm text-red-700 underline" @click="cancel">Desistir e liberar assentos</button>
        </template>

        <p v-if="order.status === 'refunded'" class="rounded-lg bg-slate-100 p-3 text-sm">Reembolso de {{ order.refunded.formatted }} solicitado ao meio de pagamento.</p>

        <div v-if="order.status === 'paid'" class="text-sm">
            <button type="button" :disabled="busy" class="text-red-700 underline" @click="confirmCancel">Cancelar passagem</button>
            <p class="mt-1 text-slate-500">Reembolso: 100% até 72 h antes do embarque, 95% até 24 h, 80% até 3 h. Depois disso não é possível cancelar.</p>
        </div>
    </section>
</template>
