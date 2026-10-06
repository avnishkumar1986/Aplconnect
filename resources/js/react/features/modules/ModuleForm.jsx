import { useState } from "react";
import { csrfToken } from "../shared/Controls";
export default function ModuleForm(p) {
    const [saving, setSaving] = useState(false);
    return (
        <form
            className="card mb-6 corporate-record-form p-4 sm:p-6"
            method="POST"
            action={p.action}
            encType="multipart/form-data"
            onSubmit={() => setSaving(true)}
        >
            <input type="hidden" name="_token" value={csrfToken()} />
            <h2 className="mb-4 text-lg font-semibold">
                Upload module package
            </h2>
            <div className="corporate-form-grid">
                <div className="form-field">
                    <label className="label">Module name *</label>
                    <input
                        className="input"
                        name="name"
                        defaultValue={p.values.name ?? ""}
                        required
                    />
                </div>
                <div className="form-field">
                    <label className="label">Version *</label>
                    <input
                        className="input"
                        name="version"
                        defaultValue={p.values.version ?? ""}
                        required
                    />
                </div>
                <div className="form-field form-field-wide">
                    <label className="label">Description</label>
                    <textarea
                        className="input"
                        name="description"
                        rows="3"
                        defaultValue={p.values.description ?? ""}
                    />
                </div>
                <div className="form-field">
                    <label className="label">Status *</label>
                    <select
                        className="input"
                        name="status"
                        defaultValue={p.values.status ?? "active"}
                    >
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <div className="form-field">
                    <label className="label">ZIP package *</label>
                    <input
                        className="input"
                        type="file"
                        name="module_file"
                        accept=".zip,application/zip"
                        required
                    />
                </div>
            </div>
            {Object.keys(p.errors ?? {}).length > 0 && (
                <div className="form-error-alert mt-4">
                    Please correct the upload fields.
                </div>
            )}
            <button className="btn-primary mt-5" disabled={saving}>
                {saving ? "Uploading…" : "Upload module"}
            </button>
        </form>
    );
}
