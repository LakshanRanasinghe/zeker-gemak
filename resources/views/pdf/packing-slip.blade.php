<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Pakbon {{ $order->number }}</title>
    <style>
        body { color: #1c1917; font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        header { border-bottom: 3px solid #f08b01; margin-bottom: 28px; padding-bottom: 16px; }
        h1 { color: #f08b01; font-size: 26px; margin: 0; }
        .meta { color: #78716c; margin-top: 6px; }
        .address { background: #fff7ed; border: 1px solid #fed7aa; margin-bottom: 24px; padding: 14px; }
        table { border-collapse: collapse; width: 100%; }
        th { background: #fafaf9; color: #78716c; font-size: 10px; padding: 9px; text-align: left; text-transform: uppercase; }
        td { border-bottom: 1px solid #e7e5e4; padding: 10px 9px; }
        .number { text-align: right; }
        footer { color: #a8a29e; font-size: 10px; margin-top: 28px; text-align: center; }
    </style>
</head>
<body>
<header>
    <h1>Zeker Gemak</h1>
    <div class="meta">Pakbon #{{ $order->number }} · {{ now()->format('d-m-Y') }}</div>
</header>

@if($recipient)
    <div class="address">
        <strong>Bezorgadres</strong><br>
        {{ $recipient['first_name'] }} {{ $recipient['last_name'] }}<br>
        @if($recipient['company']){{ $recipient['company'] }}<br>@endif
        {{ $recipient['street'] }} {{ $recipient['house_number'] }} {{ $recipient['addition'] }}<br>
        {{ $recipient['postal_code'] }} {{ $recipient['city'] }}<br>
        {{ $recipient['country_code'] }}
    </div>
@elseif($order->shippingAddress)
    <div class="address">
        <strong>Bezorgadres</strong><br>
        {{ $order->shippingAddress->name }}<br>
        @if($order->shippingAddress->company_name){{ $order->shippingAddress->company_name }}<br>@endif
        {{ $order->shippingAddress->address }} @if($order->shippingAddress->address2){{ $order->shippingAddress->address2 }}@endif<br>
        {{ $order->shippingAddress->postalcode }} {{ $order->shippingAddress->city }}<br>
        {{ $order->shippingAddress->country_id }}
    </div>
@endif

<table>
    <thead>
        <tr>
            <th>Product</th>
            <th class="number">Aantal</th>
            <th class="number">Prijs</th>
        </tr>
    </thead>
    <tbody>
        @forelse($order->items as $item)
            <tr>
                <td>{{ $item->display_name }}</td>
                <td class="number">{{ $item->quantity }}</td>
                <td class="number">&euro; {{ number_format((float) $item->price, 2, ',', '.') }}</td>
            </tr>
        @empty
            <tr><td colspan="3">Geen orderregels.</td></tr>
        @endforelse
    </tbody>
</table>

@if($order->notes)
    <p><strong>Notities:</strong> {{ $order->notes }}</p>
@endif

<footer>Zeker Gemak · Pakbon voor order #{{ $order->number }}</footer>
</body>
</html>
