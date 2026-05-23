<!DOCTYPE html>
<html>
<head>
    <title>Account Approved</title>
</head>
<body>
    <h1>Hello {{ $user->name }},</h1>
    <p>Your account has been approved by the administrator. You may now sign in and access the system.</p>
    <p>
        <a href="{{ url('/Login') }}">Sign in to AC-VMIS</a>
    </p>
    <p>If the link above does not work, open this website: <a href="{{ url('/') }}">{{ url('/') }}</a></p>
</body>
</html>
