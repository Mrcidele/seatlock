<?php

declare(strict_types=1);

use App\Models\Ticket;
use App\Models\Trip;
use App\Models\User;
use App\Tickets\TicketSigner;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->trip = Trip::factory()->create();
    $this->buyer = customer();
    $order = placeOrder($this->trip, ['01']);
    $this->postJson("/api/v1/orders/{$order['id']}/payments", ['method' => 'card', 'card_token' => 'tok_visa'], ['Idempotency-Key' => 'pay-ticket-1'])
        ->assertJsonPath('data.status', 'paid');
    $this->ticket = Ticket::query()->firstOrFail();
    $this->code = $this->getJson("/api/v1/tickets/{$this->ticket->id}")->assertOk()->json('data.qr_code');
});

it('issues a ticket with an HMAC signed QR payload', function (): void {
    [$payload, $signature] = explode('.', $this->code);
    $claims = json_decode(base64_decode(strtr($payload, '-_', '+/'), true), true);

    expect($claims['t'])->toBe($this->ticket->id)
        ->and($claims['trip'])->toBe($this->trip->id)
        ->and(app(TicketSigner::class)->verify($this->code)['ticket_id'])->toBe($this->ticket->id)
        ->and($signature)->not->toBeEmpty();
});

it('downloads the ticket as a PDF', function (): void {
    $response = $this->get("/api/v1/tickets/{$this->ticket->id}/pdf")->assertOk();

    expect($response->headers->get('Content-Type'))->toBe('application/pdf')
        ->and(substr((string) $response->getContent(), 0, 5))->toBe('%PDF-');
});

it('does not expose tickets of other customers', function (): void {
    customer();

    $this->getJson("/api/v1/tickets/{$this->ticket->id}")->assertNotFound();
    $this->get("/api/v1/tickets/{$this->ticket->id}/pdf")->assertNotFound();
});

it('validates boarding once and blocks reuse', function (): void {
    Sanctum::actingAs(User::factory()->operator()->create());

    $this->postJson('/api/v1/boarding/validate', ['code' => $this->code, 'trip_id' => $this->trip->id])
        ->assertOk()
        ->assertJsonPath('data.valid', true)
        ->assertJsonPath('data.seat', '01');

    $this->postJson('/api/v1/boarding/validate', ['code' => $this->code])
        ->assertStatus(409)
        ->assertJsonPath('type', 'http://localhost/problems/ticket-already-used')
        ->assertJsonStructure(['used_at']);

    expect(DB::table('tickets')->whereNotNull('used_at')->count())->toBe(1);
});

it('rejects forged or tampered codes', function (): void {
    Sanctum::actingAs(User::factory()->operator()->create());
    [$payload, $signature] = explode('.', $this->code);
    $tampered = rtrim(strtr(base64_encode((string) json_encode(['v' => 1, 't' => $this->ticket->id, 'trip' => 'x'])), '+/', '-_'), '=').'.'.$signature;

    $this->postJson('/api/v1/boarding/validate', ['code' => $tampered])
        ->assertStatus(422)
        ->assertJsonPath('type', 'http://localhost/problems/invalid-ticket');
});

it('rejects tickets for another trip', function (): void {
    Sanctum::actingAs(User::factory()->operator()->create());

    $this->postJson('/api/v1/boarding/validate', ['code' => $this->code, 'trip_id' => Trip::factory()->create()->id])
        ->assertStatus(409)
        ->assertJsonPath('type', 'http://localhost/problems/ticket-wrong-trip');
});

it('only lets operators validate boarding', function (): void {
    $this->postJson('/api/v1/boarding/validate', ['code' => $this->code])->assertForbidden();
});
