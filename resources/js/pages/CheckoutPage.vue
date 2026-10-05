<script setup lang="ts">
import { reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { ApiProblem, newIdempotencyKey } from '@/api/client';
import { endpoints } from '@/api/endpoints';
import CountdownTimer from '@/components/CountdownTimer.vue';
import { useCartStore } from '@/stores/cart';

const cart = useCartStore();
const router = useRouter();

const passengers = reactive(cart.seats.map((seat) => ({ seat_id: seat.id, number: seat.number, name: '', document: '', email: '' })));
const error = ref<string | null>(null);
const fieldErrors = ref<Record<string, string[]>>({});
const submitting = ref(false);
// Mesma chave em retentativas: clique duplo ou timeout não criam dois pedidos.
const idempotencyKey = newIdempotencyKey();

async function submit(): Promise<void> {
    if (!cart.tripId || !cart.leg || submitting.value) return;

    submitting.value = true;
    error.value = null;
    fieldErrors.value = {};

    try {
        const { data } = await endpoints.createOrder({
            trip_id: cart.tripId,
            origin: cart.leg.origin,
            destination: cart.leg.destination,
            passengers: passengers.map(({ seat_id, name, document, email }) => ({ seat_id, name, document, email: email || undefined })),
        }, idempotencyKey);

        cart.clear();
        await router.push({ name: 'order', params: { id: data.id } });
    } catch (e) {
        if (e instanceof ApiProblem) {
            error.value = e.problem.detail ?? e.problem.title;
            fieldErrors.value = e.problem.errors ?? {};
        } else {
            error.value = 'Falha de conexão. Tente novamente.';
        }
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <section class="mx-auto max-w-2xl space-y-4">
        <h1 class="text-xl font-semibold">Dados dos passageiros</h1>
        <p v-if="cart.seats.length === 0" class="text-sm">Seu carrinho está vazio. <RouterLink to="/" class="text-blue-700 underline">Buscar viagens</RouterLink></p>

        <template v-else>
            <CountdownTimer :expires-at="cart.expiresAt" label="Finalize antes de" @expired="router.push({ name: 'home' })" />
            <p v-if="error" class="rounded-lg bg-red-50 p-3 text-sm text-red-700" role="alert">{{ error }}</p>

            <form class="space-y-4" @submit.prevent="submit">
                <fieldset v-for="(p, i) in passengers" :key="p.seat_id" class="grid gap-3 rounded-2xl bg-white p-4 shadow-sm sm:grid-cols-3">
                    <legend class="px-1 text-sm font-semibold">Assento {{ p.number }}</legend>
                    <label class="text-sm">Nome completo
                        <input v-model="p.name" required minlength="3" class="mt-1 w-full rounded-lg border px-3 py-2" :aria-invalid="!!fieldErrors[`passengers.${i}.name`]" />
                    </label>
                    <label class="text-sm">CPF ou documento
                        <input v-model="p.document" required class="mt-1 w-full rounded-lg border px-3 py-2" :aria-invalid="!!fieldErrors[`passengers.${i}.document`]" />
                    </label>
                    <label class="text-sm">E-mail (opcional)
                        <input v-model="p.email" type="email" class="mt-1 w-full rounded-lg border px-3 py-2" />
                    </label>
                </fieldset>
                <button :disabled="submitting" class="w-full rounded-lg bg-blue-600 px-4 py-3 font-medium text-white hover:bg-blue-700 disabled:opacity-60">
                    {{ submitting ? 'Criando pedido…' : `Ir para pagamento (${cart.total?.formatted})` }}
                </button>
            </form>
        </template>
    </section>
</template>
