<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Laravel') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="font-sans antialiased bg-gray-100">
    @if($activeAlert)
        <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 text-center">
            <strong>{{ $activeAlert->title ?? 'Notice:' }}</strong> {{ $activeAlert->message }}
        </div>
    @endif
    
    <div class="min-h-screen">
        @yield('content')
    </div>
</body>
</html>