<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name') }}</title>
        <link rel="icon" href="{{ asset('favicon.ico') }}?v=3" sizes="16x16 32x32 48x48 64x64">
        <link rel="icon" href="{{ asset('favicon.svg') }}?v=3" type="image/svg+xml" sizes="any">
        @vite('resources/js/app.js')
    </head>
    <body>
        <div id="app"></div>
    </body>
</html>
