<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Your Password – Business Labels</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background-color: #FFF7ED;
            color: #1c1917;
        }

        /* ── Outer wrapper ──────────────────────── */
        .outer {
            max-width: 620px;
            margin: 40px auto;
            padding: 0 16px 48px;
        }

        /* ── Logo strip ─────────────────────────── */
        .logo-strip {
            text-align: center;
            padding: 0 0 32px;
        }

        .logo-strip a img {
            height: 52px;
            width: auto;
        }

        /* ── Card ───────────────────────────────── */
        .card {
            background: #ffffff;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 8px 40px rgba(234, 88, 12, 0.10), 0 2px 8px rgba(0,0,0,0.06);
        }

        /* ── Header ─────────────────────────────── */
        .header {
            background-color: #F08B01;
            background: linear-gradient(145deg, #ea580c 0%, #f97316 55%, #fb923c 100%);
            padding: 48px 40px 40px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        /* subtle radial glow overlay */
        .header::after {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(ellipse at 80% 20%, rgba(255,255,255,0.18) 0%, transparent 55%),
                        radial-gradient(ellipse at 15% 85%, rgba(0,0,0,0.08) 0%, transparent 45%);
            pointer-events: none;
        }

        /* decorative circles */
        .header::before {
            content: '';
            position: absolute;
            width: 280px;
            height: 280px;
            border-radius: 50%;
            background: rgba(255,255,255,0.07);
            top: -100px;
            right: -80px;
            pointer-events: none;
        }

        .header-inner {
            position: relative;
            z-index: 1;
        }

        /* lock badge */
        .lock-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 76px;
            height: 76px;
            background: rgba(255,255,255,0.20);
            border: 2px solid rgba(255,255,255,0.35);
            border-radius: 50%;
            margin-bottom: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        }

        .header h1 {
            color: #ffffff;
            font-size: 28px;
            font-weight: 800;
            letter-spacing: -0.5px;
            margin-bottom: 8px;
            text-shadow: 0 1px 3px rgba(0,0,0,0.15);
        }

        .header p {
            color: rgba(255,255,255,0.88);
            font-size: 15px;
            line-height: 1.5;
        }

        /* ── Body ───────────────────────────────── */
        .body {
            padding: 44px 44px 36px;
        }

        .greeting {
            font-size: 17px;
            font-weight: 700;
            color: #1c1917;
            margin-bottom: 12px;
        }

        .intro {
            font-size: 15px;
            color: #57534e;
            line-height: 1.7;
            margin-bottom: 36px;
        }

        /* ── CTA ────────────────────────────────── */
        .cta-wrap {
            text-align: center;
            margin-bottom: 36px;
        }

        .cta-btn {
            display: inline-block;
            background: #ea580c;
            background: linear-gradient(135deg, #ea580c 0%, #f97316 100%);
            color: #ffffff !important;
            text-decoration: none;
            font-size: 16px;
            font-weight: 700;
            letter-spacing: 0.2px;
            padding: 17px 48px;
            border-radius: 12px;
            box-shadow: 0 6px 20px rgba(234, 88, 12, 0.38);
        }

        /* ── Expiry notice ───────────────────────── */
        .notice {
            background: #fff7ed;
            border: 1px solid #fed7aa;
            border-left: 4px solid #f97316;
            border-radius: 10px;
            padding: 16px 20px;
            margin-bottom: 32px;
        }

        .notice p {
            font-size: 13.5px;
            color: #78350f;
            line-height: 1.6;
        }

        .notice strong { color: #9a3412; }

        /* ── Divider ─────────────────────────────── */
        hr.divider {
            border: none;
            border-top: 1px solid #f5f5f4;
            margin: 28px 0;
        }

        /* ── Fallback URL ────────────────────────── */
        .fallback {
            font-size: 12.5px;
            color: #a8a29e;
            line-height: 1.65;
        }

        .fallback a {
            color: #ea580c;
            word-break: break-all;
            text-decoration: none;
        }

        /* ── Footer strip ────────────────────────── */
        .footer {
            background: #fafaf9;
            border-top: 1px solid #e7e5e4;
            padding: 24px 44px;
            text-align: center;
        }

        .footer p {
            font-size: 12px;
            color: #a8a29e;
            line-height: 1.7;
            margin: 0;
        }

        .footer a {
            color: #78716c;
            text-decoration: none;
        }

        .footer a:hover { text-decoration: underline; }

        /* orange dot separator */
        .footer .dot {
            display: inline-block;
            width: 4px;
            height: 4px;
            background: #fb923c;
            border-radius: 50%;
            vertical-align: middle;
            margin: 0 8px;
        }

        /* ── Responsive ─────────────────────────── */
        @media (max-width: 640px) {
            .outer { margin: 0; padding: 0 0 32px; }
            .card { border-radius: 0; }
            .header { padding: 40px 24px 36px; }
            .body { padding: 32px 24px 28px; }
            .footer { padding: 20px 24px; }
            .cta-btn { padding: 15px 32px; font-size: 15px; display: block; }
        }
    </style>
</head>

<body>
<div class="outer">

    {{-- Logo strip --}}
    <div class="logo-strip">
        <a href="{{ $appUrl }}" target="_blank">
            <img src="{{ $message->embed(public_path('images/bbnl-logo.png')) }}" alt="Business Labels">
        </a>
    </div>

    <div class="card">

        {{-- ── Header ── --}}
        <div class="header">
            <div class="header-inner">
                <div class="lock-badge">
                    {{-- Simple lock icon --}}
                    <svg width="34" height="34" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M7 10.5V7.5C7 4.74 9.24 2.5 12 2.5C14.76 2.5 17 4.74 17 7.5V10.5"
                              stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <rect x="3.5" y="10.5" width="17" height="11" rx="3"
                              fill="rgba(255,255,255,0.15)" stroke="white" stroke-width="2"/>
                        <circle cx="12" cy="16" r="1.5" fill="white"/>
                        <line x1="12" y1="17.5" x2="12" y2="19.5"
                              stroke="white" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </div>

                <h1>Reset Your Password</h1>
                <p>We received a request to reset the password<br>for your Business Labels account.</p>
            </div>
        </div>

        {{-- ── Body ── --}}
        <div class="body">

            <p class="greeting">Hi {{ $user->name ?? 'there' }},</p>

            <p class="intro">
                Someone (hopefully you!) requested a password reset for your account.
                Click the button below to set a new password. If you didn't make this
                request, you can safely ignore this email — your account remains secure
                and nothing has changed.
            </p>

            {{-- CTA button --}}
            <div class="cta-wrap">
                <a href="{{ $resetUrl }}" class="cta-btn" target="_blank">
                    &#128274;&nbsp; Reset My Password
                </a>
            </div>

            {{-- Expiry notice --}}
            <div class="notice">
                <p>
                    <strong>&#9201; This link expires in 60&nbsp;minutes.</strong>
                    After that you will need to request a new link from the
                    forgot&nbsp;password page.
                </p>
            </div>

            <hr class="divider">

            {{-- Fallback URL --}}
            <p class="fallback">
                If the button above doesn't work in your email client, copy and paste
                this link directly into your browser:<br>
                <a href="{{ $resetUrl }}">{{ $resetUrl }}</a>
            </p>

        </div>

        {{-- ── Footer ── --}}
        <div class="footer">
            <p>
                &copy;&nbsp;{{ date('Y') }}&nbsp;Business&nbsp;Labels. All rights reserved.
                <span class="dot"></span>
                <a href="{{ $appUrl }}" target="_blank">businesslabels.nl</a>
                <span class="dot"></span>
                <a href="mailto:verkoop@businesslabels.nl">verkoop@businesslabels.nl</a>
            </p>
        </div>

    </div>
</div>
</body>
</html>
