<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Callback Request – Business Labels</title>
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

        .content { padding: 40px; }

        .phone-card { background: #fff7ed; border: 1px solid #fed7aa; border-radius: 12px; padding: 24px; text-align: center; margin-bottom: 32px; }
        .phone-label { font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #a8a29e; margin-bottom: 8px; }
        .phone-number { font-size: 32px; font-weight: 800; color: #ea580c; margin: 0; text-decoration: none; }

        .details-grid { border-top: 1px solid #f5f0eb; padding-top: 24px; }
        .detail-row { display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #f5f5f4; }
        .detail-row:last-child { border-bottom: none; }
        .detail-label { font-size: 14px; color: #78716c; font-weight: 500; }
        .detail-value { font-size: 14px; color: #1c1917; font-weight: 600; }

        .action-container { margin-top: 40px; text-align: center; }
        .btn {
            display: inline-block; background: #ea580c;
            background: linear-gradient(135deg, #ea580c 0%, #f97316 100%); color: #ffffff !important;
            padding: 16px 32px; border-radius: 12px; text-decoration: none; font-weight: 700; font-size: 16px;
            box-shadow: 0 6px 20px rgba(234, 88, 12, 0.3);
        }

        .footer { background:#fafaf9; border-top:1px solid #e7e5e4; padding:24px 40px; text-align:center; }
        .footer p { font-size:12px; color:#a8a29e; line-height:1.7; margin:0; }

        @media (max-width:640px) {
            .outer { margin:0; padding:0 0 32px; }
            .card { border-radius:0; }
            .header { padding:40px 24px 36px; }
            .content { padding: 32px 24px; }
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
                        <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <h1>Callback Request</h1>
                <p>A new visitor has requested a callback</p>
            </div>
        </div>

        <div class="content">
            <div class="phone-card">
                <div class="phone-label">Direct Contact Number</div>
                <a href="tel:{{ $data['full_phone_number'] }}" class="phone-number">{{ $data['full_phone_number'] }}</a>
            </div>

            <div class="details-grid">
                <div class="detail-row">
                    <span class="detail-label">Country</span>
                    <span class="detail-value">{{ $data['country'] }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Country Code</span>
                    <span class="detail-value">{{ $data['country_code'] }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Dial Code</span>
                    <span class="detail-value">{{ $data['dial_code'] }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Raw Phone Number</span>
                    <span class="detail-value">{{ $data['phone_number'] }}</span>
                </div>
            </div>

            <div class="action-container">
                <a href="tel:{{ $data['full_phone_number'] }}" class="btn">Initiate Callback Now</a>
            </div>
        </div>

        <div class="footer">
            <p>&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
        </div>
    </div>
</div>
</body>
</html>
