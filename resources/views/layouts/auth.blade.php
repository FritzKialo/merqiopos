<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token"
          content="{{ csrf_token() }}">
    <title>@yield('title', 'Merqio POS') | Merqio POS</title>
    @php $v = fn ($p) => asset($p) . '?v=' . (@filemtime(public_path($p)) ?: '1'); @endphp
    <!-- Fonts & Icons (self-hosted) -->
    <link rel="stylesheet" href="{{ asset('fonts/phosphor/phosphor-bold.css') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="{{ $v('css/main.css') }}">
    <link rel="stylesheet" href="{{ $v('css/auth.css') }}">
    @include('layouts.partials.brand-icons')
</head>
<body>
    <div class="auth-wrapper">
        @yield('content')
    </div>
    <script src="{{ asset('js/auth.js') }}"></script>
    @stack('scripts')
</body>
</html>