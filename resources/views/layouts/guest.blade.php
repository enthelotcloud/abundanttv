<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>{{ $title ?? 'Abundant TV' }}</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('fav.png') }}">
    <meta name="description" content="{{ $description ?? 'Abundant TV is a Christian online TV that broadcasts a variety of programs, including sermons, music, and talk shows.' }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body>
    <livewire:header />
    <main class="min-h-screen">
        {{ $slot }}
    </main>
    <livewire:footer />
    @livewireScripts
</body>
</html>
