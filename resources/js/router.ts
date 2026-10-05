import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

export const router = createRouter({
    history: createWebHistory(),
    routes: [
        { path: '/', name: 'home', component: () => import('@/pages/SearchPage.vue') },
        { path: '/trips/:id', name: 'trip', component: () => import('@/pages/TripPage.vue') },
        { path: '/checkout', name: 'checkout', component: () => import('@/pages/CheckoutPage.vue'), meta: { auth: true } },
        { path: '/orders', name: 'orders', component: () => import('@/pages/OrdersPage.vue'), meta: { auth: true } },
        { path: '/orders/:id', name: 'order', component: () => import('@/pages/OrderPage.vue'), meta: { auth: true } },
        { path: '/login', name: 'login', component: () => import('@/pages/LoginPage.vue') },
    ],
});

router.beforeEach((to) => {
    if (to.meta.auth && !useAuthStore().isAuthenticated) {
        return { name: 'login', query: { redirect: to.fullPath } };
    }
});
