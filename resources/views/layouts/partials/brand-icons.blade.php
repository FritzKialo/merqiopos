@php $iconV = @filemtime(public_path('images/merqio-mark.svg')) ?: '1'; @endphp
<link rel="icon" type="image/svg+xml" href="{{ asset('images/merqio-mark.svg') }}?v={{ $iconV }}">
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32.png') }}?v={{ $iconV }}">
<link rel="shortcut icon" href="{{ asset('favicon.ico') }}?v={{ $iconV }}">
<link rel="apple-touch-icon" href="{{ asset('icons/apple-touch-icon.png') }}?v={{ $iconV }}">
