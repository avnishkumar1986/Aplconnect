import { useState } from "react";
import { Check, Select, Shell, Text, firstError } from "../shared/Controls";

export default function ApiIntegrationForm(p) {
    const [form, setForm] = useState(p.values);
    const [saving, setSaving] = useState(false);
    const change = (name) => (event) =>
        setForm((current) => ({ ...current, [name]: event.target.value }));
    const error = (name) => firstError(p.errors, name);

    return (
        <Shell
            {...p}
            title={
                p.editing ? "Edit API integration" : "Create API integration"
            }
            hideTitle
            submitting={saving}
            onSubmit={() => setSaving(true)}
        >
            <Text
                label="Code"
                name="code"
                value={form.code ?? ""}
                onChange={change("code")}
                error={error("code")}
                required
                help="Lowercase identifier, for example stock or quality."
            />
            <Text
                label="Name"
                name="name"
                value={form.name ?? ""}
                onChange={change("name")}
                error={error("name")}
                required
            />
            <Text
                label="Endpoint path"
                name="endpoint_path"
                value={form.endpoint_path ?? ""}
                onChange={change("endpoint_path")}
                error={error("endpoint_path")}
                help="Example: /sap/opu/odata/sap/ZMAT_STOCK_CDS/ZMAT_STOCK"
            />
            <Text
                label="Table name"
                name="tbl_name"
                value={form.tbl_name ?? ""}
                onChange={change("tbl_name")}
                error={error("tbl_name")}
                help="API results will be saved here. Example: tbl_materials"
            />
            <Select
                label="HTTP method"
                name="http_method"
                value={form.http_method ?? "GET"}
                onChange={change("http_method")}
                error={error("http_method")}
                options={p.methods}
                required
            />
            <Select
                label="Authentication"
                name="auth_type"
                value={form.auth_type ?? "none"}
                onChange={change("auth_type")}
                error={error("auth_type")}
                options={p.authTypes}
                required
            />
            <Text
                label="Credential environment key"
                name="credential_env_key"
                value={form.credential_env_key ?? ""}
                onChange={change("credential_env_key")}
                error={error("credential_env_key")}
                help="The secret stays in .env; enter only its variable name."
            />
            {form.auth_type === "basic" && (
                <>
                    <Text
                        label="Username environment key"
                        name="username_env_key"
                        value={form.username_env_key ?? "API_AUTH_USERNAME"}
                        onChange={change("username_env_key")}
                        error={error("username_env_key")}
                        required
                    />
                    <Text
                        label="Password environment key"
                        name="password_env_key"
                        value={form.password_env_key ?? "API_AUTH_PASSWORD"}
                        onChange={change("password_env_key")}
                        error={error("password_env_key")}
                        required
                    />
                </>
            )}
            <Text
                label="Timeout seconds"
                name="timeout_seconds"
                type="number"
                value={form.timeout_seconds ?? 30}
                onChange={change("timeout_seconds")}
                error={error("timeout_seconds")}
                required
            />
            <Text
                label="Retry attempts"
                name="retry_count"
                type="number"
                value={form.retry_count ?? 3}
                onChange={change("retry_count")}
                error={error("retry_count")}
                required
            />
            <Text
                label="Retry delay seconds"
                name="retry_delay_seconds"
                type="number"
                value={form.retry_delay_seconds ?? 10}
                onChange={change("retry_delay_seconds")}
                error={error("retry_delay_seconds")}
                required
            />
            <Check
                label="Verify SSL certificate"
                name="verify_ssl"
                checked={Boolean(Number(form.verify_ssl))}
                onChange={(event) =>
                    setForm((current) => ({
                        ...current,
                        verify_ssl: event.target.checked ? 1 : 0,
                    }))
                }
            />
            <Check
                label="Active"
                name="status"
                checked={Boolean(Number(form.status))}
                onChange={(event) =>
                    setForm((current) => ({
                        ...current,
                        status: event.target.checked ? 1 : 0,
                    }))
                }
            />
        </Shell>
    );
}
