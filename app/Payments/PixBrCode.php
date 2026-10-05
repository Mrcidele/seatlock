<?php

declare(strict_types=1);

namespace App\Payments;

use App\ValueObjects\Money;
use InvalidArgumentException;

/**
 * Gera o "Pix copia e cola" (BR Code, padrão EMV QRCPS do Banco Central).
 * O mesmo texto é renderizado como QR Code no front e no bilhete.
 */
final class PixBrCode
{
    public static function make(string $pixKey, Money $amount, string $merchantName, string $merchantCity, string $txid): string
    {
        if ($amount->currency !== 'BRL') {
            throw new InvalidArgumentException('Pix só aceita BRL.');
        }

        $merchantAccount = self::field('00', 'br.gov.bcb.pix').self::field('01', $pixKey);
        $txid = substr(preg_replace('/[^A-Za-z0-9]/', '', $txid) ?? '', 0, 25);

        $payload = self::field('00', '01')
            .self::field('01', '12')
            .self::field('26', $merchantAccount)
            .self::field('52', '0000')
            .self::field('53', '986')
            .self::field('54', intdiv($amount->cents, 100).'.'.str_pad((string) ($amount->cents % 100), 2, '0', STR_PAD_LEFT))
            .self::field('58', 'BR')
            .self::field('59', self::ascii($merchantName, 25))
            .self::field('60', self::ascii($merchantCity, 15))
            .self::field('62', self::field('05', $txid !== '' ? $txid : '***'))
            .'6304';

        return $payload.self::crc16($payload);
    }

    /**
     * CRC16/CCITT-FALSE (polinômio 0x1021, valor inicial 0xFFFF).
     */
    public static function crc16(string $payload): string
    {
        $crc = 0xFFFF;

        foreach (str_split($payload) as $char) {
            $crc ^= ord($char) << 8;

            for ($bit = 0; $bit < 8; $bit++) {
                $crc = ($crc & 0x8000) !== 0 ? (($crc << 1) ^ 0x1021) : ($crc << 1);
                $crc &= 0xFFFF;
            }
        }

        return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
    }

    private static function field(string $id, string $value): string
    {
        $length = strlen($value);

        if ($length > 99) {
            throw new InvalidArgumentException("Campo {$id} do BR Code excede 99 caracteres.");
        }

        return $id.str_pad((string) $length, 2, '0', STR_PAD_LEFT).$value;
    }

    private static function ascii(string $value, int $max): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT', $value);

        return substr(strtoupper(preg_replace('/[^A-Za-z0-9 ]/', '', $ascii === false ? $value : $ascii) ?? ''), 0, $max);
    }
}
