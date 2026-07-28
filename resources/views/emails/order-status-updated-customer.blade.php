<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('order_emails.updated.title_tag') }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background-color: #FFF7ED;
            color: #1c1917;
        }
        .outer { max-width: 620px; margin: 40px auto; padding: 0 16px 48px; }
        .logo-strip { text-align: center; padding: 0 0 32px; }
        .logo-strip a img { height: 52px; width: auto; }
        .card { background: #ffffff; border-radius: 20px; overflow: hidden; box-shadow: 0 8px 40px rgba(234,88,12,0.10), 0 2px 8px rgba(0,0,0,0.06); }

        .header {
            background-color: #F08B01;
            background: linear-gradient(145deg, #ea580c 0%, #f97316 55%, #fb923c 100%);
            padding: 48px 40px 40px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .header::after { content:''; position:absolute; inset:0; background: radial-gradient(ellipse at 80% 20%, rgba(255,255,255,0.18) 0%, transparent 55%), radial-gradient(ellipse at 15% 85%, rgba(0,0,0,0.08) 0%, transparent 45%); pointer-events:none; }
        .header::before { content:''; position:absolute; width:280px; height:280px; border-radius:50%; background:rgba(255,255,255,0.07); top:-100px; right:-80px; pointer-events:none; }
        .header-inner { position: relative; z-index: 1; }

        .icon-badge {
            display: inline-flex; align-items: center; justify-content: center;
            width: 76px; height: 76px;
            background: rgba(255,255,255,0.20); border: 2px solid rgba(255,255,255,0.35);
            border-radius: 50%; margin-bottom: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        }

        .header h1 { color:#fff; font-size:28px; font-weight:800; letter-spacing:-0.5px; margin-bottom:8px; text-shadow:0 1px 3px rgba(0,0,0,0.15); }
        .header p { color:rgba(255,255,255,0.88); font-size:15px; line-height:1.5; }
        .order-pill { display:inline-block; background:rgba(255,255,255,0.22); color:#fff; font-size:14px; font-weight:700; padding:6px 20px; border-radius:999px; margin-top:14px; }

        .section { padding:32px 40px; border-bottom:1px solid #f5f0eb; }
        .section:last-child { border-bottom:none; }
        .section-title { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:1px; color:#a8a29e; margin:0 0 16px; }

        .row { display:flex; justify-content:space-between; padding:6px 0; font-size:14px; }
        .row .label { color:#78716c; }
        .row .value { font-weight:600; color:#1c1917; }

        .status-badge {
            display:inline-block; padding:6px 18px; border-radius:999px;
            font-size:13px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px;
        }
        .status-processing { background:#fff7ed; color:#9a3412; border:1px solid #fed7aa; }
        .status-completed { background:#f0fdf4; color:#166534; border:1px solid #bbf7d0; }
        .status-default { background:#fff7ed; color:#ea580c; border:1px solid #fdba74; }

        table.items { width:100%; border-collapse:collapse; font-size:14px; }
        table.items thead th { background:#fafaf9; padding:10px 12px; text-align:left; font-size:11px; text-transform:uppercase; letter-spacing:0.8px; color:#a8a29e; font-weight:700; }
        table.items tbody td { padding:12px; border-bottom:1px solid #f5f5f4; }
        table.items tfoot td { padding:12px; font-weight:700; font-size:15px; border-top:2px solid #e7e5e4; color:#ea580c; }

        .address-box { background:#fff7ed; border:1px solid #fed7aa; border-radius:10px; padding:20px; font-size:14px; line-height:1.7; }
        .address-box strong { display:block; font-size:12px; text-transform:uppercase; letter-spacing:0.8px; color:#ea580c; margin-bottom:8px; }

        .footer { background:#fafaf9; border-top:1px solid #e7e5e4; padding:24px 40px; text-align:center; }
        .footer p { font-size:12px; color:#a8a29e; line-height:1.7; margin:0; }
        .footer a { color:#78716c; text-decoration:none; }
        .dot { display:inline-block; width:4px; height:4px; background:#fb923c; border-radius:50%; vertical-align:middle; margin:0 8px; }

        @media (max-width:640px) {
            .outer { margin:0; padding:0 0 32px; }
            .card { border-radius:0; }
            .header { padding:40px 24px 36px; }
            .section { padding-left:24px; padding-right:24px; }
            .footer { padding:20px 24px; }
        }
    </style>
</head>

<body>
<div class="outer">
    <div class="logo-strip">
        <a href="{{ config('app.url') }}" target="_blank">
            <img src="{{ $message->embed(public_path('images/zeker-gemak-logo.png')) }}" alt="Zeker Gemak">
        </a>
    </div>

    <div class="card">
        <div class="header">
            <div class="header-inner">
                <div class="icon-badge">
                    <svg width="34" height="34" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M1 4V10H7" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M23 20V14H17" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M20.49 9A9 9 0 0 0 5.64 5.64L1 10M23 14L18.36 18.36A9 9 0 0 1 3.51 15" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <h1>{{ __('order_emails.updated.heading') }}</h1>
                <p>{{ __('order_emails.updated.intro') }}</p>
                <span class="order-pill">{{ __('order_emails.common.order_number') }} #{{ $order->number }}</span>
            </div>
        </div>

        <div class="section">
            <p class="section-title">{{ __('order_emails.updated.status_change') }}</p>
            <div class="row">
                <span class="label">{{ __('order_emails.updated.previous_status') }}</span>
                <span class="value">{{ __('order_emails.status.'.$oldStatus) }}</span>
            </div>
            <div class="row">
                <span class="label">{{ __('order_emails.updated.new_status') }}</span>
                <span class="value">
                    @php
                        $statusValue = $order->status->value();
                        $badgeClass = match ($statusValue) {
                            'processing' => 'status-processing',
                            'completed' => 'status-completed',
                            default => 'status-default',
                        };
                    @endphp
                    <span class="status-badge {{ $badgeClass }}">{{ __('order_emails.status.'.$statusValue) }}</span>
                </span>
            </div>
        </div>

        <div class="section">
            <p class="section-title">{{ __('order_emails.common.order_summary') }}</p>
            <div class="row"><span class="label">{{ __('order_emails.common.order_number') }}</span><span class="value">#{{ $order->number }}</span></div>
            <div class="row"><span class="label">{{ __('order_emails.common.order_date') }}</span><span class="value">{{ $order->created_at->translatedFormat(__('order_emails.placed.date_format')) }}</span></div>
            <div class="row"><span class="label">{{ __('order_emails.common.customer') }}</span><span class="value">{{ $order->billpayer?->firstname }} {{ $order->billpayer?->lastname }}</span></div>
        </div>

        <div class="section">
            <p class="section-title">{{ __('order_emails.common.items_ordered') }}</p>
            <table class="items">
                <thead>
                    <tr>
                        <th>{{ __('order_emails.common.product') }}</th>
                        <th style="text-align:center">{{ __('order_emails.common.quantity') }}</th>
                        <th style="text-align:right">{{ __('order_emails.common.price') }}</th>
                        <th style="text-align:right">{{ __('order_emails.common.total') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($order->items as $item)
                        <tr>
                            <td>
                                {{ $item->display_name }}
                            </td>
                            <td style="text-align:center">{{ $item->quantity }}</td>
                            <td style="text-align:right">&euro;{{ number_format($item->price, 2) }}</td>
                            <td style="text-align:right">&euro;{{ number_format($item->price * $item->quantity, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" style="text-align:right; font-weight:normal; color:#78716c;">{{ __('order_emails.common.subtotal') }}</td>
                        <td style="text-align:right; font-weight:normal; color:#1c1917;">&euro;{{ number_format($order->itemsTotal(), 2) }}</td>
                    </tr>
                    @foreach($order->adjustments()->byType(\Vanilo\Adjustments\Models\AdjustmentTypeProxy::PROMOTION()) as $adjustment)
                        <tr>
                            <td colspan="3" style="text-align:right; font-weight:normal; color:#ea580c;">{{ $adjustment->title }}</td>
                            <td style="text-align:right; font-weight:normal; color:#ea580c;">-&euro;{{ number_format(abs($adjustment->amount), 2) }}</td>
                        </tr>
                    @endforeach
                    <tr>
                        <td colspan="3" style="text-align:right; font-weight:normal; color:#78716c;">{{ __('order_emails.common.shipping') }}</td>
                        <td style="text-align:right; font-weight:normal; color:#1c1917;">&euro;{{ number_format($order->adjustments()->byType(\Vanilo\Adjustments\Models\AdjustmentTypeProxy::SHIPPING())->total(true), 2) }}</td>
                    </tr>
                    <tr>
                        <td colspan="3" style="text-align:right; font-weight:normal; color:#78716c;">{{ __('order_emails.common.tax') }}</td>
                        <td style="text-align:right; font-weight:normal; color:#1c1917;">&euro;{{ number_format($order->adjustments()->byType(\Vanilo\Adjustments\Models\AdjustmentTypeProxy::TAX())->total(true), 2) }}</td>
                    </tr>
                    <tr>
                        <td colspan="3" style="text-align:right; font-weight:700; font-size:16px; color:#ea580c;">{{ __('order_emails.common.order_total') }}</td>
                        <td style="text-align:right; font-weight:700; font-size:16px; color:#ea580c;">&euro;{{ number_format($order->total(), 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        @if ($order->shippingAddress)
            <div class="section">
                <p class="section-title">{{ __('order_emails.common.shipping_to') }}</p>
                <div class="address-box">
                    <strong>{{ __('order_emails.common.delivery_address') }}</strong>
                    {{ $order->shippingAddress->name }}<br>
                    {{ $order->shippingAddress->address }}<br>
                    {{ $order->shippingAddress->city }}
                    @if ($order->shippingAddress->postalcode)
                        , {{ $order->shippingAddress->postalcode }}
                    @endif
                    <br>
                    {{ $order->shippingAddress->country_id }}
                </div>
            </div>
        @endif

        @if ($order->notes)
            <div class="section">
                <p class="section-title">{{ __('order_emails.common.order_notes') }}</p>
                <p style="font-size:14px; color:#57534e; margin:0;">{{ $order->notes }}</p>
            </div>
        @endif

        <div class="footer">
            <p>
                {{ __('order_emails.common.questions') }}
            </p>
            <p style="margin-top:12px;">
                &copy;&nbsp;{{ date('Y') }}&nbsp;Business&nbsp;Labels. {{ __('order_emails.common.rights') }}
                <span class="dot"></span>
                <a href="{{ config('app.url') }}" target="_blank">{{ config('app.company.website') }}</a>
                <span class="dot"></span>
                <a href="mailto:{{ config('app.company.email') }}">{{ config('app.company.email') }}</a>
            </p>
        </div>
    </div>
</div>
</body>
</html>
