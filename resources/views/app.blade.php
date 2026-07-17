<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#004479">
        <link rel="manifest" href="/manifest.webmanifest">
        <link rel="apple-touch-icon" href="/favicon.ico">
        <title>{{ config('app.name', 'Cloud API CC') }}</title>
        <style>html, body { height: 100%; margin: 0; } #root { height: 100%; }</style>
        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/main.tsx'])
    </head>
    <body>
        <div id="root"></div>
    </body>
</html>
