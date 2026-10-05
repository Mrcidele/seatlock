<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Bilhete {{ $seat->number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #0f172a; font-size: 12px; }
        .ticket { border: 2px solid #1d4ed8; border-radius: 12px; padding: 20px; }
        h1 { color: #1d4ed8; margin: 0 0 4px; font-size: 22px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        td { padding: 6px 0; vertical-align: top; }
        .label { color: #64748b; font-size: 10px; text-transform: uppercase; }
        .value { font-size: 14px; font-weight: bold; }
        .qr { text-align: center; }
        .muted { color: #64748b; font-size: 10px; }
    </style>
</head>
<body>
<div class="ticket">
    <h1>SeatLock — Bilhete de passagem</h1>
    <p class="muted">Pedido {{ $order->id }} · Bilhete {{ $ticket->id }}</p>
    <table>
        <tr>
            <td>
                <div class="label">Passageiro</div><div class="value">{{ $passenger->name }}</div>
                <div class="label">Documento</div><div class="value">{{ $maskedDocument }}</div>
                <div class="label">Embarque</div><div class="value">{{ $origin->city }} — {{ $origin->name }}</div>
                <div class="value">{{ $departure->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</div>
                <div class="label">Desembarque</div><div class="value">{{ $destination->city }} — {{ $destination->name }}</div>
                <div class="label">Assento</div><div class="value">{{ $seat->number }} ({{ $seat->type->label() }}, piso {{ $seat->deck }})</div>
                <div class="label">Valor</div><div class="value">{{ $reservation->price->format() }}</div>
            </td>
            <td class="qr">
                <img src="{{ $qr }}" width="200" height="200" alt="QR Code">
                <p class="muted">Apresente este QR Code no embarque.<br>Válido para um único uso.</p>
            </td>
        </tr>
    </table>
</div>
</body>
</html>
