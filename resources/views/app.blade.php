<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    @php($reverbBrowser = app(\App\Support\ReverbBrowserConfiguration::class)->forRequest(request()))
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Local graduation ceremony operations for preparation, scanning, stage control, and audience presentation.">
        <meta name="theme-color" content="#20262e">
        <meta name="application-name" content="Graduation Stage System">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="Graduation Ops">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="gss-reverb-key" content="{{ $reverbBrowser['key'] }}">
        <meta name="gss-reverb-host" content="{{ $reverbBrowser['host'] }}">
        <meta name="gss-reverb-port" content="{{ $reverbBrowser['port'] }}">
        <meta name="gss-reverb-scheme" content="{{ $reverbBrowser['scheme'] }}">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="alternate icon" href="/favicon.ico">
        <link rel="apple-touch-icon" href="/icons/gss-192.png">
        <link rel="manifest" href="/manifest.webmanifest">

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.jsx'])
        @inertiaHead
    </head>
    <body>
        @inertia
    </body>
</html>
