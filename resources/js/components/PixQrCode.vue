<script setup lang="ts">
import QRCode from 'qrcode';
import { ref, watchEffect } from 'vue';

const props = defineProps<{ payload: string }>();
const dataUrl = ref<string | null>(null);
const copied = ref(false);

watchEffect(async () => {
    dataUrl.value = await QRCode.toDataURL(props.payload, { margin: 1, width: 240 });
});

async function copy(): Promise<void> {
    await navigator.clipboard.writeText(props.payload);
    copied.value = true;
    setTimeout(() => (copied.value = false), 2000);
}
</script>

<template>
    <div class="space-y-3 text-center">
        <img v-if="dataUrl" :src="dataUrl" alt="QR Code Pix para pagamento" class="mx-auto h-60 w-60" />
        <label class="block text-left text-sm">Pix copia e cola
            <textarea :value="payload" readonly rows="3" class="mt-1 w-full rounded-lg border bg-slate-50 p-2 font-mono text-xs" />
        </label>
        <button type="button" class="rounded-lg border px-4 py-2 text-sm hover:bg-slate-50" @click="copy">
            {{ copied ? 'Copiado!' : 'Copiar código' }}
        </button>
    </div>
</template>
