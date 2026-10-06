@extends('layout.app')
@section('title', $integration->exists ? 'Edit API integration' : 'Create API integration')
@section('content')
    @php
        $values = [
            'code' => old('code', $integration->code),
            'name' => old('name', $integration->name),
            'base_url' => old('base_url', $integration->base_url),
            'endpoint_path' => old('endpoint_path', $integration->endpoint_path),
            'tbl_name' => old('tbl_name', $integration->tbl_name),
            'http_method' => old('http_method', $integration->http_method ?? 'GET'),
            'auth_type' => old('auth_type', $integration->auth_type ?? 'none'),
            'credential_env_key' => old('credential_env_key', $integration->credential_env_key),
            'username_env_key' => old('username_env_key', $integration->username_env_key ?? 'API_AUTH_USERNAME'),
            'password_env_key' => old('password_env_key', $integration->password_env_key ?? 'API_AUTH_PASSWORD'),
            'timeout_seconds' => old('timeout_seconds', $integration->timeout_seconds ?? 30),
            'retry_count' => old('retry_count', $integration->retry_count ?? 3),
            'retry_delay_seconds' => old('retry_delay_seconds', $integration->retry_delay_seconds ?? 10),
            'verify_ssl' => old('verify_ssl', $integration->exists ? (int) $integration->verify_ssl : 1),
            'status' => old('status', $integration->exists ? (int) $integration->status : 1),
        ];
        $props = [
            'editing' => $integration->exists,
            'action' => $integration->exists
                ? route('admin.api-integrations.update', $integration)
                : route('admin.api-integrations.store'),
            'method' => $integration->exists ? 'PUT' : 'POST',
            'cancelUrl' => route('admin.api-integrations.index'),
            'values' => $values,
            'errors' => $errors->toArray(),
            'methods' => collect(['GET', 'POST', 'PUT', 'PATCH', 'DELETE'])
                ->map(fn($value) => ['value' => $value, 'label' => $value])
                ->all(),
            'authTypes' => collect([
                'none' => 'No authentication',
                'basic' => 'Basic authentication',
                'bearer' => 'Bearer token',
                'api_key' => 'API key',
            ])
                ->map(fn($label, $value) => ['value' => $value, 'label' => $label])
                ->values()
                ->all(),
        ];
    @endphp
    <div id="react-api-integration-form"></div>
    <script id="react-api-integration-form-props" type="application/json">{!! json_encode($props,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!}</script>
@endsection
