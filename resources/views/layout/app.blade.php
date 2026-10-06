<!DOCTYPE html>

<html lang="en" data-table-page-length="{{ config('app.table_page_length', 50) }}">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'APL Connect')</title>

    @viteReactRefresh
    @vite([
        'resources/css/app.css',
        'resources/js/app.js',
        'resources/js/react/main.jsx'
    ])

    @stack('styles')
    @auth<link rel="stylesheet" href="{{ asset('css/admin-corporate.css') }}?v={{ filemtime(public_path('css/admin-corporate.css')) }}">@endauth

</head>

@if($adminTheme ?? null)<style>:root{--theme-primary:{{ $adminTheme->primary_color }};--theme-secondary:{{ $adminTheme->secondary_color }};--theme-navbar-bg:{{ $adminTheme->default_mode==='dark'?$adminTheme->dark_navbar_bg:$adminTheme->light_navbar_bg }};--theme-sidebar-bg:{{ $adminTheme->default_mode==='dark'?$adminTheme->dark_sidebar_bg:$adminTheme->light_sidebar_bg }};--theme-navbar-text:{{ $adminTheme->default_mode==='dark'?$adminTheme->dark_navbar_text:$adminTheme->light_navbar_text }};--theme-sidebar-text:{{ $adminTheme->default_mode==='dark'?$adminTheme->dark_sidebar_text:$adminTheme->light_sidebar_text }};--theme-canvas:{{ $adminTheme->canvas_bg }};--theme-card:{{ $adminTheme->card_bg }};}</style>@endif
<body class="min-h-screen bg-[#f4f7fb] text-slate-900 antialiased {{ request()->boolean('modal')?'modal-form-document':'' }}" data-theme-mode="{{ ($adminTheme??null)?->default_mode??'light' }}">
@auth
@if(request()->boolean('modal'))
<main class="modal-form-page">@yield('content')</main>
@else
<div class="min-h-screen lg:flex">
 <x-admin-sidebar />
 <div id="app-shell-content" class="min-w-0 flex-1 transition-[margin] duration-300 lg:ml-72">
  <x-admin-header />
  <main class="w-full p-4 sm:p-6 lg:p-8">@if(session('success'))<div class="mb-6 flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-medium text-emerald-800"><span class="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-emerald-600 text-white">✓</span>{{ session('success') }}</div>@endif @yield('content')</main>
  <x-app-footer />
 </div>
</div>
<template data-form-modal-template>
 <div class="global-form-modal" data-form-modal-instance aria-hidden="true">
  <div class="global-form-backdrop" data-global-form-close></div>
  <section class="global-form-dialog" role="dialog" aria-modal="true">
   <header><div><p>APL Connect</p><h2 data-global-form-title>Manage record</h2></div><button type="button" data-global-form-close aria-label="Close form">×</button></header>
   <div class="global-form-loading" data-global-form-loading><span></span><p>Loading form…</p></div>
   <div class="global-form-content" data-global-form-content></div>
  </section>
 </div>
</template>
@endif
@else
<main>@yield('content')</main>
<x-app-footer />
@endauth


    @stack('scripts')
    <script defer src="{{ asset('vendor/tesseract/tesseract.min.js') }}"></script>
    <script defer src="{{ asset('js/distributor-document-ocr.js') }}?v={{ filemtime(public_path('js/distributor-document-ocr.js')) }}"></script>
    <script defer src="{{ asset('js/consumption-period.js') }}?v={{ filemtime(public_path('js/consumption-period.js')) }}"></script>
    @auth<script defer src="{{ asset('js/admin-shell.js') }}?v={{ filemtime(public_path('js/admin-shell.js')) }}"></script>@endauth

</body>

</html>
