<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"  @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#034485">
        <meta name="application-name" content="{{ config('app.name', 'AC-VMIS') }}">
        <meta property="og:site_name" content="{{ config('app.name', 'AC-VMIS') }}">
        <meta property="og:type" content="website">
        <meta property="og:url" content="{{ url()->current() }}">
        <meta property="og:image" content="{{ asset('images/ac-vmis.logo.png') }}">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:image" content="{{ asset('images/ac-vmis.logo.png') }}">
        <link rel="canonical" href="{{ url()->current() }}">

        <title inertia>{{ config('app.name', 'AC-VMIS') }}</title>

        <link rel="icon" href="/images/ac-vmis.logo.png" type="image/png">
        <link rel="icon" href="/images/ac-vmis.logo.png" sizes="any">
        <link rel="apple-touch-icon" href="/images/ac-vmis.logo.png">


        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

        @vite(['resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
