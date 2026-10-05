import { syncWithServer } from '@/lib/serverClock';
import type { ProblemDetails } from './types';

export class ApiProblem extends Error {
    constructor(public readonly problem: ProblemDetails) {
        super(problem.detail ?? problem.title);
    }

    get status(): number {
        return this.problem.status;
    }

    /** Slug do tipo (ex.: "seat-unavailable"). */
    get kind(): string {
        return this.problem.type.split('/').pop() ?? 'about:blank';
    }
}

type Options = {
    method?: 'GET' | 'POST' | 'DELETE';
    body?: unknown;
    idempotencyKey?: string;
    signal?: AbortSignal;
};

let token: string | null = null;
let cartId: string | null = null;

export function setToken(value: string | null): void {
    token = value;
}

export function setCartId(value: string | null): void {
    cartId = value;
}

export async function api<T>(path: string, options: Options = {}): Promise<T> {
    const headers: Record<string, string> = { Accept: 'application/json' };

    if (options.body !== undefined) headers['Content-Type'] = 'application/json';
    if (token) headers.Authorization = `Bearer ${token}`;
    if (cartId) headers['X-Cart-Id'] = cartId;
    if (options.idempotencyKey) headers['Idempotency-Key'] = options.idempotencyKey;

    const startedAt = Date.now();
    const response = await fetch(`/api/v1${path}`, {
        method: options.method ?? 'GET',
        headers,
        body: options.body === undefined ? undefined : JSON.stringify(options.body),
        signal: options.signal,
    });

    if (response.status === 204) {
        return undefined as T;
    }

    const payload: unknown = await response.json().catch(() => null);

    if (!response.ok) {
        const problem = (payload ?? {}) as Partial<ProblemDetails>;
        throw new ApiProblem({
            type: problem.type ?? 'about:blank',
            title: problem.title ?? response.statusText,
            status: problem.status ?? response.status,
            ...problem,
        });
    }

    const serverTime = findServerTime(payload);
    if (serverTime) syncWithServer(serverTime, startedAt);

    return payload as T;
}

function findServerTime(payload: unknown): string | null {
    if (typeof payload !== 'object' || payload === null) return null;
    const record = payload as { data?: { server_time?: unknown }; meta?: { server_time?: unknown } };
    const value = record.data?.server_time ?? record.meta?.server_time;

    return typeof value === 'string' ? value : null;
}

export function newIdempotencyKey(): string {
    return crypto.randomUUID();
}
