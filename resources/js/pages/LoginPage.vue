<script setup lang="ts">
import { reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ApiProblem } from '@/api/client';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();
const route = useRoute();
const router = useRouter();
const mode = ref<'login' | 'register'>('login');
const form = reactive({ name: '', email: '', password: '' });
const error = ref<string | null>(null);

async function submit(): Promise<void> {
    error.value = null;
    try {
        if (mode.value === 'login') await auth.login(form.email, form.password);
        else await auth.register(form.name, form.email, form.password);
        await router.push(typeof route.query.redirect === 'string' ? route.query.redirect : '/');
    } catch (e) {
        error.value = e instanceof ApiProblem ? (Object.values(e.problem.errors ?? {})[0]?.[0] ?? e.problem.title) : 'Falha de conexão.';
    }
}
</script>

<template>
    <section class="mx-auto max-w-sm space-y-4 rounded-2xl bg-white p-6 shadow-sm">
        <h1 class="text-xl font-semibold">{{ mode === 'login' ? 'Entrar' : 'Criar conta' }}</h1>
        <p v-if="error" class="rounded-lg bg-red-50 p-3 text-sm text-red-700" role="alert">{{ error }}</p>
        <form class="space-y-3" @submit.prevent="submit">
            <label v-if="mode === 'register'" class="block text-sm">Nome<input v-model="form.name" required class="mt-1 w-full rounded-lg border px-3 py-2" /></label>
            <label class="block text-sm">E-mail<input v-model="form.email" type="email" required autocomplete="email" class="mt-1 w-full rounded-lg border px-3 py-2" /></label>
            <label class="block text-sm">Senha<input v-model="form.password" type="password" required autocomplete="current-password" class="mt-1 w-full rounded-lg border px-3 py-2" /></label>
            <button class="w-full rounded-lg bg-blue-600 px-4 py-2 font-medium text-white">{{ mode === 'login' ? 'Entrar' : 'Cadastrar' }}</button>
        </form>
        <button type="button" class="text-sm text-blue-700 underline" @click="mode = mode === 'login' ? 'register' : 'login'">
            {{ mode === 'login' ? 'Não tenho conta' : 'Já tenho conta' }}
        </button>
    </section>
</template>
