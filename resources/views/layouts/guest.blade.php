<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
<link rel="icon" type="image/webp" href="{{ asset('logo.webp') }}">
<title>{{ config('app.name') }}</title>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
@vite(['resources/css/app.css','resources/js/app.js'])
</head>
<body class="auth-shell">
<div class="auth-intro"><p class="eyebrow">THE PLANNING DESK</p><h1>Every celebration<br>begins with a<br><em>conversation.</em></h1><p>A considered space for your clients, your team,<br>and everything that comes next.</p></div>
<div class="auth-card"><a href="{{ route('login') }}" class="brand mb-8 block"><x-application-logo /></a>{{ $slot }}</div>
</body>
</html>

