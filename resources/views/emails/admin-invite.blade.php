<!DOCTYPE html>
<html>
<head>
    <title>AC-VMIS Admin Invitation</title>
</head>
<body>
    <h1>Administrator Invitation</h1>
    <p>Hello,</p>
    <p>{{ $sender->name }} has invited you to create an administrator account for AC-VMIS.</p>
    <p>This invitation is intended for <strong>{{ $invite->email }}</strong> and will expire on {{ $invite->expires_at->format('M d, Y h:i A') }}.</p>
    <p>
        <a href="{{ $acceptUrl }}">Accept the administrator invitation</a>
    </p>
    <p>AC-VMIS website: <a href="{{ url('/') }}">{{ url('/') }}</a></p>
    <p>If you were not expecting this invitation, you can safely ignore this email.</p>
</body>
</html>
