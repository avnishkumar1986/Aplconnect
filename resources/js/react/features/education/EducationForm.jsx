import { useState } from "react";
import { Check, Shell, Select, Text, firstError } from "../shared/Controls";
export default function EducationForm(p) {
    const [f, setF] = useState(p.values),
        [saving, setSaving] = useState(false),
        u = (n) => (e) => setF({ ...f, [n]: e.target.value }),
        er = (n) => firstError(p.errors, n);
    return (
        <Shell
            {...p}
            title={p.editing ? "Edit education" : "Create education"}
            submitting={saving}
            onSubmit={() => setSaving(true)}
        >
            <Select
                label="User"
                name="user_id"
                value={f.user_id ?? ""}
                onChange={u("user_id")}
                options={p.options.user_id}
                error={er("user_id")}
                required
            />
            <Text
                label="Education level"
                name="education_level"
                value={f.education_level ?? ""}
                onChange={u("education_level")}
                error={er("education_level")}
                required
            />
            <Text
                label="Degree name"
                name="degree_name"
                value={f.degree_name ?? ""}
                onChange={u("degree_name")}
                error={er("degree_name")}
                required
            />
            <Text
                label="Institution name"
                name="institution_name"
                value={f.institution_name ?? ""}
                onChange={u("institution_name")}
                error={er("institution_name")}
                required
            />
            <Text
                label="University name"
                name="university_name"
                value={f.university_name ?? ""}
                onChange={u("university_name")}
                error={er("university_name")}
            />
            <Text
                label="Start date"
                name="start_date"
                type="date"
                value={f.start_date ?? ""}
                onChange={u("start_date")}
                error={er("start_date")}
            />
            <Text
                label="End date"
                name="end_date"
                type="date"
                value={f.end_date ?? ""}
                onChange={u("end_date")}
                error={er("end_date")}
            />
            <Check
                label="Currently studying"
                name="is_current"
                checked={Boolean(Number(f.is_current))}
                onChange={(e) =>
                    setF({ ...f, is_current: e.target.checked ? 1 : 0 })
                }
            />
            <Text
                label="Grade"
                name="grade"
                value={f.grade ?? ""}
                onChange={u("grade")}
                error={er("grade")}
            />
            <Text
                label="Document path"
                name="image_path"
                value={f.image_path ?? ""}
                onChange={u("image_path")}
                error={er("image_path")}
            />
            <Text
                label="Uploaded at"
                name="uploaded_at"
                type="datetime-local"
                value={f.uploaded_at ?? ""}
                onChange={u("uploaded_at")}
                error={er("uploaded_at")}
            />
            <Select
                label="Status"
                name="status"
                value={f.status ?? ""}
                onChange={u("status")}
                options={p.options.status}
                error={er("status")}
                required
            />
        </Shell>
    );
}
