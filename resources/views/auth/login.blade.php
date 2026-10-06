@extends('layout.app')

@section('title', 'Sign In | APL Connect')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/auth-login.css') }}">
@endpush
<style>
.auth-form > label.remember {
    display: flex;
    align-items: center;
    gap: 9px;
    margin: 18px 0;
    color: #566478;
    font-size: 12px;
    font-weight: 500;
    cursor: pointer;
}

.auth-form > label.remember input {
    flex: 0 0 16px;
    width: 16px;
    height: 16px;
    margin: 0;
    accent-color: var(--auth-teal);
}

.auth-sso {
    margin-top: 22px;
}

.auth-sso-divider {
    display: flex;
    align-items: center;
    gap: 14px;
    margin-bottom: 18px;
    color: var(--auth-muted);
    font-size: 11px;
    text-transform: uppercase;
}

.auth-sso-divider::before,
.auth-sso-divider::after {
    content: "";
    height: 1px;
    flex: 1;
    background: var(--auth-line);
}

.auth-sso-button {
    display: flex;
    width: 100%;
    min-height: 44px;
    align-items: center;
    justify-content: center;
    gap: 10px;
    padding: 0 16px;
    border: 1px solid var(--auth-line);
    border-radius: var(--auth-control-radius);
    background: #fff;
    color: var(--auth-ink);
    font-size: 12px;
    font-weight: 700;
    text-decoration: none;
    transition:
        border-color .16s,
        background .16s,
        color .16s,
        transform .16s;
}

.auth-sso-button:hover {
    border-color: var(--auth-teal);
    background: rgba(21, 154, 166, .05);
    color: var(--auth-teal-dark);
    transform: translateY(-1px);
}

.auth-sso-button:focus-visible {
    outline: 3px solid rgba(21, 154, 166, .2);
    outline-offset: 2px;
}

.auth-sso-button svg {
    width: 18px;
    height: 18px;
    fill: none;
    stroke: currentColor;
    stroke-width: 1.7;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.auth-security {
    margin-top: 22px;
}

.auth-copyright {
    margin-top: 32px;
}
</style>
@section('content')
    <main class="auth-page">
        <section class="auth-brand" aria-label="APL Connect">
            <div class="auth-grid" aria-hidden="true"></div>

            <div class="auth-brand-content">
                <a
                    href="{{ route('login') }}"
                    class="auth-wordmark"
                    aria-label="APL Connect sign in"
                >
                    <span class="auth-mark" aria-hidden="true">
                        <img src="{{ asset('images/apl-apollo-logo.webp') }}" alt="">
                    </span>

                    <span class="auth-wordmark-copy">
                        <strong>APL <em>Connect</em></strong>
                        <small class="enterprise-workspace-label">Enterprise workspace</small>
                    </span>
                </a>

                <div class="auth-message">
                    <p class="auth-kicker">Secure enterprise access</p>

                    <h1>
                        One workspace.<br>
                        <span>Complete control.</span>
                    </h1>

                    <p>
                        Access people, operations, permissions and business
                        insights through one protected corporate platform.
                    </p>
                </div>

                <div class="auth-trust">
                    <span><b>01</b> Centralized records</span>
                    <span><b>02</b> Role-based security</span>
                    <span><b>03</b> Real-time oversight</span>
                </div>
            </div>

            <p class="auth-brand-footer">
                APL Apollo · Authorized personnel only
            </p>
        </section>

        <section
            class="auth-form-panel"
            aria-labelledby="login-heading"
        >
            <div class="auth-form-wrap">
                <div class="auth-mobile-brand">
                    <span class="auth-mark" aria-hidden="true">
                        <img src="{{ asset('images/apl-apollo-logo.webp') }}" alt="">
                    </span>

                    <strong>APL <em>Connect</em></strong>
                </div>

                <header class="auth-heading">
                    <p class="auth-eyebrow">Welcome back</p>
                    <h2 id="login-heading">Sign in to your account</h2>
                    <p>
                        Enter your credentials to continue to the workspace.
                    </p>
                </header>

                {{-- React username/password login form --}}
                <div id="react-login-form"></div>

                <script
                    id="react-login-form-props"
                    type="application/json"
                >{!! json_encode(
                    [
                        'action' => route('login.store'),
                        'username' => old('username'),
                        'error' => $errors->first('username'),
                    ],
                    JSON_HEX_TAG
                    | JSON_HEX_AMP
                    | JSON_HEX_APOS
                    | JSON_HEX_QUOT
                ) !!}</script>

                {{-- Company email login --}}
                <div class="auth-sso">
                    <div class="auth-sso-divider" aria-hidden="true">
                        <span>or</span>
                    </div>

                    <a
                        href="{{ url('/auth/company') }}"
                        class="auth-sso-button"
                    >
                        <svg
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                            focusable="false"
                        >
                            <rect x="3" y="5" width="18" height="14" rx="2"/>
                            <path d="m4 7 8 6 8-6"/>
                        </svg>

                        <span>Continue with company email</span>
                    </a>
                </div>

                <div class="auth-security">
                    <svg
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                        focusable="false"
                    >
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/>
                        <path d="m9 12 2 2 4-4"/>
                    </svg>

                    <span>Protected with role-based access control</span>
                </div>

                <p class="auth-copyright">
                    © {{ date('Y') }} APL Apollo. All rights reserved.
                </p>
            </div>
        </section>
    </main>
@endsection
