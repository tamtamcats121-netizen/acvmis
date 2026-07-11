<!DOCTYPE html>
<html>
<head>
    <title>Reset Your AC-VMIS Password</title>
</head>
<body>
    <h1>Hello {{ $user->name ?: $user->email }},</h1>
    <p>We received a request to reset the password for your AC-VMIS account.</p>
    <p>Use the secure link below to choose a new password. This link expires after 60 minutes.</p>
    <p><a href="{{ $resetUrl }}">Reset Password</a></p>
    <p>If you did not request this reset, you can ignore this email.</p>
    <p>AC-VMIS website: <a href="{{ url('/') }}">{{ url('/') }}</a></p>
</body>
</html>
