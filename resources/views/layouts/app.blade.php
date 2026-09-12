<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
<title>{{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css','resources/js/app.js'])
</head>
<body>
    <a href="#main" class="sr-only focus:not-sr-only">Skip to content</a>
    @include('layouts.navigation')
    <main id="main" class="crm-main">
        @isset($header)<header class="crm-heading">{{ $header }}</header>@endisset
        @if(session('success'))<div class="notice" role="status">{{ session('success') }}</div>@endif
        @if($errors->any())
            <div class="notice error" role="alert"><strong>Please check the following:</strong><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif
        {{ $slot }}
    </main>
    <footer class="crm-footer"><div class="footer-brand"><x-application-logo class="footer-logo" /><span>Thoughtfully planned, beautifully remembered</span></div> <span>Times shown in Asia/Kolkata</span></footer>
</body>
</html>

