@php
    /**
     * Spec-sheet PDF — generated on the fly when no PDF has been uploaded.
     * Layout is intentionally simple: logo, two data tables, contact footer.
     */
    $company = config('app.company');

    $labelFor = function (string $configKey, ?string $value): ?string {
        if (blank($value)) {
            return null;
        }

        return config("app.material_{$configKey}")[$value] ?? $value;
    };

    $certificateLabel = match ($material->certificate) {
        'BS5609' => __('BS5609 — Chemical transportation'),
        'FSC' => __('FSC certified'),
        'none', null, '' => null,
        default => $material->certificate,
    };

    // Table 1 — core material details.
    $details = array_filter([
        'Code' => $material->code,
        'Brand' => $labelFor('brands', $material->brand),
        'Status' => $material->status,
        'Print Method' => $labelFor('print_method', $material->print_method),
        'Base Material' => $labelFor('base_material', $material->base_material),
        'Finish' => $labelFor('finish', $material->finish),
        'Adhesive' => $labelFor('adhesive', $material->adhesive),
        'Supplier' => $labelFor('suppliers', $material->supplier),
        'Supplier Reference' => $material->supplier_reference,
        'Certificate' => $certificateLabel,
    ], fn ($v) => filled($v));

    // Table 2 — technical specifications, already resolved for the active locale.
    $specs = collect($specs ?? [])
        ->filter(fn ($spec) => filled($spec['label'] ?? null) && filled($spec['value'] ?? null))
        ->values();

    $logoPath = public_path('images/zeker-gemak-logo.png');
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ $title }} — {{ __('Spec Sheet') }}</title>
    <style>
        @page { margin: 130px 40px 110px 40px; }

        * { font-family: 'DejaVu Sans', sans-serif; }

        body { color: #3f3f46; font-size: 11px; line-height: 1.5; }

        .header {
            position: fixed;
            top: -100px;
            left: 0;
            right: 0;
            height: 80px;
            border-bottom: 3px solid #f59e0b;
            padding-bottom: 10px;
        }

        .header img { height: 42px; }

        .header .doc-type {
            float: right;
            text-align: right;
            color: #a1a1aa;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding-top: 14px;
        }

        .footer {
            position: fixed;
            bottom: -85px;
            left: 0;
            right: 0;
            height: 75px;
            border-top: 1px solid #e4e4e7;
            padding-top: 10px;
            font-size: 9px;
            color: #71717a;
        }

        .footer .contact { width: 70%; }
        .footer .contact td { padding: 1px 14px 1px 0; vertical-align: top; }
        .footer .contact .label { color: #f59e0b; font-weight: bold; width: 52px; }

        .footer .review {
            float: right;
            width: 28%;
            text-align: right;
        }

        .badge {
            display: inline-block;
            border: 1px solid #e4e4e7;
            border-radius: 6px;
            padding: 6px 10px;
            color: #3f3f46;
            text-decoration: none;
        }

        .badge .stars { color: #f59e0b; font-size: 11px; letter-spacing: 1px; }
        .badge .g { color: #4285F4; font-weight: bold; }

        h1 {
            font-size: 20px;
            color: #18181b;
            margin: 0 0 2px 0;
        }

        .subtitle { color: #71717a; font-size: 12px; margin-bottom: 18px; }

        .section-title {
            font-size: 13px;
            color: #18181b;
            font-weight: bold;
            margin: 22px 0 8px 0;
            padding-left: 8px;
            border-left: 3px solid #f59e0b;
        }

        table.data {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #e4e4e7;
        }

        table.data td {
            padding: 7px 12px;
            border-bottom: 1px solid #e4e4e7;
        }

        table.data tr:last-child td { border-bottom: none; }
        table.data tr:nth-child(even) { background: #fafafa; }

        table.data .key {
            width: 38%;
            color: #71717a;
        }

        table.data .val {
            color: #18181b;
            font-weight: bold;
        }

        .empty {
            color: #a1a1aa;
            font-style: italic;
            padding: 8px 2px;
        }
    </style>
</head>
<body>
    <div class="header">
        @if (file_exists($logoPath))
            <img src="{{ $logoPath }}" alt="{{ $company['name'] }}">
        @else
            <strong style="font-size:18px;color:#18181b;">{{ $company['name'] }}</strong>
        @endif
        <div class="doc-type">{{ __('Material Spec Sheet') }}</div>
    </div>

    <div class="footer">
        <table class="contact">
            <tr>
                <td class="label">{{ __('Phone') }}</td>
                <td>{{ $company['phone'] }}</td>
                <td class="label">{{ __('Email') }}</td>
                <td>{{ $company['email'] }}</td>
            </tr>
            <tr>
                <td class="label">{{ __('Address') }}</td>
                <td colspan="3">{{ $company['address'] }}</td>
            </tr>
            <tr>
                <td class="label">{{ __('Web') }}</td>
                <td colspan="3">{{ $company['website'] }}</td>
            </tr>
        </table>

        @if (filled($company['google_review_url']))
            <div class="review">
                <a href="{{ $company['google_review_url'] }}" class="badge">
                    <span class="g">G</span>oogle Reviews<br>
                    <span class="stars">★ ★ ★ ★ ★</span>
                </a>
            </div>
        @endif
    </div>

    <main>
        <h1>{{ $title }}</h1>
        @if (filled($subtitle))
            <div class="subtitle">{{ $subtitle }}</div>
        @endif

        <div class="section-title">{{ __('Material Details') }}</div>
        <table class="data">
            @foreach ($details as $key => $value)
                <tr>
                    <td class="key">{{ __($key) }}</td>
                    <td class="val">{{ $value }}</td>
                </tr>
            @endforeach
        </table>

        <div class="section-title">{{ __('Specifications') }}</div>
        @if ($specs->isNotEmpty())
            <table class="data">
                @foreach ($specs as $spec)
                    <tr>
                        <td class="key">{{ $spec['label'] }}</td>
                        <td class="val">{{ $spec['value'] }}</td>
                    </tr>
                @endforeach
            </table>
        @else
            <div class="empty">{{ __('No technical specifications recorded for this material.') }}</div>
        @endif
    </main>
</body>
</html>
