<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Pending Approval</title>
</head>
<body style="font-family: Arial, sans-serif; color: #222; line-height: 1.5;">
    <h2 style="margin-bottom: 8px;">Hello {{ $user->name }},</h2>

    <p style="margin-top: 0;">
        Your AC-VMIS account registration has been received and is currently
        <strong>pending administrative review</strong>.
    </p>

    <p>
        You will receive another notification once the review process has been completed.
    </p>

    <p>
        You can open the AC-VMIS website here:
        <a href="{{ url('/') }}" style="color: #034485;">{{ url('/') }}</a>
    </p>

    <p style="margin-top: 24px;">Thank you,<br>Asian College Varsity MIS</p>
</body>
</html>
