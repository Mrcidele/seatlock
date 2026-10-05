<?php

declare(strict_types=1);

use App\Payments\PixBrCode;
use App\ValueObjects\Money;

it('computes CRC16/CCITT-FALSE', function (): void {
    expect(PixBrCode::crc16('123456789'))->toBe('29B1');
});

it('builds a valid BR Code payload', function (): void {
    $code = PixBrCode::make('pix@seatlock.test', Money::of(12_345), 'SeatLock Passagens', 'São Paulo', '01JABCDEF-123');

    expect($code)->toStartWith('000201010212')
        ->toContain('0014br.gov.bcb.pix0117pix@seatlock.test')
        ->toContain('5406123.45')
        ->toContain('5802BR')
        ->toContain('5918SEATLOCK PASSAGENS')
        ->toContain('6009SAO PAULO')
        ->toContain('0512'.'01JABCDEF123')
        ->and(substr($code, -8, 4))->toBe('6304')
        ->and(substr($code, -4))->toBe(PixBrCode::crc16(substr($code, 0, -4)));
})->skip(fn (): bool => ! function_exists('iconv'));
