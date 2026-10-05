<?php

declare(strict_types=1);

namespace App\Tickets;

use App\Models\Ticket;
use RuntimeException;

/**
 * Conteúdo do QR Code do bilhete: payload em base64url + HMAC-SHA256.
 * O validador do embarque confere a assinatura sem depender de nada além da
 * chave; qualquer alteração no payload invalida o bilhete.
 */
final readonly class TicketSigner
{
    public function __construct(private string $key)
    {
        if ($key === '') {
            throw new RuntimeException('Defina TICKET_SIGNING_KEY para assinar bilhetes.');
        }
    }

    public function sign(Ticket $ticket): string
    {
        $reservation = $ticket->reservation;

        $payload = self::encode((string) json_encode([
            'v' => 1,
            't' => $ticket->id,
            'trip' => $reservation->trip_id,
            'seat' => $reservation->seat_id,
            'leg' => [$reservation->origin_index, $reservation->destination_index],
        ]));

        return $payload.'.'.self::encode(hash_hmac('sha256', $payload, $this->key, true));
    }

    /**
     * @return array{ticket_id: string, trip_id: string}
     *
     * @throws InvalidTicket
     */
    public function verify(string $code): array
    {
        $parts = explode('.', trim($code));

        if (count($parts) !== 2) {
            throw new InvalidTicket('Formato de bilhete inválido.');
        }

        [$payload, $signature] = $parts;
        $expected = self::encode(hash_hmac('sha256', $payload, $this->key, true));

        if (! hash_equals($expected, $signature)) {
            throw new InvalidTicket('Assinatura do bilhete não confere.');
        }

        $data = json_decode(self::decode($payload), true);

        if (! is_array($data) || ! is_string($data['t'] ?? null) || ! is_string($data['trip'] ?? null)) {
            throw new InvalidTicket('Conteúdo do bilhete inválido.');
        }

        return ['ticket_id' => $data['t'], 'trip_id' => $data['trip']];
    }

    private static function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function decode(string $value): string
    {
        return (string) base64_decode(strtr($value, '-_', '+/'), true);
    }
}
