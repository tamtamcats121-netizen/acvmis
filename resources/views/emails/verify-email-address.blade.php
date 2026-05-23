<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Your AC-VMIS Account</title>
</head>
<body style="margin:0;padding:0;background:#f4f7fb;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f7fb;padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:600px;background:#ffffff;border-radius:18px;overflow:hidden;border:1px solid #dbe7f3;">
                    <tr>
                        <td style="background:#034485;padding:28px 32px;color:#ffffff;">
                            <div style="font-size:12px;letter-spacing:0.18em;text-transform:uppercase;font-weight:700;opacity:0.82;">AC-VMIS</div>
                            <h1 style="margin:12px 0 0;font-size:28px;line-height:1.2;font-weight:800;">Verify Your AC-VMIS Account</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px;">
                            <p style="margin:0 0 16px;font-size:15px;line-height:1.7;">Hello,</p>
                            <p style="margin:0 0 16px;font-size:15px;line-height:1.7;">
                                Please verify your email address to complete your AC-VMIS account setup.
                            </p>
                            <p style="margin:0 0 28px;font-size:15px;line-height:1.7;">
                                Click the button below to confirm your email address.
                            </p>
                            <p style="margin:0 0 28px;">
                                <a href="{{ $verificationUrl }}" style="display:inline-block;background:#034485;color:#ffffff;text-decoration:none;padding:14px 22px;border-radius:999px;font-size:14px;font-weight:700;">
                                    Verify Email Address
                                </a>
                            </p>
                            <p style="margin:0 0 10px;font-size:13px;line-height:1.7;color:#475569;">
                                If the button above does not work, open this link:
                            </p>
                            <p style="margin:0 0 24px;font-size:13px;line-height:1.7;word-break:break-all;color:#034485;">
                                <a href="{{ $verificationUrl }}" style="color:#034485;">{{ $verificationUrl }}</a>
                            </p>
                            <p style="margin:0 0 24px;font-size:13px;line-height:1.7;color:#475569;">
                                AC-VMIS website:
                                <a href="{{ url('/') }}" style="color:#034485;">{{ url('/') }}</a>
                            </p>
                            <p style="margin:0;font-size:14px;line-height:1.7;color:#475569;">
                                If you did not request this, you may ignore this message.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
