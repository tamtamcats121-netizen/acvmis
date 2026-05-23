<!DOCTYPE html>
<html>
<head>
    <title>Account Rejected</title>
</head>
<body>
    <h1>Hello {{ $user->name }},</h1>
    <p>Your account application has not been approved by the administrator.</p>

    @if($remarks)
        <p><strong>Remarks:</strong> {{ $remarks }}</p>
    @endif

    <p>If you believe this decision requires clarification, please contact the appropriate support office.</p>
    <p>Open AC-VMIS here: <a href="{{ url('/') }}">{{ url('/') }}</a></p>
</body>
</html>
