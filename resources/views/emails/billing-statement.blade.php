<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Billing Statement</title>
</head>
<body style="margin:0; padding:0; background-color:#F5F8FC; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#F5F8FC; padding:32px 0;">
    <tr>
      <td align="center">
        <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="background-color:#FFFFFF; border-radius:12px; overflow:hidden; max-width:600px; width:100%;">

          <!-- Header with logo -->
          <tr>
            <td style="background-color:#1B1B3A; padding:28px 32px; text-align:center;">
              <img src="{{ asset('pwa-icons/logo-header.png') }}" alt="Egliane Accounting Services" width="48" height="48" style="display:block; margin:0 auto 10px; border:0;">
              <span style="color:#FFFFFF; font-size:18px; font-weight:700;">Egliane Accounting Services</span>
            </td>
          </tr>

          <!-- Body -->
          <tr>
            <td style="padding:40px 32px;">
              <h1 style="margin:0 0 12px; color:#1B1B3A; font-size:22px; font-weight:700; text-align:center;">
                Your billing statement is ready
              </h1>
              <p style="margin:0 0 28px; color:#4B5563; font-size:15px; line-height:1.5; text-align:center;">
                Hi {{ $clientName }}, your billing statement for <strong>{{ $billing->period_label }}</strong> is ready and attached to this email as a PDF.
              </p>

              <!-- Total box -->
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                <tr>
                  <td align="center">
                    <div style="background-color:#F5F8FC; border:1.5px solid #E5E7EB; border-radius:10px; padding:20px 32px;">
                      <div style="color:#6B7280; font-size:12px; text-transform:uppercase; letter-spacing:1px;">Total amount due</div>
                      <span style="color:#1B1B3A; font-size:32px; font-weight:700;">{{ $totalLabel }}</span>
                      @if ($billing->isPaid())
                        <div style="color:#27AE60; font-size:13px; font-weight:700; margin-top:6px;">PAID &middot; {{ $billing->paid_at?->format('F j, Y') }}</div>
                      @elseif ($dueLabel)
                        <div style="color:#B45309; font-size:13px; margin-top:6px;">Due on {{ $dueLabel }}</div>
                      @endif
                    </div>
                  </td>
                </tr>
              </table>

              <p style="margin:28px 0 0; color:#4B5563; font-size:14px; line-height:1.5; text-align:center;">
                If you have any questions about this statement, just reply to this email or message us.
              </p>
            </td>
          </tr>

          <!-- Footer -->
          <tr>
            <td style="padding:20px 32px; border-top:1px solid #E5E7EB; text-align:center;">
              <p style="margin:0; color:#9CA3AF; font-size:12px;">
                HARRIS EGLIANE, CPA &middot; &copy; {{ date('Y') }} Egliane Accounting Services. All rights reserved.
              </p>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>
