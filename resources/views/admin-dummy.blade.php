@extends('layout.app')
@section('title', $pageTitle.' | APL Connect')
@section('content')
<div class="w-full max-w-none">
    <nav class="crud-breadcrumb mb-4" aria-label="Breadcrumb">
        <span>Admin</span><b>/</b><span>{{ $sectionTitle }}</span><b>/</b><span>{{ $pageTitle }}</span>
    </nav>
    <section class="card min-h-[360px]">
        <div class="flex items-start justify-between gap-4 border-b border-slate-100 pb-5">
            <div>
                <p class="theme-eyebrow text-xs font-bold uppercase tracking-[.14em]">{{ $sectionTitle }}</p>
                <h1 class="mt-2 text-2xl font-semibold text-slate-800">{{ $pageTitle }}</h1>
                <p class="mt-2 text-sm text-slate-500">This is a demonstration page for the sidebar navigation.</p>
            </div>
            <span class="theme-badge rounded-lg px-3 py-1.5 text-xs font-semibold">Demo</span>
        </div>
        <div class="grid min-h-[240px] place-items-center text-center">
            <div>
                <div class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-slate-100 text-xl text-slate-400">◇</div>
                <h2 class="mt-4 font-semibold text-slate-700">{{ $pageTitle }} content</h2>
                <p class="mt-1 text-sm text-slate-500">Replace this placeholder with the production module when ready.</p>
            </div>
        </div>
    </section>
</div>
@endsection
