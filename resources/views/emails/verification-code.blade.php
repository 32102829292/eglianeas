<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Egliane Verification Code</title>
</head>
<body style="margin:0;padding:0;background-color:#F5F8FC;font-family:Arial,Helvetica,sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#F5F8FC;padding:32px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background-color:#FFFFFF;border-radius:12px;overflow:hidden;box-shadow:0 4px 16px rgba(27,27,58,0.08);">
                    <tr>
                        <td style="background-color:#1B1B3A;padding:28px 32px;text-align:center;">
                            <div style="font-size:28px;font-weight:900;color:#5AB3F0;letter-spacing:1px;">E</div>
                            <div style="font-size:18px;font-weight:700;color:#FFFFFF;margin-top:4px;">Egliane Accounting Services</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px;">
                            <h1 style="margin:0 0 12px;font-size:20px;color:#1B1B3A;">Verify your account</h1>
                            <p style="margin:0 0 20px;font-size:14px;line-height:1.6;color:#5a5a7a;">
                                {{ $name ? 'Hi '.$name.', ' : 'Hello' }} thanks for signing up with Egliane Accounting Services.
                                Use the 6-digit code below to activate your account. It expires in 15 minutes.
                            </p>
                            <div style="background-color:#F5F8FC;border:2px dashed #5AB3F0;border-radius:8px;padding:24px;text-align:center;font-size:36px;font-weight:800;letter-spacing:12px;color:#1B1B3A;">
                                {{ $code }}
                            </div>
                            <p style="margin:20px 0 0;font-size:12px;color:#8a8aa5;">If you did not sign up for an Egliane account, you can safely ignore this email.</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color:#1B1B3A;padding:16px 32px;text-align:center;font-size:12px;color:#9aa7c4;">
                            &copy; {{ date('Y') }} Egliane Accounting Services. All rights reserved.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
