import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import { setToken } from '@/api/client';
import { endpoints } from '@/api/endpoints';
import type { User } from '@/api/types';
import { readStorage, writeStorage } from '@/lib/storage';

const TOKEN_KEY = 'seatlock.token';

export const useAuthStore = defineStore('auth', () => {
    const token = ref<string | null>(readStorage(TOKEN_KEY));
    const user = ref<User | null>(null);
    setToken(token.value);

    const isAuthenticated = computed(() => token.value !== null);

    function setSession(newToken: string | null, newUser: User | null): void {
        token.value = newToken;
        user.value = newUser;
        setToken(newToken);
        writeStorage(TOKEN_KEY, newToken);
    }

    async function login(email: string, password: string): Promise<void> {
        const { data } = await endpoints.login(email, password);
        setSession(data.token, data.user);
    }

    async function register(name: string, email: string, password: string): Promise<void> {
        const { data } = await endpoints.register(name, email, password);
        setSession(data.token, data.user);
    }

    async function loadUser(): Promise<void> {
        if (!token.value || user.value) return;
        try {
            user.value = (await endpoints.me()).data;
        } catch {
            setSession(null, null);
        }
    }

    function logout(): void {
        setSession(null, null);
    }

    return { token, user, isAuthenticated, login, register, loadUser, logout };
});
