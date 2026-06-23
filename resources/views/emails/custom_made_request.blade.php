<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1.0" />
  <title>Custom-made Label Request — Businesslabels</title>
</head>
<body style="margin:0;padding:0;background-color:#f1f5f9;font-family:Arial,Helvetica,sans-serif;">

  <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f1f5f9;padding:40px 16px;">
    <tr><td align="center">
      <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">

        <!-- Header: navy bar with logo -->
        <tr>
          <td style="background-color:#0c2a3a;border-radius:16px 16px 0 0;padding:20px 32px;">
            <table width="100%" cellpadding="0" cellspacing="0">
              <tr>
                <td style="vertical-align:middle;">
                  <img src="{{ config('app.url') }}/logo.png" alt="Businesslabels" width="160"
                    style="display:block;height:auto;max-height:34px;object-fit:contain;" />
                </td>
                <td align="right" style="vertical-align:middle;">
                  <span style="font-size:11px;color:#64748b;letter-spacing:0.06em;text-transform:uppercase;">
                    Admin Notification
                  </span>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- Orange title stripe -->
        <tr>
          <td style="background-color:#f08500;padding:20px 32px;">
            <p style="margin:0;font-size:20px;font-weight:700;color:#ffffff;letter-spacing:-0.01em;">
              New Custom-made Label Request
            </p>
            <p style="margin:5px 0 0;font-size:13px;color:#fff3e0;line-height:1.5;">
              A customer has requested a custom-made label via the Customization form.
            </p>
          </td>
        </tr>

        <!-- White body -->
        <tr>
          <td style="background-color:#ffffff;padding:32px;">

            <!-- Info grid -->
            <table width="100%" cellpadding="0" cellspacing="0"
              style="border:1px solid #e2e8f0;border-radius:10px;overflow:hidden;border-collapse:separate;border-spacing:0;">
              
              @php
                $infoRows = [
                  ['label' => 'Name', 'value' => $data['name'] ?? ''],
                  ['label' => 'Email', 'value' => $data['email'] ?? ''],
                  ['label' => 'Phone', 'value' => $data['phone'] ?? ''],
                  ['label' => 'Company', 'value' => $data['company'] ?? ''],
                  ['label' => 'Shape', 'value' => $data['shape'] ?? ''],
                  ['label' => 'Dimensions', 'value' => $data['dimensions'] ?? ''],
                  ['label' => 'Printer Model', 'value' => $data['printer'] ?? ''],
                  ['label' => 'Material', 'value' => $data['material'] ?? ''],
                  ['label' => 'Comments', 'value' => $data['comments'] ?? ''],
                ];
                $infoRows = array_filter($infoRows, fn($row) => !empty($row['value']));
                $i = 0;
              @endphp

              @foreach($infoRows as $row)
              <tr style="background-color:{{ $i % 2 === 0 ? '#f8fafc' : '#ffffff' }};">
                <td style="padding:13px 20px;border-bottom:1px solid #e2e8f0;width:36%;vertical-align:top;">
                  <span style="font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:0.06em;">{{ $row['label'] }}</span>
                </td>
                <td style="padding:13px 20px;border-bottom:1px solid #e2e8f0;vertical-align:top;">
                  <span style="font-size:14px;font-weight:600;color:#0f172a;">{{ $row['value'] }}</span>
                </td>
              </tr>
              @php $i++; @endphp
              @endforeach
            </table>

            <!-- Reply callout -->
            <table width="100%" cellpadding="0" cellspacing="0"
              style="margin-top:24px;border-left:4px solid #f08500;background-color:#fff7ed;border-radius:0 8px 8px 0;">
              <tr>
                <td style="padding:16px 20px;">
                  <p style="margin:0;font-size:12px;font-weight:700;color:#c2410c;text-transform:uppercase;letter-spacing:0.05em;">
                    Next Step
                  </p>
                  <p style="margin:6px 0 0;font-size:13px;color:#7c2d12;line-height:1.6;">
                    Review the requirements and reply to the customer at 
                    <a href="mailto:{{ $data['email'] ?? '' }}" style="color:#f08500;font-weight:600;">{{ $data['email'] ?? '' }}</a> to offer advice or provide a quote.
                  </p>
                </td>
              </tr>
            </table>

          </td>
        </tr>

        <!-- Footer: navy bar -->
        <tr>
          <td style="background-color:#0c2a3a;border-radius:0 0 16px 16px;padding:20px 32px;">
            <table width="100%" cellpadding="0" cellspacing="0">
              <tr>
                <td style="vertical-align:top;">
                  <p style="margin:0;font-size:13px;font-weight:600;color:#f1f5f9;">Businesslabels</p>
                  <p style="margin:5px 0 0;font-size:12px;color:#475569;line-height:1.6;">
                    <a href="mailto:verkoop@businesslabels.nl"
                      style="color:#64748b;text-decoration:none;">verkoop@businesslabels.nl</a>
                    &nbsp;&middot;&nbsp;
                    <a href="tel:+31318590465"
                      style="color:#64748b;text-decoration:none;">+31 (0)318 590 465</a>
                  </p>
                </td>
                <td align="right" style="vertical-align:top;">
                  <p style="margin:0;font-size:11px;color:#334155;line-height:1.5;">
                    Auto-generated by<br/>businesslabels.nl
                  </p>
                </td>
              </tr>
            </table>
          </td>
        </tr>

      </table>
    </td></tr>
  </table>

</body>
</html>
