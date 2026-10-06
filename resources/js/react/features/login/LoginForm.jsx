import { useState } from "react";
import { csrfToken } from "../shared/Controls";
export default function LoginForm(p) {
    const [username, setUsername] = useState(p.username ?? ""),
        [show, setShow] = useState(false),
        [saving, setSaving] = useState(false);
    return (
        <form
            method="POST"
            action={p.action}
            className="auth-form"
            onSubmit={() => setSaving(true)}
        >
            <input type="hidden" name="_token" value={csrfToken()} />
            <label htmlFor="username">Username</label>
            <div className="auth-input">
                <input
                    id="username"
                    name="username"
                    value={username}
                    onChange={(e) => setUsername(e.target.value)}
                    placeholder="Enter your username"
                    autoComplete="username"
                    required
                    autoFocus
                />
            </div>
            {p.error && (
                <div className="auth-error" role="alert">
                    <span className="auth-error-icon">!</span>
                    <span>{p.error}</span>
                </div>
            )}
            <div className="auth-label-row">
                <label htmlFor="password">Password</label>
                <a href="#">Forgot password?</a>
            </div>
            <div className="auth-input password-field">
                <input
                    id="password"
                    type={show ? "text" : "password"}
                    name="password"
                    placeholder="Enter your password"
                    autoComplete="current-password"
                    required
                />
                <button
                    type="button"
                    aria-label="Show password"
                    onClick={() => setShow(!show)}
                >
                    {show ? "Hide" : "Show"}
                </button>
            </div>
            <label className="remember">
                <input type="checkbox" name="remember" value="1" />
                <span>Keep me signed in</span>
            </label>
            <button className="auth-submit" type="submit" disabled={saving}>
                <span>{saving ? "Signing in…" : "Sign in securely"}</span>
            </button>
        </form>
    );
}
