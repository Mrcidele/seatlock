<script setup lang="ts">
import { onMounted } from 'vue';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();
onMounted(() => auth.loadUser());
</script>

<template>
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:p-2">Pular para o conteúdo</a>
    <header class="border-b bg-white">
        <nav class="mx-auto flex max-w-6xl items-center justify-between p-4" aria-label="Principal">
            <RouterLink to="/" class="text-lg font-bold text-blue-700">SeatLock</RouterLink>
            <div class="flex items-center gap-4 text-sm">
                <RouterLink v-if="auth.isAuthenticated" :to="{ name: 'orders' }">Meus pedidos</RouterLink>
                <button v-if="auth.isAuthenticated" type="button" @click="auth.logout()">Sair</button>
                <RouterLink v-else :to="{ name: 'login' }">Entrar</RouterLink>
            </div>
        </nav>
    </header>
    <main id="main" class="mx-auto max-w-6xl p-4">
        <RouterView />
    </main>
</template>
