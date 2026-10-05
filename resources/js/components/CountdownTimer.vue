<script setup lang="ts">
import { computed, watch } from 'vue';
import { useCountdown } from '@/composables/useCountdown';

const props = defineProps<{ expiresAt: string | null; label?: string }>();
const emit = defineEmits<{ expired: [] }>();

const { remainingSeconds, expired, label: time } = useCountdown(() => props.expiresAt);
const urgent = computed(() => remainingSeconds.value !== null && remainingSeconds.value <= 60);

watch(expired, (value) => {
    if (value) emit('expired');
});
</script>

<template>
    <p
        v-if="expiresAt"
        class="flex items-center justify-between rounded-lg px-3 py-2 text-sm font-medium"
        :class="urgent ? 'bg-red-50 text-red-700' : 'bg-blue-50 text-blue-800'"
    >
        <span>{{ props.label ?? 'Assentos reservados por' }}</span>
        <!-- aria-live só nos últimos segundos, para não narrar o relógio inteiro -->
        <time class="font-mono text-base tabular-nums" :aria-live="urgent ? 'polite' : 'off'" :datetime="`PT${remainingSeconds ?? 0}S`">{{ time }}</time>
    </p>
</template>
