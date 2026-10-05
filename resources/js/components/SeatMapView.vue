<script setup lang="ts">
import { computed, nextTick, ref } from 'vue';
import type { Deck, SeatCell, SeatInfo, SeatMap } from '@/api/types';
import { directionFromKey, firstSeat, moveInGrid, type Position } from '@/lib/seatGrid';

const props = defineProps<{
    seatMap: SeatMap;
    selectedIds: string[];
    pendingId: string | null;
}>();

const emit = defineEmits<{ toggle: [seat: SeatInfo] }>();

const statusLabel: Record<SeatInfo['status'], string> = {
    available: 'disponível',
    locked: 'reservado por outra pessoa',
    sold: 'vendido',
};

// Posição focada por andar (roving tabindex: só um assento por andar é tabulável).
const focused = ref<Record<number, Position | null>>({});
const buttons = new Map<string, HTMLButtonElement>();

const focusedFor = (deck: Deck): Position | null => focused.value[deck.level] ?? firstSeat(deck);

const isSelected = (seat: SeatInfo): boolean => props.selectedIds.includes(seat.id) || seat.held_by_you;
const isBlocked = (seat: SeatInfo): boolean => seat.status === 'sold' || (seat.status === 'locked' && !isSelected(seat));

function seatClasses(seat: SeatInfo): string[] {
    if (isSelected(seat)) return ['bg-blue-600', 'text-white', 'border-blue-700'];
    if (seat.status === 'sold') return ['bg-slate-300', 'text-slate-500', 'border-slate-300', 'cursor-not-allowed', 'line-through'];
    if (seat.status === 'locked') return ['bg-amber-100', 'text-amber-800', 'border-amber-400', 'cursor-not-allowed', 'seat-locked'];
    if (seat.type === 'sleeper') return ['bg-white', 'border-violet-400', 'hover:bg-violet-50'];
    if (seat.type === 'accessible') return ['bg-white', 'border-teal-500', 'hover:bg-teal-50'];
    return ['bg-white', 'border-emerald-500', 'hover:bg-emerald-50'];
}

function ariaLabel(seat: SeatInfo): string {
    const state = isSelected(seat) ? 'selecionado por você' : statusLabel[seat.status];
    return `Assento ${seat.number}, ${seat.type_label}, ${state}, ${seat.price.formatted}`;
}

function onActivate(deck: Deck, cell: SeatCell): void {
    focused.value = { ...focused.value, [deck.level]: { row: cell.row, column: cell.column } };
    if (cell.seat && !isBlocked(cell.seat) && props.pendingId === null) emit('toggle', cell.seat);
}

async function onKeydown(event: KeyboardEvent, deck: Deck, cell: SeatCell): Promise<void> {
    const direction = directionFromKey(event.key);
    if (!direction) return;

    event.preventDefault();
    const next = moveInGrid(deck, { row: cell.row, column: cell.column }, direction);
    focused.value = { ...focused.value, [deck.level]: next };

    await nextTick();
    buttons.get(`${deck.level}:${next.row}:${next.column}`)?.focus();
}

function register(deck: Deck, cell: SeatCell, el: unknown): void {
    const key = `${deck.level}:${cell.row}:${cell.column}`;
    if (el instanceof HTMLButtonElement) buttons.set(key, el);
    else buttons.delete(key);
}

const multiDeck = computed(() => props.seatMap.decks.length > 1);
</script>

<template>
    <div class="space-y-6">
        <section v-for="deck in seatMap.decks" :key="deck.level" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <h3 v-if="multiDeck" :id="`deck-${deck.level}`" class="mb-3 text-sm font-semibold text-slate-600">
                {{ deck.level === 1 ? 'Piso inferior' : `Piso superior (${deck.level})` }}
            </h3>
            <div
                role="grid"
                :aria-labelledby="multiDeck ? `deck-${deck.level}` : undefined"
                :aria-label="multiDeck ? undefined : 'Mapa de assentos'"
                aria-describedby="seat-map-help"
                class="mx-auto grid max-w-sm gap-1.5"
                :style="{ gridTemplateColumns: `repeat(${deck.columns}, minmax(2.5rem, 1fr))` }"
            >
                <div v-for="(row, r) in deck.cells" :key="r" role="row" class="contents">
                    <div v-for="cell in row" :key="`${cell.row}-${cell.column}`" role="gridcell" class="aspect-square">
                        <button
                            v-if="cell.seat"
                            :ref="(el) => register(deck, cell, el)"
                            type="button"
                            :data-seat="cell.seat.number"
                            :tabindex="focusedFor(deck)?.row === cell.row && focusedFor(deck)?.column === cell.column ? 0 : -1"
                            :aria-label="ariaLabel(cell.seat)"
                            :aria-pressed="isSelected(cell.seat)"
                            :aria-disabled="isBlocked(cell.seat)"
                            :aria-busy="pendingId === cell.seat.id"
                            class="flex h-full w-full items-center justify-center rounded-lg border-2 text-xs font-semibold transition focus:outline-none focus-visible:ring-4 focus-visible:ring-blue-300"
                            :class="[...seatClasses(cell.seat), pendingId === cell.seat.id ? 'animate-pulse' : '']"
                            @click="onActivate(deck, cell)"
                            @keydown="onKeydown($event, deck, cell)"
                        >
                            {{ cell.seat.number }}
                        </button>
                        <span v-else-if="cell.kind === 'toilet'" class="flex h-full items-center justify-center text-[10px] text-slate-400" aria-hidden="true">WC</span>
                        <span v-else-if="cell.kind === 'stairs'" class="flex h-full items-center justify-center text-[10px] text-slate-400" aria-hidden="true">⇅</span>
                        <span v-else aria-hidden="true" />
                    </div>
                </div>
            </div>
        </section>

        <p id="seat-map-help" class="sr-only">Use as setas para navegar entre os assentos e Enter ou Espaço para selecionar.</p>

        <ul class="flex flex-wrap gap-4 text-xs text-slate-600" aria-label="Legenda">
            <li class="flex items-center gap-1.5"><span class="h-4 w-4 rounded border-2 border-emerald-500 bg-white" />Convencional</li>
            <li class="flex items-center gap-1.5"><span class="h-4 w-4 rounded border-2 border-violet-400 bg-white" />Leito</li>
            <li class="flex items-center gap-1.5"><span class="h-4 w-4 rounded border-2 border-teal-500 bg-white" />PCD</li>
            <li class="flex items-center gap-1.5"><span class="h-4 w-4 rounded border-2 border-blue-700 bg-blue-600" />Seu</li>
            <li class="flex items-center gap-1.5"><span class="seat-locked h-4 w-4 rounded border-2 border-amber-400 bg-amber-100" />Reservado</li>
            <li class="flex items-center gap-1.5"><span class="h-4 w-4 rounded bg-slate-300" />Vendido</li>
        </ul>
    </div>
</template>

<style scoped>
.seat-locked {
    background-image: repeating-linear-gradient(45deg, transparent 0 4px, rgb(251 191 36 / 0.35) 4px 8px);
}
</style>
