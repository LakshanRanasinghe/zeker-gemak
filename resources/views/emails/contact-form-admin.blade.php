<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Message – {{ config('app.company.name') }}</title>
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

        .sender-card { background: #fff7ed; border: 1px solid #fed7aa; border-radius: 12px; padding: 24px; text-align: center; margin-bottom: 32px; }
        .sender-label { font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #a8a29e; margin-bottom: 8px; }
        .sender-email { font-size: 24px; font-weight: 800; color: #ea580c; margin: 0; word-break: break-all; text-decoration: none; }

        .message-section { border-top: 1px solid #f5f0eb; padding-top: 32px; }
        .message-label { font-size: 14px; color: #78716c; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 16px; }
        .message-box {
            background: #fafaf9; border-left: 4px solid #ea580c; padding: 20px; border-radius: 4px 12px 12px 4px;
            font-size: 16px; color: #1c1917; font-style: italic; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); white-space: pre-wrap;
        }

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
            <img src="{{ $message->embed(public_path('images/zeker-gemak-logo.png')) }}" alt="Zeker Gemak">
        </a>
    </div>

    <div class="card">
        <div class="header">
            <div class="header-inner">
                <div class="icon-badge">
                    <svg width="34" height="34" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M4 4H20C21.1 4 22 4.9 22 6V18C22 19.1 21.1 20 20 20H4C2.9 20 2 19.1 2 18V6C2 4.9 2.9 4 4 4Z" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M22 6L12 13L2" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <h1>New Message</h1>
                <p>You have received a new contact form submission</p>
            </div>
        </div>

        <div class="content">
            <div class="sender-card">
                <div class="sender-label">From Sender</div>
                <a href="mailto:{{ $data['email'] }}" class="sender-email">{{ $data['email'] }}</a>
            </div>

            <div class="message-section">
                <div class="message-label">Message Content</div>
                <div class="message-box">
                    "{{ $data['message'] }}"
                </div>
            </div>

            <div class="action-container">
                <a href="mailto:{{ $data['email'] }}" class="btn">Reply via Email</a>
            </div>
        </div>

        <div class="footer">
            <p>&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
        </div>
    </div>
</div>
</body>
</html>
