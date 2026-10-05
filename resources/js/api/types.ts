export interface Money {
    cents: number;
    currency: string;
    formatted: string;
}

export interface Leg {
    origin: number;
    destination: number;
}

export interface Stop {
    index: number;
    name: string;
    city: string;
    state: string;
    departure_at: string;
    fare_from_origin: Money;
}

export interface Trip {
    id: string;
    status: string;
    departure_at: string;
    route: { id: string; code: string; name: string };
    vehicle: { id: string; name: string };
    stops: Stop[];
}

export interface TripSearchResult extends Trip {
    leg: Leg;
    available_seats: number;
    from_price: Money;
}

export type SeatStatus = 'available' | 'locked' | 'sold';
export type SeatType = 'conventional' | 'sleeper' | 'accessible';
export type CellKind = 'seat' | 'aisle' | 'empty' | 'toilet' | 'stairs';

export interface SeatInfo {
    id: string;
    number: string;
    type: SeatType;
    type_label: string;
    status: SeatStatus;
    held_by_you: boolean;
    price: Money;
}

export interface SeatCell {
    row: number;
    column: number;
    kind: CellKind;
    seat: SeatInfo | null;
}

export interface Deck {
    level: number;
    rows: number;
    columns: number;
    cells: SeatCell[][];
}

export interface SeatMap {
    trip_id: string;
    leg: Leg & { segments: number[] };
    decks: Deck[];
    summary: { available: number; locked: number; sold: number };
}

export interface LockResponse {
    trip_id: string;
    seat_ids: string[];
    leg: Leg;
    expires_at: string | null;
    server_time: string;
    degraded: boolean;
}

export type OrderStatus = 'pending' | 'paid' | 'expired' | 'cancelled' | 'refunded';

export interface Payment {
    id: string;
    method: 'pix' | 'card';
    status: 'pending' | 'approved' | 'declined' | 'cancelled' | 'refunded';
    amount: Money;
    pix: { copy_paste: string; expires_at: string | null } | null;
    card: { brand: string | null; last_four: string } | null;
    failure_reason: string | null;
}

export interface Order {
    id: string;
    status: OrderStatus;
    trip_id: string;
    leg: Leg;
    total: Money;
    refunded: Money;
    expires_at: string;
    server_time: string;
    renewals_left: number;
    paid_at: string | null;
    cancellation_reason: string | null;
    reservations: {
        id: string;
        status: string;
        seat: { id: string; number: string; type: SeatType; deck: number };
        passenger: { name: string; document: string };
        price: Money;
        ticket_id: string | null;
    }[];
    payments: Payment[];
}

export interface User {
    id: string;
    name: string;
    email: string;
    role: 'customer' | 'operator' | 'admin';
}

export interface SeatAlternative {
    id: string;
    number: string;
    type: SeatType;
    deck: number;
}

/** RFC 9457 */
export interface ProblemDetails {
    type: string;
    title: string;
    status: number;
    detail?: string;
    instance?: string;
    errors?: Record<string, string[]>;
    conflicting_seat_ids?: string[];
    alternatives?: SeatAlternative[];
}
