<?php

declare(strict_types=1);

namespace App\Tickets;

use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

final class TicketQrCode
{
    /**
     * PNG em data URI (o dompdf renderiza PNG de forma confiável).
     */
    public static function dataUri(string $content): string
    {
        return (new QRCode(new QROptions([
            'outputInterface' => QRGdImagePNG::class,
            'outputBase64' => true,
            'scale' => 6,
            'addQuietzone' => true,
        ])))->render($content);
    }
}
