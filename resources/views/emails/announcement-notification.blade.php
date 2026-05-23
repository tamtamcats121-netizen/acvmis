<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $notificationTitle }}</title>
</head>
<body style="font-family: Arial, sans-serif; color: #222; line-height: 1.5;">
    <h2 style="margin-bottom: 8px;">Hello {{ $user->name }},</h2>

    <p style="margin-top: 0;">
        You have received a new <strong>{{ strtolower($notificationTypeLabel) }}</strong> notice in AC-VMIS.
    </p>

    <div style="margin: 20px 0; padding: 16px; border: 1px solid #e2e8f0; border-radius: 10px; background: #f8fafc;">
        <h3 style="margin: 0 0 10px;">{{ $notificationTitle }}</h3>
        <p style="margin: 0;">{!! nl2br(e($notificationMessage)) !!}</p>
    </div>

    <p>
        Review the full details in your account:
        <a href="{{ $actionUrl }}" style="color: #0f766e;">Open AC-VMIS Notifications</a>
    </p>

    <p>
        AC-VMIS website:
        <a href="{{ url('/') }}" style="color: #034485;">{{ url('/') }}</a>
    </p>

    <p style="margin-top: 24px;">Thank you,<br>{{ config('app.name') }}</p>
</body>
</html>
