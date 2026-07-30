@php
    $details = new \App\Support\OrderEmailDetails($order);
    $type = str_replace('_admin', '', $emailType);
    $translationGroup = match ($type) {
        'placed' => 'placed',
        'cancelled' => 'cancelled',
        'shipped' => 'shipped',
        default => 'updated',
    };
    $customerName = trim(($order->billpayer?->firstname ?? '').' '.($order->billpayer?->lastname ?? ''))
        ?: __('order_emails.placed.fallback_name');
    $status = $type === 'shipped' ? 'shipped' : $order->status->value();
    $palette = match ($type) {
        'shipped' => ['main' => '#047857', 'soft' => '#ecfdf5', 'border' => '#a7f3d0', 'text' => '#065f46', 'icon' => '✓'],
        'cancelled' => ['main' => '#b91c1c', 'soft' => '#fef2f2', 'border' => '#fecaca', 'text' => '#991b1b', 'icon' => '×'],
        'updated' => ['main' => '#b45309', 'soft' => '#fffbeb', 'border' => '#fde68a', 'text' => '#92400e', 'icon' => '↻'],
        default => ['main' => '#E9A821', 'soft' => '#fffaf0', 'border' => '#f5dfad', 'text' => '#8a5b00', 'icon' => '✓'],
    };
    $heading = $isAdmin && $type === 'placed'
        ? __('New order received')
        : __('order_emails.'.$translationGroup.'.heading');
    $intro = $isAdmin && $type === 'placed'
        ? __('A paid order is ready for processing.')
        : __('order_emails.'.$translationGroup.'.intro');
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>{{ __('order_emails.'.$translationGroup.'.title_tag') }}</title>
    <style>
        body { margin:0; padding:0; width:100%!important; background:#f5f7f9; color:#2C3642; font-family:Arial,Helvetica,sans-serif; -webkit-text-size-adjust:100%; }
        table { border-spacing:0; }
        img { border:0; display:block; }
        a { color:#8a5b00; }
        .container { width:100%; max-width:640px; }
        .summary { border-collapse:collapse; width:100%; }
        .summary td { border-bottom:1px solid #e9eef4; font-size:14px; line-height:1.5; padding:11px 0; vertical-align:top; }
        .summary tr:last-child td { border-bottom:0; }
        .label { color:#6F7983; padding-right:20px!important; width:42%; }
        .value { color:#2C3642; font-weight:700; overflow-wrap:anywhere; text-align:right; }
        .items { border-collapse:collapse; table-layout:fixed; width:100%; }
        .items th { background:#f8fafc; border-bottom:1px solid #e9eef4; color:#6F7983; font-size:10px; letter-spacing:.7px; padding:11px 8px; text-align:left; text-transform:uppercase; }
        .items td { border-bottom:1px solid #eef2f6; color:#2C3642; font-size:13px; line-height:1.45; overflow-wrap:anywhere; padding:13px 8px; vertical-align:top; }
        .items tfoot td { border-bottom:0; padding-bottom:5px; padding-top:8px; }
        .section-title { color:#6F7983; font-size:11px; font-weight:700; letter-spacing:1px; margin:0 0 16px; text-transform:uppercase; }
        @media only screen and (max-width:600px) {
            .outer { padding:10px 6px!important; }
            .pad { padding:24px 20px!important; }
            .label,.value { display:block!important; text-align:left!important; width:100%!important; }
            .label { border-bottom:0!important; padding-bottom:2px!important; }
            .value { padding-top:0!important; }
            .hide-mobile { display:none!important; }
        }
    </style>
</head>
<body>
<div style="display:none;font-size:1px;line-height:1px;max-height:0;opacity:0;overflow:hidden;">{{ $intro }} #{{ $order->number }}</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
    <tr>
        <td class="outer" align="center" style="padding:28px 14px;">
            <table role="presentation" class="container" width="640" cellpadding="0" cellspacing="0" border="0">
                <tr>
                    <td class="pad" style="background:#fff;border-radius:16px 16px 0 0;padding:22px 32px;">
                        <a href="{{ config('app.url') }}">
                            <img src="{{ $message->embed(public_path('images/zeker-gemak-logo.png')) }}" alt="Zeker Gemak" style="height:auto;max-height:52px;max-width:220px;width:auto;">
                        </a>
                    </td>
                </tr>
                <tr>
                    <td class="pad" style="background:{{ $palette['main'] }};padding:34px 32px;text-align:center;">
                        <table role="presentation" align="center"><tr><td style="background:rgba(255,255,255,.18);border:2px solid rgba(255,255,255,.35);border-radius:50%;color:#fff;font-size:25px;font-weight:700;height:48px;line-height:48px;text-align:center;width:48px;">{{ $palette['icon'] }}</td></tr></table>
                        <h1 style="color:#fff;font-size:28px;line-height:1.2;margin:18px 0 0;">{{ $heading }}</h1>
                        <p style="color:#fff;font-size:15px;line-height:1.55;margin:10px auto 0;max-width:500px;">{{ $intro }}</p>
                        <span style="background:rgba(255,255,255,.18);border-radius:8px;color:#fff;display:inline-block;font-size:13px;font-weight:700;margin-top:20px;padding:9px 16px;">#{{ $order->number }}</span>
                    </td>
                </tr>
                @if(! $isAdmin && $type !== 'updated')
                    <tr><td class="pad" style="background:{{ $palette['soft'] }};border-bottom:1px solid {{ $palette['border'] }};color:{{ $palette['text'] }};font-size:15px;line-height:1.65;padding:22px 32px;text-align:center;">{{ __('order_emails.'.$translationGroup.'.greeting', ['name' => $customerName]) }} {{ __('order_emails.'.$translationGroup.'.summary_intro') }}</td></tr>
                @endif
                <tr>
                    <td class="pad" style="background:#fff;border-bottom:1px solid #e9eef4;padding:28px 32px;">
                        <p class="section-title">{{ __('order_emails.common.order_summary') }}</p>
                        <table class="summary">
                            <tr><td class="label">{{ __('order_emails.common.order_number') }}</td><td class="value">#{{ $order->number }}</td></tr>
                            <tr><td class="label">{{ __('order_emails.common.order_date') }}</td><td class="value">{{ $order->created_at?->format(__('order_emails.placed.date_format')) }}</td></tr>
                            <tr><td class="label">{{ __('order_emails.common.status') }}</td><td class="value" style="color:{{ $palette['text'] }};">{{ __('order_emails.status.'.$status) }}</td></tr>
                            <tr><td class="label">{{ __('order_emails.common.customer') }}</td><td class="value">{{ $customerName }}</td></tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td class="pad" style="background:#fff;border-bottom:1px solid #e9eef4;padding:28px 32px;">
                        <p class="section-title">{{ __('order_emails.common.items_ordered') }}</p>
                        <table class="items">
                            <thead><tr><th style="width:44%;">{{ __('order_emails.common.product') }}</th><th style="text-align:center;width:14%;">{{ __('order_emails.common.quantity') }}</th><th class="hide-mobile" style="text-align:right;width:19%;">{{ __('order_emails.common.price') }}</th><th style="text-align:right;width:23%;">{{ __('order_emails.common.total') }}</th></tr></thead>
                            <tbody>
                                @foreach($details->items() as $item)
                                    <tr>
                                        <td style="font-weight:600;">{{ $item['name'] }} @foreach($item['meta'] as $meta)<span style="color:#6F7983;display:block;font-size:11px;font-weight:400;margin-top:3px;">{{ $meta }}</span>@endforeach</td>
                                        <td style="text-align:center;">{{ $item['quantity'] }}</td>
                                        <td class="hide-mobile" style="text-align:right;">&euro;&nbsp;{{ number_format($item['price'], 2, ',', '.') }}</td>
                                        <td style="font-weight:600;text-align:right;">&euro;&nbsp;{{ number_format($item['total'], 2, ',', '.') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr><td colspan="3" style="color:#6F7983;text-align:right;">{{ __('order_emails.common.subtotal') }}</td><td style="text-align:right;">&euro;&nbsp;{{ number_format($details->subtotal(), 2, ',', '.') }}</td></tr>
                                @if($details->discount() > 0)<tr><td colspan="3" style="color:#6F7983;text-align:right;">{{ __('Discount') }}</td><td style="text-align:right;">-&euro;&nbsp;{{ number_format($details->discount(), 2, ',', '.') }}</td></tr>@endif
                                <tr><td colspan="3" style="color:#6F7983;text-align:right;">{{ __('order_emails.common.shipping') }}</td><td style="text-align:right;">&euro;&nbsp;{{ number_format($details->shipping(), 2, ',', '.') }}</td></tr>
                                @if($details->fees() > 0)<tr><td colspan="3" style="color:#6F7983;text-align:right;">{{ __('Payment fee') }}</td><td style="text-align:right;">&euro;&nbsp;{{ number_format($details->fees(), 2, ',', '.') }}</td></tr>@endif
                                <tr><td colspan="3" style="color:#6F7983;text-align:right;">{{ __('order_emails.common.tax') }}</td><td style="text-align:right;">&euro;&nbsp;{{ number_format($details->tax(), 2, ',', '.') }}</td></tr>
                                <tr><td colspan="3" style="color:{{ $palette['text'] }};font-weight:700;text-align:right;">{{ __('order_emails.common.order_total') }}</td><td style="color:{{ $palette['text'] }};font-size:15px;font-weight:700;text-align:right;">&euro;&nbsp;{{ number_format($details->total(), 2, ',', '.') }}</td></tr>
                            </tfoot>
                        </table>
                    </td>
                </tr>
                @if($order->shippingAddress)
                    <tr><td class="pad" style="background:#fff;border-bottom:1px solid #e9eef4;padding:28px 32px;"><p class="section-title">{{ __('order_emails.common.shipping_to') }}</p><div style="background:{{ $palette['soft'] }};border:1px solid {{ $palette['border'] }};border-radius:10px;color:#2C3642;font-size:14px;line-height:1.7;padding:18px;"><strong style="color:{{ $palette['text'] }};display:block;font-size:11px;letter-spacing:.8px;margin-bottom:7px;text-transform:uppercase;">{{ __('order_emails.common.delivery_address') }}</strong>{{ $order->shippingAddress->name }}<br>{{ $order->shippingAddress->address }}<br>{{ $order->shippingAddress->postalcode }} {{ $order->shippingAddress->city }}<br>{{ $order->shippingAddress->country_id }}</div></td></tr>
                @endif
                @if($type === 'shipped' && $order->tracking_number && isset($trackingUrl))
                    <tr><td class="pad" style="background:#fff;border-bottom:1px solid #e9eef4;padding:28px 32px;text-align:center;"><p class="section-title">{{ __('order_emails.shipped.tracking_title') }}</p><p style="color:#6F7983;font-size:13px;margin:0 0 18px;">{{ $carrierName ?? '' }} &middot; {{ __('order_emails.shipped.tracking_number') }}: <strong style="color:#2C3642;">{{ $order->tracking_number }}</strong></p><a href="{{ $trackingUrl }}" style="background:{{ $palette['main'] }};border-radius:9px;color:#fff;display:inline-block;font-size:14px;font-weight:700;padding:12px 22px;text-decoration:none;">{{ __('order_emails.shipped.track_button') }}</a></td></tr>
                @endif
                <tr><td class="pad" style="background:#f8fafc;border-radius:0 0 16px 16px;color:#6F7983;font-size:12px;line-height:1.7;padding:22px 32px;text-align:center;"><div style="margin-bottom:8px;">{{ $isAdmin ? __('Automated order notification.') : __('order_emails.common.questions') }}</div>&copy; {{ date('Y') }} Zeker Gemak &nbsp;&middot;&nbsp; <a href="mailto:{{ config('app.company.email') }}">{{ config('app.company.email') }}</a></td></tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
