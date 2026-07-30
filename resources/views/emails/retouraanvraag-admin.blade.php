<!DOCTYPE html>
<html lang="nl">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1.0" />
  <title>Nieuwe retouraanvraag - {{ config('app.company.name') }}</title>
</head>
<body style="margin:0;padding:0;background-color:#f1f5f9;font-family:Arial,Helvetica,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f1f5f9;padding:40px 16px;">
    <tr><td align="center">
      <table width="720" cellpadding="0" cellspacing="0" style="max-width:720px;width:100%;">
        <tr>
          <td style="background-color:#0c2a3a;border-radius:16px 16px 0 0;padding:20px 32px;">
            <table width="100%" cellpadding="0" cellspacing="0">
              <tr>
                <td><img src="{{ config('app.url') }}/logo.png" alt="{{ config('app.company.name') }}" width="160" style="display:block;height:auto;max-height:34px;object-fit:contain;" /></td>
                <td align="right"><span style="font-size:11px;color:#64748b;letter-spacing:0.06em;text-transform:uppercase;">Admin Notification</span></td>
              </tr>
            </table>
          </td>
        </tr>
        <tr>
          <td style="background-color:#f08500;padding:20px 32px;">
            <p style="margin:0;font-size:20px;font-weight:700;color:#ffffff;">Nieuwe retouraanvraag</p>
            <p style="margin:5px 0 0;font-size:13px;color:#fff3e0;line-height:1.5;">RMA nummer: {{ $rmaNumber }}</p>
          </td>
        </tr>
        <tr>
          <td style="background-color:#ffffff;padding:32px;">
            @php
              $contactRows = [
                ['label' => 'Naam', 'value' => $data['name'] ?? ''],
                ['label' => 'E-mail', 'value' => $data['email'] ?? ''],
                ['label' => 'Telefoon', 'value' => $data['phone'] ?? ''],
                ['label' => 'Organisatie', 'value' => $data['organisation'] ?? ''],
                ['label' => 'Adres', 'value' => $data['address'] ?? ''],
                ['label' => 'Postcode', 'value' => $data['postcode'] ?? ''],
                ['label' => 'Plaats', 'value' => $data['city'] ?? ''],
              ];
              $contactRows = array_filter($contactRows, fn ($row) => $row['value'] !== '' && $row['value'] !== null);
            @endphp

            <p style="margin:0 0 10px;font-size:14px;font-weight:700;color:#0f172a;">Klantgegevens</p>
            <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e2e8f0;border-radius:10px;overflow:hidden;border-collapse:separate;border-spacing:0;">
              @foreach($contactRows as $index => $row)
                <tr style="background-color:{{ $index % 2 === 0 ? '#f8fafc' : '#ffffff' }};">
                  <td style="padding:12px 16px;border-bottom:1px solid #e2e8f0;width:34%;vertical-align:top;"><span style="font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:0.06em;">{{ $row['label'] }}</span></td>
                  <td style="padding:12px 16px;border-bottom:1px solid #e2e8f0;vertical-align:top;"><span style="font-size:14px;color:#0f172a;">{{ $row['value'] }}</span></td>
                </tr>
              @endforeach
            </table>

            <p style="margin:24px 0 10px;font-size:14px;font-weight:700;color:#0f172a;">Algemene informatie</p>
            <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e2e8f0;border-radius:10px;overflow:hidden;border-collapse:separate;border-spacing:0;">
              <tr style="background-color:#f8fafc;">
                <td style="padding:12px 16px;border-bottom:1px solid #e2e8f0;width:34%;vertical-align:top;"><span style="font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:0.06em;">Redenen</span></td>
                <td style="padding:12px 16px;border-bottom:1px solid #e2e8f0;vertical-align:top;"><span style="font-size:14px;color:#0f172a;">{{ implode(', ', $data['generalReasons'] ?? []) ?: '-' }}</span></td>
              </tr>
              <tr>
                <td style="padding:12px 16px;border-bottom:1px solid #e2e8f0;vertical-align:top;"><span style="font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:0.06em;">Onderwerp</span></td>
                <td style="padding:12px 16px;border-bottom:1px solid #e2e8f0;vertical-align:top;"><span style="font-size:14px;color:#0f172a;">{{ $data['subject'] ?? '-' }}</span></td>
              </tr>
              <tr style="background-color:#f8fafc;">
                <td style="padding:12px 16px;vertical-align:top;"><span style="font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:0.06em;">Bericht</span></td>
                <td style="padding:12px 16px;vertical-align:top;"><span style="font-size:14px;color:#0f172a;line-height:1.6;">{{ $data['message'] ?? '-' }}</span></td>
              </tr>
            </table>

            <p style="margin:24px 0 10px;font-size:14px;font-weight:700;color:#0f172a;">Producten</p>
            <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e2e8f0;border-radius:10px;overflow:hidden;border-collapse:collapse;">
              <tr style="background-color:#0c2a3a;">
                <th align="left" style="padding:10px;font-size:11px;color:#ffffff;">Product</th>
                <th align="left" style="padding:10px;font-size:11px;color:#ffffff;">SKU</th>
                <th align="left" style="padding:10px;font-size:11px;color:#ffffff;">Aantal</th>
                <th align="left" style="padding:10px;font-size:11px;color:#ffffff;">Factuur</th>
                <th align="left" style="padding:10px;font-size:11px;color:#ffffff;">Reden / probleem</th>
              </tr>
              @forelse($products as $product)
                <tr>
                  <td style="padding:10px;border-top:1px solid #e2e8f0;font-size:13px;color:#0f172a;vertical-align:top;">{{ $product['name'] ?: '-' }}</td>
                  <td style="padding:10px;border-top:1px solid #e2e8f0;font-size:13px;color:#0f172a;vertical-align:top;">{{ $product['sku'] ?: '-' }}</td>
                  <td style="padding:10px;border-top:1px solid #e2e8f0;font-size:13px;color:#0f172a;vertical-align:top;">{{ $product['quantity'] ?: '-' }}</td>
                  <td style="padding:10px;border-top:1px solid #e2e8f0;font-size:13px;color:#0f172a;vertical-align:top;">{{ $product['invoice_number'] ?: '-' }}<br />{{ $product['invoice_date'] ?: '-' }}</td>
                  <td style="padding:10px;border-top:1px solid #e2e8f0;font-size:13px;color:#0f172a;line-height:1.5;vertical-align:top;">
                    {{ implode(', ', $product['reasons'] ?? []) ?: '-' }}<br />
                    {{ $product['problem'] ?: '-' }}<br />
                    {{ $product['notes'] ?: '' }}
                  </td>
                </tr>
              @empty
                <tr><td colspan="5" style="padding:12px;font-size:13px;color:#64748b;">Geen productdetails opgegeven.</td></tr>
              @endforelse
            </table>
          </td>
        </tr>
        <tr>
          <td style="background-color:#0c2a3a;border-radius:0 0 16px 16px;padding:20px 32px;">
            <p style="margin:0;font-size:13px;font-weight:600;color:#f1f5f9;">{{ config('app.company.name') }}</p>
          </td>
        </tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
