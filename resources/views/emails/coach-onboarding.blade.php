<!DOCTYPE html>
<html>
<head>
    <title>AC-VMIS Coach Account</title>
</head>
<body>
    <h1>Hello {{ $user->name }},</h1>
    <p>Your AC-VMIS coach account has been created by an administrator.</p>
    <p><strong>Email:</strong> {{ $user->email }}</p>
    <p>Use the secure activation link below to set your password and complete account setup.</p>
    <p>This link is intended only for you. If it expires, ask the administrator to resend your activation link.</p>
    <p><a href="{{ $activationUrl }}">Activate Coach Account</a></p>
    <p><a href="{{ $loginUrl }}">Open AC-VMIS Sign-In Page</a></p>
    <p>AC-VMIS website: <a href="{{ url('/') }}">{{ url('/') }}</a></p>
</body>
</html>
