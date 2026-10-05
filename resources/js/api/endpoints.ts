import { api, newIdempotencyKey } from './client';
import type { LockResponse, Order, SeatMap, Trip, TripSearchResult, User } from './types';

export interface PassengerInput {
    seat_id: string;
    name: string;
    document: string;
    email?: string;
}

export const endpoints = {
    login: (email: string, password: string) =>
        api<{ data: { token: string; user: User } }>('/auth/login', { method: 'POST', body: { email, password } }),
    register: (name: string, email: string, password: string) =>
        api<{ data: { token: string; user: User } }>('/auth/register', { method: 'POST', body: { name, email, password } }),
    me: () => api<{ data: User }>('/auth/me'),

    searchTrips: (params: { origin?: string; destination?: string; date?: string }) => {
        const query = new URLSearchParams(Object.entries(params).filter(([, v]) => v) as [string, string][]);
        return api<{ data: TripSearchResult[] }>(`/trips?${query.toString()}`);
    },
    trip: (id: string) => api<{ data: Trip }>(`/trips/${id}`),
    seatMap: (tripId: string, origin: number, destination: number, signal?: AbortSignal) =>
        api<{ data: SeatMap; meta: { server_time: string } }>(`/trips/${tripId}/seat-map?origin=${origin}&destination=${destination}`, { signal }),

    lockSeats: (tripId: string, origin: number, destination: number, seatIds: string[]) =>
        api<{ data: LockResponse }>(`/trips/${tripId}/locks`, { method: 'POST', body: { origin, destination, seat_ids: seatIds } }),
    releaseSeats: (tripId: string, origin: number, destination: number, seatIds: string[]) =>
        api<void>(`/trips/${tripId}/locks`, { method: 'DELETE', body: { origin, destination, seat_ids: seatIds } }),

    createOrder: (body: { trip_id: string; origin: number; destination: number; passengers: PassengerInput[] }, idempotencyKey = newIdempotencyKey()) =>
        api<{ data: Order }>('/orders', { method: 'POST', body, idempotencyKey }),
    order: (id: string) => api<{ data: Order }>(`/orders/${id}`),
    orders: () => api<{ data: Order[] }>('/orders'),
    cancelOrder: (id: string) => api<{ data: Order }>(`/orders/${id}/cancel`, { method: 'POST' }),
    renewOrder: (id: string) => api<{ data: Order }>(`/orders/${id}/renew`, { method: 'POST' }),
    pay: (orderId: string, body: { method: 'pix' | 'card'; card_token?: string; card_brand?: string }, idempotencyKey = newIdempotencyKey()) =>
        api<{ data: Order; payment_id: string }>(`/orders/${orderId}/payments`, { method: 'POST', body, idempotencyKey }),
    simulatePayment: (paymentId: string, status: 'approved' | 'declined') =>
        api<void>(`/dev/payments/${paymentId}/simulate`, { method: 'POST', body: { status } }),
};
