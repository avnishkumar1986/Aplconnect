@extends('layout.app')
@section('title', 'Admin Theme')
@section('content')
    <x-page-header title="Admin Theme" description="Choose a preset or create a custom corporate color palette."><span
            class="crud-breadcrumb">Settings <b>/</b> Admin Theme</span></x-page-header>
    @php
        $presets = [
            [
                'key' => 'default-indigo',
                'label' => 'Default Indigo',
                'primary' => '#6366f1',
                'secondary' => '#8b5cf6',
                'sidebar' => '#1e1e2d',
                'canvas' => '#f4f5fb',
            ],
            [
                'key' => 'ocean-blue',
                'label' => 'Ocean Blue',
                'primary' => '#2563eb',
                'secondary' => '#0ea5e9',
                'sidebar' => '#15243b',
                'canvas' => '#f1f6fc',
            ],
            [
                'key' => 'emerald',
                'label' => 'Emerald',
                'primary' => '#059669',
                'secondary' => '#10b981',
                'sidebar' => '#162333',
                'canvas' => '#f0f8f5',
            ],
            [
                'key' => 'sunset-orange',
                'label' => 'Sunset Orange',
                'primary' => '#ea580c',
                'secondary' => '#f59e0b',
                'sidebar' => '#28221e',
                'canvas' => '#fff6ef',
            ],
            [
                'key' => 'rose',
                'label' => 'Rose',
                'primary' => '#e11d48',
                'secondary' => '#fb4565',
                'sidebar' => '#211d2c',
                'canvas' => '#fff2f5',
            ],
            [
                'key' => 'purple-reign',
                'label' => 'Purple Reign',
                'primary' => '#7c3aed',
                'secondary' => '#a855f7',
                'sidebar' => '#211b34',
                'canvas' => '#f7f3ff',
            ],
            [
                'key' => 'teal-cyan',
                'label' => 'Teal Cyan',
                'primary' => '#159aa6',
                'secondary' => '#31b7c3',
                'sidebar' => '#111b2e',
                'canvas' => '#f3f6fa',
            ],
            [
                'key' => 'slate-professional',
                'label' => 'Slate Professional',
                'primary' => '#475569',
                'secondary' => '#64748b',
                'sidebar' => '#172033',
                'canvas' => '#f3f5f7',
            ],
            [
                'key' => 'amber-gold',
                'label' => 'Amber Gold',
                'primary' => '#d97706',
                'secondary' => '#fbbf24',
                'sidebar' => '#29251d',
                'canvas' => '#fff8eb',
            ],
            [
                'key' => 'midnight-navy',
                'label' => 'Midnight Navy',
                'primary' => '#24456d',
                'secondary' => '#3b82f6',
                'sidebar' => '#101b2e',
                'canvas' => '#f1f5f9',
            ],
            [
                'key' => 'light-clean',
                'label' => 'Light Clean',
                'primary' => '#6366f1',
                'secondary' => '#8b5cf6',
                'sidebar' => '#f8fafc',
                'canvas' => '#ffffff',
            ],
            [
                'key' => 'light-warm',
                'label' => 'Light Warm',
                'primary' => '#ea580c',
                'secondary' => '#f59e0b',
                'sidebar' => '#faf8f5',
                'canvas' => '#fffaf5',
            ],
        ];
        $names = [
            'preset',
            'default_mode',
            'primary_color',
            'secondary_color',
            'light_navbar_bg',
            'light_sidebar_bg',
            'light_navbar_text',
            'light_sidebar_text',
            'dark_navbar_bg',
            'dark_sidebar_bg',
            'dark_navbar_text',
            'dark_sidebar_text',
            'canvas_bg',
            'card_bg',
        ];
        $values = collect($names)->mapWithKeys(fn($name) => [$name => old($name, $theme->{$name})])->all();
        $props = [
            'action' => route('admin.theme-settings.update'),
            'values' => $values,
            'presets' => $presets,
            'errors' => $errors->toArray(),
        ];
    @endphp
    <div id="react-theme-settings-form"></div>
    <script id="react-theme-settings-form-props" type="application/json">{!! json_encode($props,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!}</script>
@endsection
