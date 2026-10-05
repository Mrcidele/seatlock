<script setup lang="ts">
import { onMounted, ref } from 'vue';
import type { ProblemDetails, SeatAlternative } from '@/api/types';

const props = defineProps<{ problem: ProblemDetails }>();
const emit = defineEmits<{ choose: [seat: SeatAlternative]; close: [] }>();

const dialog = ref<HTMLDialogElement | null>(null);
const typeLabel: Record<SeatAlternative['type'], string> = { conventional: 'Convencional', sleeper: 'Leito', accessible: 'PCD' };

onMounted(() => dialog.value?.showModal());
</script>

<template>
    <dialog
        ref="dialog"
        aria-labelledby="seat-taken-title"
        class="m-auto w-full max-w-md rounded-2xl p-6 shadow-xl backdrop:bg-slate-900/40"
        @close="emit('close')"
    >
        <h2 id="seat-taken-title" class="text-lg font-semibold">Esse assento acabou de ser ocupado</h2>
        <p class="mt-1 text-sm text-slate-600">Outra pessoa reservou ou comprou este lugar segundos antes de você.</p>

        <template v-if="props.problem.alternatives?.length">
            <p class="mt-4 text-sm font-medium">Que tal um destes, parecidos e livres agora?</p>
            <ul class="mt-2 grid grid-cols-3 gap-2">
                <li v-for="seat in props.problem.alternatives" :key="seat.id">
                    <button
                        type="button"
                        class="w-full rounded-lg border-2 border-emerald-500 px-2 py-2 text-sm hover:bg-emerald-50 focus-visible:ring-4 focus-visible:ring-blue-300"
                        @click="emit('choose', seat)"
                    >
                        <span class="block font-semibold">{{ seat.number }}</span>
                        <span class="block text-xs text-slate-500">{{ typeLabel[seat.type] }}<template v-if="seat.deck > 1"> · piso {{ seat.deck }}</template></span>
                    </button>
                </li>
            </ul>
        </template>
        <p v-else class="mt-4 text-sm">Não há outro assento livre parecido neste trecho.</p>

        <form method="dialog" class="mt-6 text-right">
            <button class="rounded-lg px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100">Escolher no mapa</button>
        </form>
    </dialog>
</template>
