<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Cancelled – Business Labels</title>
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

        .alert-box { background:#fff7ed; border:1px solid #fed7aa; border-left:4px solid #f97316; border-radius:10px; padding:16px 20px; font-size:14px; color:#78350f; line-height:1.6; }

        table.items { width:100%; border-collapse:collapse; font-size:14px; }
        table.items thead th { background:#fafaf9; padding:10px 12px; text-align:left; font-size:11px; text-transform:uppercase; letter-spacing:0.8px; color:#a8a29e; font-weight:700; }
        table.items tbody td { padding:12px; border-bottom:1px solid #f5f5f4; color:#a8a29e; }
        table.items tfoot td { padding:12px; font-weight:700; font-size:15px; border-top:2px solid #e7e5e4; }

        .footer { background:#fafaf9; border-top:1px solid #e7e5e4; padding:24px 40px; text-align:center; }
        .footer p { font-size:12px; color:#a8a29e; line-height:1.7; margin:0; }

        @media (max-width:640px) {
            .outer { margin:0; padding:0 0 32px; }
            .card { border-radius:0; }
            .header { padding:40px 24px 36px; }
            .section { padding-left:24px; padding-right:24px; }
        }
    </style>
</head>

<body>
<div class="outer">
    <div class="logo-strip">
        <a href="{{ config('app.url') }}" target="_blank">
            <img src="{{ $message->embed(public_path('images/bbnl-logo.png')) }}" alt="Business Labels">
        </a>
    </div>

    <div class="card">
        <div class="header">
            <div class="header-inner">
                <div class="icon-badge">
                    <svg width="34" height="34" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="12" cy="12" r="9" stroke="white" stroke-width="2" fill="rgba(255,255,255,0.15)"/>
                        <path d="M15 9L9 15" stroke="white" stroke-width="2.5" stroke-linecap="round"/>
                        <path d="M9 9L15 15" stroke="white" stroke-width="2.5" stroke-linecap="round"/>
                    </svg>
                </div>
                <h1>Order Cancelled</h1>
                <p>An order has been cancelled on your store</p>
                <span class="order-pill">Order #{{ $order->number }}</span>
            </div>
        </div>

        <div class="section">
            <div class="alert-box">
                This order has been marked as <strong>cancelled</strong>. Please review the detail below.
            </div>
        </div>

        <div class="section">
            <p class="section-title">Order Details</p>
            <div class="row"><span class="label">Order Number</span><span class="value">#{{ $order->number }}</span></div>
            <div class="row"><span class="label">Order Date</span><span class="value">{{ $order->created_at->format('M d, Y – H:i') }}</span></div>
            <div class="row"><span class="label">Cancelled At</span><span class="value">{{ $order->updated_at->format('M d, Y – H:i') }}</span></div>
        </div>

        <div class="section">
            <p class="section-title">Customer</p>
            @if($order->billpayer)
                <div class="row"><span class="label">Name</span><span class="value">{{ $order->billpayer->firstname }} {{ $order->billpayer->lastname }}</span></div>
                @if($order->billpayer->email)
                    <div class="row"><span class="label">Email</span><span class="value">{{ $order->billpayer->email }}</span></div>
                @endif
                @if($order->billpayer->phone)
                    <div class="row"><span class="label">Phone</span><span class="value">{{ $order->billpayer->phone }}</span></div>
                @endif
            @endif
        </div>

        <div class="section">
            <p class="section-title">Cancelled Items</p>
            <table class="items">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th style="text-align:center">Qty</th>
                        <th style="text-align:right">Price</th>
                        <th style="text-align:right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($order->items as $item)
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
                        <td colspan="3" style="text-align:right; font-weight:normal; color:#78716c;">Subtotal</td>
                        <td style="text-align:right; font-weight:normal; color:#1c1917;">&euro;{{ number_format($order->itemsTotal(), 2) }}</td>
                    </tr>
                    @foreach($order->adjustments()->byType(\Vanilo\Adjustments\Models\AdjustmentTypeProxy::PROMOTION()) as $adjustment)
                        <tr>
                            <td colspan="3" style="text-align:right; font-weight:normal; color:#ea580c;">{{ $adjustment->title }}</td>
                            <td style="text-align:right; font-weight:normal; color:#ea580c;">-&euro;{{ number_format(abs($adjustment->amount), 2) }}</td>
                        </tr>
                    @endforeach
                    <tr>
                        <td colspan="3" style="text-align:right; font-weight:normal; color:#78716c;">Shipping</td>
                        <td style="text-align:right; font-weight:normal; color:#1c1917;">&euro;{{ number_format($order->adjustments()->byType(\Vanilo\Adjustments\Models\AdjustmentTypeProxy::SHIPPING())->total(true), 2) }}</td>
                    </tr>
                    <tr>
                        <td colspan="3" style="text-align:right; font-weight:normal; color:#78716c;">Tax</td>
                        <td style="text-align:right; font-weight:normal; color:#1c1917;">&euro;{{ number_format($order->adjustments()->byType(\Vanilo\Adjustments\Models\AdjustmentTypeProxy::TAX())->total(true), 2) }}</td>
                    </tr>
                    <tr>
                        <td colspan="3" style="text-align:right; font-weight:700; font-size:16px;">Order Total</td>
                        <td style="text-align:right; font-weight:700; font-size:16px;">&euro;{{ number_format($order->total(), 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="footer">
            <p>
                &copy;&nbsp;{{ date('Y') }}&nbsp;Business&nbsp;Labels. This is an automated notification.
            </p>
        </div>
    </div>
</div>
</body>
</html>
