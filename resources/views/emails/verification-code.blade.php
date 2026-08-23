<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Verify your email</title>
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
                Verify your email
              </h1>
              <p style="margin:0 0 28px; color:#4B5563; font-size:15px; line-height:1.5; text-align:center;">
                {{ $name ? 'Hi '.$name.', ' : 'Hello, ' }}use the code below to verify your account. This code expires in {{ $expiresInMinutes }} minutes.
              </p>

              <!-- Code box -->
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                <tr>
                  <td align="center">
                    <div style="background-color:#F5F8FC; border:1.5px solid #E5E7EB; border-radius:10px; padding:20px 32px; display:inline-block;">
                      <span style="color:#1B1B3A; font-size:36px; font-weight:700; letter-spacing:10px;">
                        {{ $code }}
                      </span>
                    </div>
                  </td>
                </tr>
              </table>

              <p style="margin:28px 0 0; color:#9CA3AF; font-size:13px; line-height:1.5; text-align:center;">
                Didn't request this code? You can safely ignore this email.
              </p>
            </td>
          </tr>

          <!-- Footer -->
          <tr>
            <td style="padding:20px 32px; border-top:1px solid #E5E7EB; text-align:center;">
              <p style="margin:0; color:#9CA3AF; font-size:12px;">
                &copy; {{ date('Y') }} Egliane Accounting Services. All rights reserved.
              </p>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>
