import { useMemo, useRef, useState } from "react";
import IndiaLocationFields from "../shared/IndiaLocationFields";

const steps = [
    "Personal",
    "Employment",
    "Contact",
    "Address",
    "Education",
    "Login",
];
const errorStep = {
    type_code: 0,
    code: 0,
    distributor_business_name: 0,
    distributor_legal_business_name: 0,
    distributor_category: 0,
    distributor_tier_code: 0,
    gst_number: 1,
    pan_number: 1,
    first_name: 0,
    last_name: 0,
    gender: 0,
    date_of_birth: 0,
    marital_status: 0,
    anniversary_date: 0,
    company_id: 0,
    sitting_location_id: 0,
    department_id: 0,
    designation_id: 0,
    role_id: 0,
    contact_id: 2,
    new_contact_type: 2,
    new_contact_value: 2,
    address_id: 3,
    new_address_type: 3,
    new_address_line_1: 3,
    new_city: 3,
    new_state: 3,
    new_postal_code: 3,
    new_education_level: 4,
    new_degree_name: 4,
    new_institution_name: 4,
    new_education_document: 4,
    supplier_gst_document: 1,
    supplier_pan_document: 1,
    distributor_company_association: 6,
    distributor_applicable_plants: 6,
    distributor_material_groups: 6,
    distributor_credit_limit: 6,
    distributor_credit_period_days: 6,
    distributor_payment_terms: 6,
    distributor_price_list: 6,
    distributor_currency: 6,
    distributor_tax_classification: 6,
    distributor_bank_name: 6,
    distributor_account_holder_name: 6,
    distributor_account_number: 6,
    distributor_ifsc_code: 6,
    distributor_bank_branch: 6,
    distributor_cancelled_cheque: 6,
    username: 5,
    password: 5,
    password_confirmation: 5,
    is_verified: 5,
    status: 5,
};

function ErrorMessage({ message }) {
    return message ? (
        <p className="mt-1 text-sm text-red-600">{message}</p>
    ) : null;
}
function Text({
    label,
    name,
    value,
    change,
    error,
    type = "text",
    required = false,
    disabled = false,
    placeholder = "",
    autoComplete,
    pattern,
    min,
    max,
    step,
    minLength,
    maxLength,
    inputMode,
    title,
}) {
    return (
        <div className="form-field">
            <div className="floating-control">
                <input
                    className="input floating-input"
                    id={name}
                    name={name}
                    type={type}
                    value={value ?? ""}
                    onChange={change(name)}
                    required={required}
                    disabled={disabled}
                    placeholder={placeholder || " "}
                    aria-label={label}
                    autoComplete={autoComplete}
                    pattern={pattern}
                    min={min}
                    max={max}
                    step={step}
                    minLength={minLength}
                    maxLength={maxLength}
                    inputMode={inputMode}
                    title={title}
                    aria-invalid={Boolean(error)}
                    aria-describedby={error ? `${name}-error` : undefined}
                />
                <label className="floating-label" htmlFor={name}>
                    {label}{required && <span> *</span>}
                </label>
            </div>
            <div id={`${name}-error`}><ErrorMessage message={error} /></div>
        </div>
    );
}
function Select({
    label,
    name,
    value,
    change,
    error,
    options = [],
    required = false,
    disabled = false,
}) {
    return (
        <div className="form-field">
            <div className="floating-control floating-select-control">
                <select
                    className="input floating-input"
                    id={name}
                    name={name}
                    value={value ?? ""}
                    onChange={change(name)}
                    required={required}
                    disabled={disabled}
                    aria-label={label}
                    aria-invalid={Boolean(error)}
                    aria-describedby={error ? `${name}-error` : undefined}
                >
                    <option value="">Select</option>
                    {options
                        .filter((x) => x.value !== "")
                        .map((x) => (
                            <option key={`${name}-${x.value}`} value={x.value}>
                                {x.label}
                            </option>
                        ))}
                </select>
                <label className="floating-label" htmlFor={name}>
                    {label}{required && <span> *</span>}
                </label>
            </div>
            <div id={`${name}-error`}><ErrorMessage message={error} /></div>
        </div>
    );
}

function UserLookup({ label, name, value, change, error, options = [], required = false }) {
    const selected = options.find((option) => String(option.value) === String(value ?? ""));
    const [query, setQuery] = useState(selected?.label ?? "");
    const listId = `${name}-options`;
    const update = (event) => {
        const text = event.target.value;
        setQuery(text);
        const match = options.find((option) => option.label.toLocaleLowerCase() === text.trim().toLocaleLowerCase());
        change(name)({ target: { value: match?.value ?? "" } });
    };

    return (
        <div className="form-field">
            <input type="hidden" name={name} value={value ?? ""} />
            <div className="floating-control">
                <input
                    className="input floating-input"
                    id={`${name}-lookup`}
                    type="text"
                    value={query}
                    onChange={update}
                    list={listId}
                    placeholder=" "
                    autoComplete="off"
                    required={required}
                    aria-label={label}
                />
                <label className="floating-label" htmlFor={`${name}-lookup`}>
                    {label}{required && <span> *</span>}
                </label>
                <datalist id={listId}>
                    {options.map((option) => <option key={`${name}-${option.value}`} value={option.label} />)}
                </datalist>
            </div>
            <ErrorMessage message={error} />
        </div>
    );
}

function CheckboxTable({ label, name, value, change, options = [], error, itemLabel = "Item" }) {
    const [search, setSearch] = useState("");
    const selected = Array.isArray(value) ? value.map(String) : [];
    const visibleOptions = options.filter(option =>
        `${option.value} ${option.label}`.toLocaleLowerCase().includes(search.trim().toLocaleLowerCase()),
    );
    const toggle = optionValue => {
        const normalized = String(optionValue);
        change(selected.includes(normalized)
            ? selected.filter(item => item !== normalized)
            : [...selected, normalized]);
    };
    const selectVisible = () => change([...new Set([...selected, ...visibleOptions.map(option => String(option.value))])]);

    return (
        <div className="form-field form-field-wide checkbox-table-field">
            {selected.map(item => <input key={`${name}-selected-${item}`} type="hidden" name={`${name}[]`} value={item} />)}
            <div className="overflow-hidden rounded-xl border border-slate-200 bg-white">
                <div className="flex flex-col gap-3 border-b border-slate-200 bg-slate-50 p-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <label className="block text-sm font-semibold text-slate-800">{label}</label>
                        <span className="text-xs text-slate-500">{selected.length} selected</span>
                    </div>
                    <div className="flex flex-col gap-2 sm:flex-row">
                        <input className="input sm:w-56" value={search} onChange={event => setSearch(event.target.value)} placeholder={`Search ${label.toLowerCase()}…`} />
                        <button type="button" className="btn-secondary" onClick={selectVisible}>Select all</button>
                        <button type="button" className="btn-secondary" onClick={() => change([])}>Clear</button>
                    </div>
                </div>
                <div className="max-h-64 overflow-auto">
                    <table className="w-full min-w-96 text-left text-sm">
                        <thead className="sticky top-0 z-10 bg-slate-100 text-xs uppercase tracking-wide text-slate-600">
                            <tr><th className="w-16 px-4 py-3">Select</th><th className="px-4 py-3">{itemLabel}</th><th className="w-36 px-4 py-3">Code</th></tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {visibleOptions.length ? visibleOptions.map(option => {
                                const checked = selected.includes(String(option.value));
                                return <tr key={`${name}-${option.value}`} className={checked ? "bg-cyan-50" : "hover:bg-slate-50"}>
                                    <td className="px-4 py-3"><input type="checkbox" checked={checked} onChange={() => toggle(option.value)} aria-label={`Select ${option.label}`} /></td>
                                    <td className="px-4 py-3 font-medium text-slate-800">{option.label}</td>
                                    <td className="px-4 py-3 text-slate-500">{option.value}</td>
                                </tr>;
                            }) : <tr><td colSpan="3" className="px-4 py-8 text-center text-slate-500">No matching records.</td></tr>}
                        </tbody>
                    </table>
                </div>
            </div>
            <ErrorMessage message={error} />
        </div>
    );
}

function AddressCoordinates({ index, row, changeAddress, latitudeError, longitudeError, prefix = "addresses", required = true }) {
    const [geocoding, setGeocoding] = useState(false);
    const [geocodingMessage, setGeocodingMessage] = useState("");
    const latitude = Number(row.latitude);
    const longitude = Number(row.longitude);
    const hasCoordinates = Number.isFinite(latitude) && Number.isFinite(longitude)
        && row.latitude !== "" && row.longitude !== "";

    const useCurrentLocation = () => {
        if (!navigator.geolocation) return;
        navigator.geolocation.getCurrentPosition(({ coords }) => {
            changeAddress(index, "latitude", coords.latitude.toFixed(7));
            changeAddress(index, "longitude", coords.longitude.toFixed(7));
        });
    };
    const canGeocode = Boolean(row.line_1 && row.state && row.city && row.postal_code);
    const getMapLocation = async () => {
        if (!canGeocode || geocoding) return;
        setGeocoding(true);
        setGeocodingMessage("");
        try {
            const cleanPlace = value => String(value || "")
                .replace(/\s+(H\.?O\.?|S\.?O\.?|B\.?O\.?)$/i, "")
                .trim();
            const city = cleanPlace(row.city);
            const district = cleanPlace(row.district);
            const country = row.country || "India";
            const candidates = [
                [row.line_1, row.line_2, city, district, row.state, row.postal_code, country],
                [row.line_1, row.line_2, city, row.state, row.postal_code, country],
                [city, district, row.state, row.postal_code, country],
                [row.postal_code, row.state, country],
            ].map(parts => parts.filter(Boolean).join(", "));

            let match = null;
            for (const query of candidates) {
                const params = new URLSearchParams({ q: query, format: "jsonv2", limit: "1", countrycodes: "in" });
                const response = await fetch(`https://nominatim.openstreetmap.org/search?${params.toString()}`, {
                    headers: { Accept: "application/json" },
                });
                if (!response.ok) throw new Error("Location service is unavailable.");
                const results = await response.json();
                if (results.length) {
                    match = results[0];
                    break;
                }
                await new Promise(resolve => window.setTimeout(resolve, 1000));
            }
            if (!match) {
                setGeocodingMessage("Location not found. Check the address and postal code.");
                return;
            }
            changeAddress(index, "latitude", Number(match.lat).toFixed(7));
            changeAddress(index, "longitude", Number(match.lon).toFixed(7));
            setGeocodingMessage("Map location found from the entered address.");
        } catch (error) {
            setGeocodingMessage(error.message || "Unable to get the map location.");
        } finally {
            setGeocoding(false);
        }
    };

    return (
        <div className="address-coordinate-controls mt-3 border-t border-slate-100 pt-3">
            {!canGeocode && <p className="mb-3 text-xs text-amber-700">Fill Address Line 1, State, City and Postal Code to get the map location.</p>}
            {geocodingMessage && !hasCoordinates && <p className="mb-3 text-xs text-red-600">{geocodingMessage}</p>}
            <div className="corporate-form-grid address-coordinate-grid">
                <Text label="Latitude" name={`${prefix}[${index}][latitude]`} type="number" value={row.latitude} change={() => event => changeAddress(index, "latitude", event.target.value)} error={latitudeError} required={required} />
                <Text label="Longitude" name={`${prefix}[${index}][longitude]`} type="number" value={row.longitude} change={() => event => changeAddress(index, "longitude", event.target.value)} error={longitudeError} required={required} />
                <div className="form-field">
                    <button type="button" className="btn-primary w-full" onClick={getMapLocation} disabled={!canGeocode || geocoding}>
                        {geocoding ? "Getting location…" : "Get map location"}
                    </button>
                </div>
                <div className="form-field">
                    <button type="button" className="btn-secondary w-full" onClick={useCurrentLocation}>Use current location</button>
                </div>
            </div>
        </div>
    );
}

function AddressMapPreview({ index, row }) {
    const latitude = Number(row.latitude);
    const longitude = Number(row.longitude);
    const hasCoordinates = Number.isFinite(latitude) && Number.isFinite(longitude)
        && row.latitude !== "" && row.longitude !== "";
    const mapUrl = hasCoordinates
        ? `https://www.openstreetmap.org/export/embed.html?bbox=${longitude - 0.01}%2C${latitude - 0.01}%2C${longitude + 0.01}%2C${latitude + 0.01}&layer=mapnik&marker=${latitude}%2C${longitude}`
        : "";
    const shareOnWhatsApp = () => {
        if (!hasCoordinates) return;
        const address = [row.line_1, row.line_2, row.city, row.district, row.state, row.postal_code, row.country]
            .filter(Boolean)
            .join(", ");
        const googleMapsUrl = `https://www.google.com/maps?q=${latitude},${longitude}`;
        const message = ["Location", address, googleMapsUrl].filter(Boolean).join("\n");
        window.open(`https://wa.me/?text=${encodeURIComponent(message)}`, "_blank", "noopener,noreferrer");
    };

    return (
        <div className="address-map-share-panel">
            {hasCoordinates ? (
                <iframe
                    className="min-h-96 w-full rounded-xl border border-slate-200 bg-white shadow-sm"
                    src={mapUrl}
                    title={`Address location ${index + 1}`}
                    loading="lazy"
                />
            ) : (
                <div className="address-map-empty">
                    <span>⌖</span>
                    <strong>Map preview</strong>
                    <p>Enter the address and select Get map location.</p>
                </div>
            )}
            <button type="button" className="whatsapp-map-share" onClick={shareOnWhatsApp} disabled={!hasCoordinates}>
                <span aria-hidden="true">●</span> Share location on WhatsApp
            </button>
        </div>
    );
}

const contactTypeLabels = { "1": "Mobile", "2": "Telephone", "3": "Email", "4": "Emergency contact" };
const addressTypeLabels = { "1": "Local", "2": "Permanent", "3": "Official", "4": "Plant", "5": "Subsidiary" };
const addressLabel = row => String(row.type) === "1" && Boolean(row.is_current_permanent_same)
    ? "Local & Permanent"
    : (addressTypeLabels[String(row.type)] ?? "Address");

function RecordsTable({ columns, rows, emptyMessage, onEdit, onRemove }) {
    return <div className="wizard-record-table-wrap"><table className="wizard-record-table">
        <thead><tr><th>#</th>{columns.map(column => <th key={column.key}>{column.label}</th>)}<th>Actions</th></tr></thead>
        <tbody>{rows.length ? rows.map((row, index) => <tr key={row.key ?? index}>
            <td>{index + 1}</td>
            {columns.map(column => <td key={column.key}>{column.render(row, index) || "—"}</td>)}
            <td><div className="wizard-record-actions"><button type="button" onClick={() => onEdit(index)}>Edit</button>{rows.length > 1 && <button type="button" className="danger" onClick={() => onRemove(index)}>Remove</button>}</div></td>
        </tr>) : <tr><td className="wizard-record-empty" colSpan={columns.length + 2}>{emptyMessage}</td></tr>}</tbody>
    </table></div>;
}

export default function UserForm({
    distributorMode = false,
    lockUserType = false,
    title,
    description,
    action,
    method = "POST",
    cancelUrl,
    fields = [],
    values = {},
    errors = {},
    initialContacts = [],
    initialAddresses = [],
    initialSubsidiaries = [],
    initialAdditionalEducations = [],
    roleOptions = [],
    supplierOptions = [],
    materialGroupOptions = [],
    distributorTypeOptions = [],
    distributorTierOptions = [],
    supplierDocuments = {},
    distributorCancelledChequeUrl = null,
}) {
    const initialUserType = fields
        .find(field => field.name === "type_code")
        ?.options?.find(option => String(option.value) === String(values.type_code));
    const initialIsDistributor = /distributor/i.test(initialUserType?.label ?? "");
    const failedStep = Object.keys(errors).length
        ? Math.min(
              ...Object.keys(errors).map((name) =>
                name.startsWith("subsidiaries.")
                      ? 2
                      : name.startsWith("contacts.")
                      ? 2
                      : /^addresses\.\d+\.(latitude|longitude)$/.test(name)
                        ? (initialIsDistributor ? 2 : 3)
                        : name.startsWith("addresses.")
                          ? (initialIsDistributor ? 2 : 3)
                        : name.startsWith("additional_education.")
                          ? 4
                        : (errorStep[name] ?? 5),
              ),
          )
        : 0;
    const [step, setStep] = useState(failedStep);
    const [saving, setSaving] = useState(false);
    const [changePassword, setChangePassword] = useState(method === "POST");
    const [form, setForm] = useState({
        new_education_is_current: "0",
        ...values,
        distributor_material_groups: initialIsDistributor
            && method === "POST"
            && (!Array.isArray(values.distributor_material_groups) || values.distributor_material_groups.length === 0)
                ? materialGroupOptions.map(option => String(option.value))
                : (values.distributor_material_groups ?? []),
    });
    const [contacts, setContacts] = useState(
        initialContacts.length
            ? initialContacts
            : [{ type: "1", value: "", is_primary: true }],
    );
    const [addresses, setAddresses] = useState(
        initialAddresses.length
            ? initialAddresses
            : [{
            type: "1",
            is_current_permanent_same: false,
            line_1: "",
            line_2: "",
            city: "",
            district: "",
            state: "",
            postal_code: "",
            country: "India",
            latitude: "",
            longitude: "",
        }],
    );
    const [subsidiaries, setSubsidiaries] = useState(initialSubsidiaries);
    const [activeSubsidiaryIndex, setActiveSubsidiaryIndex] = useState(0);
    const [activeDistributorAddress, setActiveDistributorAddress] = useState("main");
    const failedAddressIndex = Object.keys(errors)
        .map((name) => name.match(/^addresses\.(\d+)\./)?.[1])
        .find((index) => index !== undefined);
    const [activeAddressIndex, setActiveAddressIndex] = useState(Number(failedAddressIndex ?? 0));
    const [additionalEducations, setAdditionalEducations] = useState(initialAdditionalEducations);
    const addEducation = () =>
        setAdditionalEducations((rows) => [
            ...rows,
            {
                level: "",
                degree_name: "",
                institution_name: "",
                university_name: "",
                start_date: "",
                end_date: "",
                grade: "",
                is_current: false,
            },
        ]);
    const changeEducation = (index, field) => (event) =>
        setAdditionalEducations((rows) =>
            rows.map((row, rowIndex) =>
                rowIndex === index
                    ? {
                          ...row,
                          [field]:
                              event.target.type === "checkbox"
                                  ? event.target.checked
                                  : event.target.value,
                      }
                    : row,
            ),
        );
    const focusRecord = (id) => {
        window.requestAnimationFrame(() => {
            const record = document.getElementById(id);
            record?.scrollIntoView({ behavior: "smooth", block: "center" });
            record?.querySelector("input:not([type='hidden']), select")?.focus();
        });
    };
    const formRef = useRef(null);
    const explicitSubmitRef = useRef(false);
    const options = useMemo(
        () =>
            Object.fromEntries(
                fields.map((field) => [field.name, field.options ?? []]),
            ),
        [fields],
    );
    const change = (name) => (event) =>
        setForm((current) => ({
            ...current,
            [name]: event.target.value,
        }));
    const changeContact = (index, key, value) =>
        setContacts((rows) =>
            rows.map((row, i) =>
                i === index
                    ? { ...row, [key]: value }
                    : key === "is_primary"
                      ? { ...row, is_primary: false }
                      : row,
            ),
        );
    const addContact = () => {
        const phoneCount = contacts.filter(row => ["1", "2"].includes(String(row.type))).length;
        const emailCount = contacts.filter(row => String(row.type) === "3").length;
        const emergencyCount = contacts.filter(row => String(row.type) === "4").length;
        const nextType = phoneCount < maxPhoneContacts ? "1" : emailCount < maxEmailContacts ? "3" : emergencyCount < 1 ? "4" : null;
        if (!nextType) return;
        setContacts(rows => [...rows, { type: nextType, value: "", is_primary: false }]);
    };
    const changeDistributorContact = (kind, value) => {
        const isMatch = row => kind === "phone"
            ? ["1", "2"].includes(String(row.type))
            : String(row.type) === "3";
        setContacts(rows => {
            const existingIndex = rows.findIndex(isMatch);
            if (existingIndex === -1) {
                return [...rows, { type: kind === "phone" ? "1" : "3", value, is_primary: true }];
            }
            return rows.map((row, index) => index === existingIndex
                ? { ...row, value, is_primary: true }
                : row);
        });
    };
    const changeAddress = (index, key, value) =>
        setAddresses((rows) =>
            rows.map((row, i) =>
                i === index ? {
                    ...row,
                    [key]: value,
                    ...(key === "type" && String(value) !== "1" ? { is_current_permanent_same: false } : {}),
                } : row,
            ),
        );
    const setCurrentPermanentSame = (index, checked) => {
        setAddresses(rows => {
            const selectedIndex = checked
                ? rows.slice(0, index).filter(row => String(row.type) !== "2").length
                : index;
            const updated = rows
                .map((row, rowIndex) => rowIndex === index
                    ? { ...row, is_current_permanent_same: checked }
                    : row)
                .filter((row, rowIndex) => !checked || rowIndex === index || String(row.type) !== "2");
            window.requestAnimationFrame(() => setActiveAddressIndex(Math.max(0, selectedIndex)));
            return updated;
        });
    };
    const addAddress = () => {
        if (addresses.length >= maxAddresses) return;
        const nextIndex = addresses.length;
        setAddresses((rows) => [
            ...rows,
            {
                type: addresses.some(row => String(row.type) === "1" && row.is_current_permanent_same) ? "3" : "2",
                is_current_permanent_same: false,
                line_1: "",
                line_2: "",
                city: "",
                district: "",
                state: "",
                postal_code: "",
                country: "India",
                latitude: "",
                longitude: "",
            },
        ]);
        setActiveAddressIndex(nextIndex);
    };
    const removeAddress = (index) => {
        if (addresses.length === 1) return;
        setAddresses((rows) => rows.filter((_, rowIndex) => rowIndex !== index));
        setActiveAddressIndex((current) => Math.max(0, current > index ? current - 1 : Math.min(current, addresses.length - 2)));
    };
    const addSubsidiary = () => setSubsidiaries(rows => {
        setActiveSubsidiaryIndex(rows.length);
        setActiveDistributorAddress(`subsidiary-${rows.length}`);
        return [...rows, {
            name: "", phone: "", email: "", line_1: "", line_2: "",
            city: "", district: "", state: "", postal_code: "", country: "India",
            latitude: "", longitude: "",
        }];
    });
    const removeSubsidiary = index => {
        setSubsidiaries(rows => rows.filter((_, rowIndex) => rowIndex !== index));
        setActiveSubsidiaryIndex(current => Math.max(0, current > index ? current - 1 : Math.min(current, subsidiaries.length - 2)));
        setActiveDistributorAddress("main");
    };
    const changeSubsidiary = (index, key, value) => setSubsidiaries(rows =>
        rows.map((row, rowIndex) => rowIndex === index ? { ...row, [key]: value } : row),
    );
    const err = (name) => (errors[name] ?? [])[0];
    const label = (name) =>
        options[name]?.find((x) => String(x.value) === String(form[name]))
            ?.label || "Not selected";
    const selectedUserType = options.type_code?.find(
        (option) => String(option.value) === String(form.type_code),
    );
    const isDistributorType = /distributor/i.test(selectedUserType?.label ?? "");
    const isEmployeeType = /employee/i.test(selectedUserType?.label ?? "");
    const distributorPhone = contacts.find(row => ["1", "2"].includes(String(row.type))) ?? { type: "1", value: "", is_primary: true };
    const distributorEmail = contacts.find(row => String(row.type) === "3") ?? { type: "3", value: "", is_primary: true };
    const maxAddresses = isEmployeeType ? 2 : 1;
    const maxPhoneContacts = isEmployeeType ? 2 : 1;
    const maxEmailContacts = 1;
    const userCodeLabel = `${selectedUserType?.label ?? "User"} ID`;
    const changeUserType = (event) => {
        const nextType = event.target.value;
        const nextTypeLabel = options.type_code?.find(option => String(option.value) === String(nextType))?.label ?? "";
        setForm((current) => ({
            ...current,
            type_code: nextType,
            code: current.type_code === nextType ? current.code : "",
            ...(/distributor/i.test(nextTypeLabel)
                && (!Array.isArray(current.distributor_material_groups) || current.distributor_material_groups.length === 0)
                ? { distributor_material_groups: materialGroupOptions.map(option => String(option.value)) }
                : {}),
        }));
    };
    const wizardSteps = isDistributorType
        ? ["Distributor", "Documents", "Contact & Address", "Commercial & Banking", "Login"]
        : ["Personal & Employment", "Contact", "Address & Map", "Education", "Login"];
    const wizardStepIndexes = isDistributorType ? [0, 1, 2, 6, 5] : [0, 2, 3, 4, 5];
    const visibleStep = Math.max(0, wizardStepIndexes.indexOf(step));
    const csrf =
        document.querySelector('meta[name="csrf-token"]')?.content ?? "";
    const closeForm = () => {
        const localClose = document.querySelector("[data-user-modal-close]");
        const parentClose = window.parent !== window
            ? window.parent.document.querySelector("[data-global-form-close]")
            : null;
        if (localClose) localClose.click();
        else if (parentClose) parentClose.click();
        else window.location.href = cancelUrl;
    };
    const next = () => {
        const panels = formRef.current?.querySelectorAll(`[data-step="${step}"]`) ?? [];
        const invalid = [
            ...[...panels].flatMap(panel => [...panel.querySelectorAll("input,select,textarea")]),
        ].find((input) => !input.disabled && input.type !== "hidden" && input.offsetParent !== null && !input.checkValidity());
        if (invalid) {
            invalid.reportValidity();
            return;
        }
        const currentIndex = wizardStepIndexes.indexOf(step);
        setStep(wizardStepIndexes[Math.min(currentIndex + 1, wizardStepIndexes.length - 1)]);
    };

    return (
        <div className={"user-wizard user-wizard-compact "+(method !== "POST" ? "is-editing" : "")}>
            {!document.querySelector("[data-user-modal]") && (
                <div className="user-wizard-page-header mb-6 flex justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold">{title}</h1>
                        <p className="text-sm text-slate-500">{description}</p>
                    </div>
                    <a className="btn-secondary" href={cancelUrl}>
                        ← Back
                    </a>
                </div>
            )}
            <div className="user-wizard-steps mb-4 rounded-xl border border-slate-200 bg-slate-50 p-3">
                <div className="mb-3 h-1.5 overflow-hidden rounded-full bg-slate-200">
                    <div className="h-full rounded-full bg-cyan-600 transition-all duration-300" style={{ width: `${((visibleStep + 1) / wizardSteps.length) * 100}%` }} />
                </div>
                <ol className="grid gap-2" style={{ gridTemplateColumns: `repeat(${wizardSteps.length}, minmax(0, 1fr))` }}>
                    {wizardSteps.map((name, index) => {
                        const actualStep = wizardStepIndexes[index];
                        const active = actualStep === step;
                        const complete = index < visibleStep;
                        return <li key={name}><button type="button" aria-current={active ? "step" : undefined} data-complete={complete ? "true" : undefined} onClick={() => index <= visibleStep && setStep(actualStep)} className={`w-full rounded-lg border px-2 py-2 text-left transition ${active ? "border-cyan-200 bg-cyan-50 text-cyan-800" : complete ? "border-transparent text-emerald-700" : "border-transparent text-slate-400"}`}>
                            <span className="flex items-center gap-2"><span className={`inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full border text-xs font-bold ${active ? "border-cyan-600 bg-cyan-600 text-white" : complete ? "border-emerald-600 bg-emerald-600 text-white" : "border-slate-300 bg-white"}`}>{complete ? "✓" : index + 1}</span><b className="block text-xs">{name}</b></span>
                        </button></li>;
                    })}
                </ol>
            </div>
            <form
                ref={formRef}
                className={isDistributorType && step === 2 ? "flex flex-col" : ""}
                style={isDistributorType && step === 2 ? { display: "flex", flexDirection: "column" } : undefined}
                method="POST"
                action={action}
                encType="multipart/form-data"
                onSubmit={(event) => {
                    if (step !== 5) {
                        event.preventDefault();
                        next();
                        return;
                    }
                    if (!explicitSubmitRef.current) {
                        event.preventDefault();
                        return;
                    }
                    explicitSubmitRef.current = false;
                    setSaving(true);
                }}
            >
                <input type="hidden" name="_token" value={csrf} />
                {method !== "POST" && (
                    <input type="hidden" name="_method" value={method} />
                )}
                {Object.keys(errors).length > 0 && (
                    <div className="form-error-alert">
                        <span className="form-error-alert-icon">!</span>
                        <div>
                            <strong>Unable to save {distributorMode ? "distributor" : "employee"}</strong>
                            <p>Please correct the following fields and submit again:</p>
                            <ul className="form-error-summary-list">
                                {[...new Set(Object.values(errors).flat())].map((message, index) => (
                                    <li key={`${index}-${message}`}>{message}</li>
                                ))}
                            </ul>
                        </div>
                    </div>
                )}

                <section data-step="0" className={step === 0 ? "" : "hidden"}>
                    <div className="user-form-group user-form-group-classification">
                        <div className="user-form-group-heading">
                            <span>01</span>
                            <div><h3>Account classification</h3><p>Select the onboarding type and enter its unique reference code.</p></div>
                        </div>
                        <div className="corporate-form-grid classification-grid">
                            <Select
                                label={distributorMode ? "Distributor type" : "Employee type"}
                                name="type_code"
                                value={form.type_code}
                                change={() => changeUserType}
                                error={err("type_code")}
                                options={options.type_code}
                                disabled={lockUserType}
                                required
                            />
                            {lockUserType && <input type="hidden" name="type_code" value={form.type_code} />}
                            <Text
                                label={userCodeLabel}
                                name="code"
                                type="number"
                                value={form.code}
                                change={change}
                                error={err("code")}
                                required
                            />
                        </div>
                    </div>
                    <div className="user-form-group-heading">
                        <span>02</span>
                        <div><h3>{isDistributorType ? "Distributor details" : "Personal details"}</h3><p>{isDistributorType ? "Provide the distributor's registered business information." : "Enter the user's basic personal information."}</p></div>
                    </div>
                    {isDistributorType ? (
                        <div className="corporate-form-grid">
                            <Text
                                label="Distributor / Business name"
                                name="distributor_business_name"
                                value={form.distributor_business_name}
                                change={change}
                                error={err("distributor_business_name")}
                                required
                            />
                            <Text
                                label="Legal business name"
                                name="distributor_legal_business_name"
                                value={form.distributor_legal_business_name}
                                change={change}
                                error={err("distributor_legal_business_name")}
                                required
                            />
                            <Select
                                label="Distributor type"
                                name="distributor_category"
                                value={form.distributor_category}
                                change={change}
                                error={err("distributor_category")}
                                options={distributorTypeOptions}
                                required
                            />
                            <Select
                                label="Distributor tier"
                                name="distributor_tier_code"
                                value={form.distributor_tier_code}
                                change={change}
                                error={err("distributor_tier_code")}
                                options={distributorTierOptions}
                                required
                            />
                            <Select
                                label="Role"
                                name="role_id"
                                value={form.role_id}
                                change={change}
                                error={err("role_id")}
                                options={roleOptions}
                                disabled={distributorMode}
                                required
                            />
                            {distributorMode && <input type="hidden" name="role_id" value={form.role_id} />}
                            <Text
                                label="First name"
                                name="first_name"
                                value={form.first_name}
                                change={change}
                                error={err("first_name")}
                                required
                            />
                            <Text
                                label="Last name"
                                name="last_name"
                                value={form.last_name}
                                change={change}
                                error={err("last_name")}
                                required
                            />
                        </div>
                    ) : (
                    <>
                    <div className="corporate-form-grid">
                        <Text
                            label="First name"
                            name="first_name"
                            value={form.first_name}
                            change={change}
                            error={err("first_name")}
                            required
                        />
                        <Text
                            label="Last name"
                            name="last_name"
                            value={form.last_name}
                            change={change}
                            error={err("last_name")}
                            required
                        />
                        <Select
                            label="Gender"
                            name="gender"
                            value={form.gender}
                            change={change}
                            error={err("gender")}
                            options={options.gender}
                        />
                        <Text
                            label="Date of birth"
                            name="date_of_birth"
                            type="date"
                            value={form.date_of_birth}
                            change={change}
                            error={err("date_of_birth")}
                        />
                        <Select
                            label="Marital status"
                            name="marital_status"
                            value={form.marital_status}
                            change={change}
                            error={err("marital_status")}
                            options={options.marital_status}
                        />
                        <Text
                            label="Anniversary date"
                            name="anniversary_date"
                            type="date"
                            value={form.anniversary_date}
                            change={change}
                            error={err("anniversary_date")}
                        />
                    </div>
                    <div className="mb-2 mt-6 rounded-lg bg-slate-50 px-4 py-3"><h4 className="text-sm font-semibold text-slate-800">Employment details</h4></div>
                    <div className="corporate-form-grid">
                        <Select label="Company" name="company_id" value={form.company_id} change={change} error={err("company_id")} options={options.company_id} required />
                        <Select label="Sitting location" name="sitting_location_id" value={form.sitting_location_id} change={change} error={err("sitting_location_id")} options={options.sitting_location_id} required />
                        <Select label="Department" name="department_id" value={form.department_id} change={change} error={err("department_id")} options={options.department_id} />
                        <Select label="Designation" name="designation_id" value={form.designation_id} change={change} error={err("designation_id")} options={options.designation_id} />
                        <Select label="Role" name="role_id" value={form.role_id} change={change} error={err("role_id")} options={roleOptions} required />
                    </div>
                    </>
                    )}
                </section>

                <section data-step="1" className={step === 1 ? "" : "hidden"}>
                    {isDistributorType && (
                        <>
                            <h3 className="font-semibold">Distributor documents</h3>
                            <p className="mb-4 text-sm text-slate-500">
                                Upload GST registration and PAN card documents. PDF, JPG, PNG or WebP up to 10 MB.
                            </p>
                            <div className="distributor-document-grid">
                                <article className="distributor-document-card">
                                    <header><span>GST</span><div><h4>GST registration</h4><p>Upload a clear registration certificate for automatic GSTIN extraction.</p></div></header>
                                    <div className="distributor-document-fields">
                                        <div className="form-field document-upload-field">
                                            <label className="label" htmlFor="supplier_gst_document">GST document</label>
                                            <input className="input" id="supplier_gst_document" name="supplier_gst_document" type="file" accept=".pdf,image/jpeg,image/png,image/webp" />
                                            <ErrorMessage message={err("supplier_gst_document")} />
                                        </div>
                                        <Text label="GSTIN" name="gst_number" value={form.gst_number} change={change} error={err("gst_number")} required pattern="[0-9]{2}[A-Za-z]{5}[0-9]{4}[A-Za-z][1-9A-Za-z]Z[0-9A-Za-z]" minLength={15} maxLength={15} title="Enter a valid 15-character GSTIN, for example 09ABCDE1234F1Z5." />
                                    </div>
                                    <div className="distributor-document-preview">
                                        <div className="document-preview-heading"><span>Document preview</span><small>Click an image to zoom</small></div>
                                        <div className="document-preview-box" data-document-preview="gst">
                                            {supplierDocuments.gst
                                                ? (/\.(jpe?g|png|webp)(\?|$)/i.test(supplierDocuments.gst)
                                                    ? <img src={supplierDocuments.gst} alt="Saved GST document" />
                                                    : <a href={supplierDocuments.gst} target="_blank" rel="noreferrer">Open saved GST document</a>)
                                                : <span className="document-preview-empty">No GST document selected</span>}
                                        </div>
                                    </div>
                                </article>
                                <article className="distributor-document-card">
                                    <header><span>PAN</span><div><h4>PAN card</h4><p>Upload a clear PAN card image for automatic number extraction.</p></div></header>
                                    <div className="distributor-document-fields">
                                        <div className="form-field document-upload-field">
                                            <label className="label" htmlFor="supplier_pan_document">PAN document</label>
                                            <input className="input" id="supplier_pan_document" name="supplier_pan_document" type="file" accept=".pdf,image/jpeg,image/png,image/webp" />
                                            <ErrorMessage message={err("supplier_pan_document")} />
                                        </div>
                                        <Text label="PAN number" name="pan_number" value={form.pan_number} change={change} error={err("pan_number")} required pattern="[A-Za-z]{5}[0-9]{4}[A-Za-z]" minLength={10} maxLength={10} title="Enter a valid PAN, for example ABCDE1234F." />
                                    </div>
                                    <div className="distributor-document-preview">
                                        <div className="document-preview-heading"><span>Document preview</span><small>Click an image to zoom</small></div>
                                        <div className="document-preview-box" data-document-preview="pan">
                                            {supplierDocuments.pan
                                                ? (/\.(jpe?g|png|webp)(\?|$)/i.test(supplierDocuments.pan)
                                                    ? <img src={supplierDocuments.pan} alt="Saved PAN document" />
                                                    : <a href={supplierDocuments.pan} target="_blank" rel="noreferrer">Open saved PAN document</a>)
                                                : <span className="document-preview-empty">No PAN document selected</span>}
                                        </div>
                                    </div>
                                </article>
                            </div>
                        </>
                    )}
                </section>

                <section
                    data-step="2"
                    style={isDistributorType ? { order: 2 } : undefined}
                    className={step === 2
                        ? (isDistributorType
                            ? (activeDistributorAddress.startsWith("subsidiary-") ? "mt-3" : "hidden")
                            : "")
                        : "hidden"}
                >
                    {!isDistributorType && <>
                        <h3 className="font-semibold">Contact details</h3>
                        <p className="mb-4 text-sm text-slate-500">Add one or more employee contact details.</p>
                    </>}
                    {!isDistributorType && <>
                    <RecordsTable
                        columns={[
                            { key: "type", label: "Type", render: row => contactTypeLabels[row.type] },
                            { key: "value", label: "Contact", render: row => row.value },
                            { key: "primary", label: "Primary", render: row => row.is_primary ? "Yes" : "No" },
                        ]}
                        rows={contacts}
                        emptyMessage="No contacts added."
                        onEdit={index => focusRecord(`contact-record-${index}`)}
                        onRemove={index => setContacts(rows => rows.filter((_, i) => i !== index))}
                    />
                    <input type="hidden" name="contact_id" value={form.contact_id ?? ""} />
                        <div className="mt-4 space-y-4">
                            {contacts.map((row, index) => (
                                <div
                                    className={`contact-form-card rounded-xl border p-4 ${row.is_primary ? "is-primary border-cyan-200 bg-cyan-50/30" : "border-slate-200 bg-white"}`}
                                    key={index}
                                    id={`contact-record-${index}`}
                                >
                                    <input type="hidden" name={`contacts[${index}][id]`} value={row.id ?? ""} />
                                    <div className="contact-form-card-header">
                                        <div>
                                            <span>Contact {index + 1}</span>
                                            <strong>{contactTypeLabels[String(row.type)] ?? "Contact details"}</strong>
                                        </div>
                                        <div>
                                            <input type="hidden" name={`contacts[${index}][is_primary]`} value="0" />
                                            <label className="contact-primary-toggle">
                                                <input
                                                    type="checkbox"
                                                    name={`contacts[${index}][is_primary]`}
                                                    value="1"
                                                    checked={row.is_primary}
                                                    onChange={(e) => changeContact(index, "is_primary", e.target.checked)}
                                                />
                                                <span aria-hidden="true"></span>
                                                Primary contact
                                            </label>
                                        </div>
                                    </div>
                                    <div className="corporate-form-grid repeatable-form-grid contact-form-grid">
                                        <div className="form-field">
                                            <label className="label">
                                                Contact type
                                            </label>
                                            <select
                                                className="input"
                                                name={`contacts[${index}][type]`}
                                                value={row.type}
                                                onChange={(e) =>
                                                    changeContact(
                                                        index,
                                                        "type",
                                                        e.target.value,
                                                    )
                                                }
                                            >
                                                <option value="1" disabled={!(["1", "2"].includes(String(row.type))) && contacts.filter(item => ["1", "2"].includes(String(item.type))).length >= maxPhoneContacts}>
                                                    Mobile
                                                </option>
                                                <option value="2" disabled={!(["1", "2"].includes(String(row.type))) && contacts.filter(item => ["1", "2"].includes(String(item.type))).length >= maxPhoneContacts}>
                                                    Telephone
                                                </option>
                                                <option value="3" disabled={String(row.type) !== "3" && contacts.filter(item => String(item.type) === "3").length >= maxEmailContacts}>Email</option>
                                                <option value="4" disabled={String(row.type) !== "4" && contacts.filter(item => String(item.type) === "4").length >= 1}>
                                                    Emergency contact
                                                </option>
                                            </select>
                                        </div>
                                        <div className="form-field">
                                            <label className="label">
                                                Contact value
                                            </label>
                                            <input
                                                className="input"
                                                type={String(row.type) === "3" ? "email" : "tel"}
                                                name={`contacts[${index}][value]`}
                                                value={row.value}
                                                placeholder={String(row.type) === "3" ? "name@example.com" : "Enter contact number"}
                                                onChange={(e) =>
                                                    changeContact(
                                                        index,
                                                        "value",
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                        </div>
                                    </div>
                                </div>
                            ))}
                            <button
                                type="button"
                                className="btn-secondary"
                                onClick={addContact}
                                disabled={
                                    contacts.filter(row => ["1", "2"].includes(String(row.type))).length >= maxPhoneContacts
                                    && contacts.filter(row => String(row.type) === "3").length >= maxEmailContacts
                                    && contacts.filter(row => String(row.type) === "4").length >= 1
                                }
                            >
                                ＋ Add another contact
                            </button>
                        </div>
                    </>}
                    {isDistributorType && <div>
                        <div className="hidden">
                            <div>
                                <h4 className="font-semibold text-slate-800">Subsidiaries</h4>
                                <p className="text-sm text-slate-500">Add each subsidiary's contact and address details in one form.</p>
                            </div>
                            <button type="button" className="btn-primary" onClick={addSubsidiary}>＋ Add more subsidiary</button>
                        </div>
                        {subsidiaries.length === 0 ? (
                            <p className="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-4 text-sm text-slate-500">No subsidiaries added.</p>
                        ) : (
                            <div className="space-y-4">
                                <div className="hidden" role="tablist" aria-label="Subsidiaries">
                                    {subsidiaries.map((row, index) => <button
                                        key={`subsidiary-tab-${row.id ?? index}`}
                                        type="button"
                                        role="tab"
                                        aria-selected={activeSubsidiaryIndex === index}
                                        className={`address-tab ${activeSubsidiaryIndex === index ? "is-active" : ""}`}
                                        onClick={() => setActiveSubsidiaryIndex(index)}
                                    >
                                        <span className="block text-xs font-semibold uppercase tracking-wide">Subsidiary {index + 1}</span>
                                        <span className="mt-1 block max-w-44 truncate text-xs text-slate-500">{row.name || "Not completed"}</span>
                                    </button>)}
                                </div>
                                {subsidiaries.map((row, index) => <div className={`${activeDistributorAddress === `subsidiary-${index}` ? "block" : "hidden"} distributor-address-card distributor-subsidiary-card rounded-xl border border-slate-200 bg-white`} key={`subsidiary-${row.id ?? index}`}>
                                    <input type="hidden" name={`subsidiaries[${index}][id]`} value={row.id ?? ""} />
                                    <div className="subsidiary-card-heading flex items-center justify-between">
                                        <strong className="text-sm text-slate-800">Subsidiary {index + 1}</strong>
                                        <button type="button" className="rounded-lg border border-red-200 px-3 py-2 text-sm font-semibold text-red-600 hover:bg-red-50" onClick={() => removeSubsidiary(index)}>Remove</button>
                                    </div>
                                    <div className="address-details-split">
                                        <div className="address-form-block">
                                            <div className="corporate-form-grid repeatable-form-grid">
                                                <Text label="Subsidiary name" name={`subsidiaries[${index}][name]`} value={row.name} change={() => event => changeSubsidiary(index, "name", event.target.value)} error={err(`subsidiaries.${index}.name`)} />
                                                <Text label="Phone number" name={`subsidiaries[${index}][phone]`} type="tel" value={row.phone} change={() => event => changeSubsidiary(index, "phone", event.target.value)} error={err(`subsidiaries.${index}.phone`)} />
                                                <Text label="Email" name={`subsidiaries[${index}][email]`} type="email" value={row.email} change={() => event => changeSubsidiary(index, "email", event.target.value)} error={err(`subsidiaries.${index}.email`)} />
                                                <Text label="Address line 1" name={`subsidiaries[${index}][line_1]`} value={row.line_1} change={() => event => changeSubsidiary(index, "line_1", event.target.value)} error={err(`subsidiaries.${index}.line_1`)} />
                                                <Text label="Address line 2" name={`subsidiaries[${index}][line_2]`} value={row.line_2} change={() => event => changeSubsidiary(index, "line_2", event.target.value)} error={err(`subsidiaries.${index}.line_2`)} />
                                                <IndiaLocationFields
                                                    prefix={`subsidiaries[${index}]`}
                                                    value={row}
                                                    onChange={next => setSubsidiaries(rows => rows.map((item, rowIndex) => rowIndex === index ? next : item))}
                                                    errors={{
                                                        state: err(`subsidiaries.${index}.state`),
                                                        district: err(`subsidiaries.${index}.district`),
                                                        city: err(`subsidiaries.${index}.city`),
                                                        postal_code: err(`subsidiaries.${index}.postal_code`),
                                                    }}
                                                    required={false}
                                                />
                                            </div>
                                            <AddressCoordinates
                                                index={index}
                                                row={row}
                                                changeAddress={changeSubsidiary}
                                                prefix="subsidiaries"
                                                latitudeError={err(`subsidiaries.${index}.latitude`)}
                                                longitudeError={err(`subsidiaries.${index}.longitude`)}
                                                required={false}
                                            />
                                        </div>
                                        <div className="address-map-panel">
                                            <AddressMapPreview index={index} row={row} />
                                        </div>
                                    </div>
                                </div>)}
                            </div>
                        )}
                    </div>}
                </section>

                <section data-step={isDistributorType ? "2" : "3"} style={isDistributorType ? { order: 1 } : undefined} className={step === (isDistributorType ? 2 : 3) ? (isDistributorType ? "" : "mt-6 border-t border-slate-200 pt-5") : "hidden"}>
                    {!isDistributorType && <>
                    <div className="mb-4 flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h3 className="font-semibold">Address details</h3>
                            <p className="text-sm text-slate-500">Manage each address in its own tab.</p>
                        </div>
                        {addresses.length < maxAddresses && (
                            <button type="button" className="btn-primary" onClick={addAddress}>＋ Add address ({addresses.length}/{maxAddresses})</button>
                        )}
                    </div>
                    <div className="address-tab-list mb-4" role="tablist" aria-label="User addresses">
                        {addresses.map((row, index) => {
                            const active = index === activeAddressIndex;
                            return (
                                <button
                                    key={`address-tab-${row.id ?? index}`}
                                    type="button"
                                    role="tab"
                                    aria-selected={active}
                                    onClick={() => setActiveAddressIndex(index)}
                                    className={`address-tab ${active ? "is-active" : ""}`}
                                >
                                    <span className="block text-xs font-semibold uppercase tracking-wide">Address {index + 1}</span>
                                    <span className="mt-1 block text-sm font-bold">{addressLabel(row)}</span>
                                    <span className="mt-1 block max-w-44 truncate text-xs text-slate-500">{[row.city, row.postal_code].filter(Boolean).join(" · ") || "Not completed"}</span>
                                </button>
                            );
                        })}
                    </div>
                    </>}
                    {isDistributorType && <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <div className="address-tab-list flex-1" role="tablist" aria-label="Distributor addresses">
                            <button
                                type="button"
                                role="tab"
                                aria-selected={activeDistributorAddress === "main"}
                                className={`address-tab ${activeDistributorAddress === "main" ? "is-active" : ""}`}
                                onClick={() => setActiveDistributorAddress("main")}
                            >
                                <span className="block text-xs font-semibold uppercase tracking-wide">Distributor</span>
                                <span className="mt-1 block max-w-44 truncate text-xs text-slate-500">Primary address</span>
                            </button>
                            {subsidiaries.map((row, index) => <button
                                key={`unified-subsidiary-tab-${row.id ?? index}`}
                                type="button"
                                role="tab"
                                aria-selected={activeDistributorAddress === `subsidiary-${index}`}
                                className={`address-tab ${activeDistributorAddress === `subsidiary-${index}` ? "is-active" : ""}`}
                                onClick={() => {
                                    setActiveSubsidiaryIndex(index);
                                    setActiveDistributorAddress(`subsidiary-${index}`);
                                }}
                            >
                                <span className="block text-xs font-semibold uppercase tracking-wide">Subsidiary {index + 1}</span>
                                <span className="mt-1 block max-w-44 truncate text-xs text-slate-500">{row.name || "Not completed"}</span>
                            </button>)}
                        </div>
                        <button type="button" className="btn-primary" onClick={addSubsidiary}>＋ Add subsidiary</button>
                    </div>}
                    <input type="hidden" name="address_id" value={form.address_id ?? ""} />
                        <div className="mt-4 space-y-4">
                            {addresses.map((row, index) => (
                                <div
                                    className={`${index === activeAddressIndex && (!isDistributorType || activeDistributorAddress === "main") ? "block" : "hidden"} ${isDistributorType ? "distributor-address-card" : ""} rounded-xl border border-slate-200 bg-white p-5 shadow-sm`}
                                    key={index}
                                    id={`address-record-${index}`}
                                    role="tabpanel"
                                >
                                    {!isDistributorType && <div className="mb-4 flex items-center justify-between border-b border-slate-100 pb-3">
                                        <div><span className="text-xs font-semibold uppercase tracking-wide text-cyan-700">Address {index + 1}</span><h4 className="font-semibold text-slate-800">{addressLabel(row)} address</h4></div>
                                        {addresses.length > 1 && <button type="button" className="rounded-lg border border-red-200 px-3 py-2 text-sm font-semibold text-red-600 hover:bg-red-50" onClick={() => removeAddress(index)}>Remove address</button>}
                                    </div>}
                                    <input type="hidden" name={`addresses[${index}][id]`} value={row.id ?? ""} />
                                    <div className="address-details-split">
                                    <div className="address-form-block">
                                        {!isDistributorType && <h5 className="address-form-block-title">Address information</h5>}
                                        <div className="corporate-form-grid repeatable-form-grid">
                                        <div className="form-field">
                                            <label className="label">Address type</label>
                                            <select
                                                className="input"
                                                name={`addresses[${index}][type]`}
                                                value={row.type}
                                                onChange={(e) => changeAddress(index, "type", e.target.value)}
                                            >
                                                {isDistributorType ? <>
                                                    <option value="1">Local</option>
                                                    <option value="2">Permanent</option>
                                                    <option value="3">Official</option>
                                                    <option value="5">Subsidiary</option>
                                                </> : <>
                                                    <option value="1">Local</option>
                                                    <option value="2" disabled={String(row.type) !== "2" && addresses.some(item => String(item.type) === "1" && item.is_current_permanent_same)}>Permanent</option>
                                                </>}
                                            </select>
                                        </div>
                                        {isDistributorType && index === 0 && <>
                                            <input type="hidden" name="contact_id" value={form.contact_id ?? ""} />
                                            <input type="hidden" name="contacts[0][id]" value={distributorPhone.id ?? ""} />
                                            <input type="hidden" name="contacts[0][type]" value={distributorPhone.type ?? "1"} />
                                            <input type="hidden" name="contacts[0][is_primary]" value="1" />
                                            <Text
                                                label="Primary phone number"
                                                name="contacts[0][value]"
                                                type="tel"
                                                value={distributorPhone.value ?? ""}
                                                change={() => event => changeDistributorContact("phone", event.target.value)}
                                                error={err("contacts.0.value")}
                                                required
                                                pattern="[+]?[0-9 ()-]{7,20}"
                                                maxLength={20}
                                                inputMode="tel"
                                                title="Enter a valid phone number using 7 to 20 digits."
                                            />
                                            <input type="hidden" name="contacts[1][id]" value={distributorEmail.id ?? ""} />
                                            <input type="hidden" name="contacts[1][type]" value="3" />
                                            <input type="hidden" name="contacts[1][is_primary]" value="1" />
                                            <Text
                                                label="Primary email address"
                                                name="contacts[1][value]"
                                                type="email"
                                                value={distributorEmail.value ?? ""}
                                                change={() => event => changeDistributorContact("email", event.target.value)}
                                                error={err("contacts.1.value")}
                                                required
                                                maxLength={255}
                                                autoComplete="email"
                                            />
                                        </>}
                                        {!isDistributorType && String(row.type) === "1" && (
                                            <div className="form-field flex items-center">
                                                <input type="hidden" name={`addresses[${index}][is_current_permanent_same]`} value="0" />
                                                <label className="flex w-full cursor-pointer items-center gap-3 rounded-xl border border-cyan-100 bg-cyan-50 px-4 py-3 text-sm font-semibold text-slate-700">
                                                    <input
                                                        type="checkbox"
                                                        name={`addresses[${index}][is_current_permanent_same]`}
                                                        value="1"
                                                        checked={Boolean(row.is_current_permanent_same)}
                                                        onChange={event => setCurrentPermanentSame(index, event.target.checked)}
                                                        className="h-5 w-5 rounded border-slate-300 text-cyan-600"
                                                    />
                                                    Local and permanent address are the same
                                                </label>
                                            </div>
                                        )}
                                        {[
                                            ["line_1", "Address line 1"],
                                            ["line_2", "Address line 2"],
                                        ].map(([key, title]) => (
                                            <div
                                                className="form-field"
                                                key={key}
                                            >
                                                <label className="label">
                                                    {title}
                                                </label>
                                                <input
                                                    className="input"
                                                    name={`addresses[${index}][${key}]`}
                                                    value={row[key]}
                                                    onChange={(e) =>
                                                        changeAddress(
                                                            index,
                                                            key,
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                            </div>
                                        ))}
                                        <IndiaLocationFields
                                            prefix={`addresses[${index}]`}
                                            value={row}
                                            onChange={next => setAddresses(rows => rows.map((item, i) => i === index ? next : item))}
                                            errors={{
                                                state: err(`addresses.${index}.state`),
                                                district: err(`addresses.${index}.district`),
                                                city: err(`addresses.${index}.city`),
                                                postal_code: err(`addresses.${index}.postal_code`),
                                            }}
                                        />
                                        </div>
                                        <AddressCoordinates
                                            index={index}
                                            row={row}
                                            changeAddress={changeAddress}
                                            latitudeError={err(`addresses.${index}.latitude`)}
                                            longitudeError={err(`addresses.${index}.longitude`)}
                                        />
                                    </div>
                                    <div className="address-map-panel">
                                        <AddressMapPreview index={index} row={row} />
                                    </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                </section>

                <section data-step="4" className={step === 4 ? "" : "hidden"}>
                    <h3 className="font-semibold">
                        Education{" "}
                        <span className="text-sm font-normal text-slate-500">
                            (optional)
                        </span>
                    </h3>
                    <p className="mb-4 text-sm text-slate-500">
                        Leave empty to skip.
                    </p>
                    <RecordsTable
                        columns={[
                            { key: "level", label: "Level", render: row => row.level },
                            { key: "degree", label: "Degree", render: row => row.degree_name },
                            { key: "institution", label: "Institution", render: row => row.institution_name },
                            { key: "university", label: "University", render: row => row.university_name },
                            { key: "dates", label: "Dates", render: row => row.is_current ? `${row.start_date || "—"} – Present` : [row.start_date, row.end_date].filter(Boolean).join(" – ") },
                        ]}
                        rows={[{
                            key: "primary",
                            level: form.new_education_level,
                            degree_name: form.new_degree_name,
                            institution_name: form.new_institution_name,
                            university_name: form.new_university_name,
                            start_date: form.new_education_start_date,
                            end_date: form.new_education_end_date,
                            is_current: form.new_education_is_current === "1",
                        }, ...additionalEducations.map((row, index) => ({ ...row, key: `additional-${index}` }))]}
                        emptyMessage="No education added."
                        onEdit={index => focusRecord(index === 0 ? "education-record-primary" : `education-record-${index - 1}`)}
                        onRemove={index => {
                            if (index === 0) setForm(current => ({ ...current, new_education_level: "", new_degree_name: "", new_institution_name: "", new_university_name: "", new_education_start_date: "", new_education_end_date: "", new_education_grade: "", new_education_is_current: "0" }));
                            else setAdditionalEducations(rows => rows.filter((_, rowIndex) => rowIndex !== index - 1));
                        }}
                    />
                    <div className="corporate-form-grid" id="education-record-primary">
                        <Text
                            label="Education level"
                            name="new_education_level"
                            value={form.new_education_level}
                            change={change}
                            error={err("new_education_level")}
                        />
                        <Text
                            label="Degree name"
                            name="new_degree_name"
                            value={form.new_degree_name}
                            change={change}
                            error={err("new_degree_name")}
                        />
                        <Text
                            label="Institution name"
                            name="new_institution_name"
                            value={form.new_institution_name}
                            change={change}
                            error={err("new_institution_name")}
                        />
                        <Text
                            label="University name"
                            name="new_university_name"
                            value={form.new_university_name}
                            change={change}
                            error={err("new_university_name")}
                        />
                        <Text
                            label="Start date"
                            name="new_education_start_date"
                            type="date"
                            value={form.new_education_start_date}
                            change={change}
                            error={err("new_education_start_date")}
                        />
                        <Text
                            label="End date"
                            name="new_education_end_date"
                            type="date"
                            value={form.new_education_end_date}
                            change={change}
                            error={err("new_education_end_date")}
                        />
                        <Text
                            label="Grade"
                            name="new_education_grade"
                            value={form.new_education_grade}
                            change={change}
                            error={err("new_education_grade")}
                        />
                        <div className="form-field">
                            <input
                                type="hidden"
                                name="new_education_is_current"
                                value="0"
                            />
                            <label className="flex gap-2">
                                <input
                                    type="checkbox"
                                    name="new_education_is_current"
                                    value="1"
                                    checked={
                                        form.new_education_is_current === "1"
                                    }
                                    onChange={(event) =>
                                        setForm({
                                            ...form,
                                            new_education_is_current: event
                                                .target.checked
                                                ? "1"
                                                : "0",
                                        })
                                    }
                                />
                                Currently studying
                            </label>
                        </div>
                        <div className="form-field form-field-wide education-upload-field">
                            <label className="label" htmlFor="new_education_document">
                                Education document / image
                            </label>
                            <input
                                className="input"
                                id="new_education_document"
                                name="new_education_document"
                                type="file"
                                accept=".pdf,image/jpeg,image/png,image/webp"
                            />
                            <p className="education-upload-help">
                                Upload a PDF, JPG, PNG or WebP file up to 10 MB.
                            </p>
                            <ErrorMessage message={err("new_education_document")} />
                        </div>
                    </div>
                    {additionalEducations.map((education, index) => (
                        <div className="additional-education-card" id={`education-record-${index}`} key={`education-${index}`}>
                            <input type="hidden" name={`additional_education[${index}][id]`} value={education.id ?? ""} />
                            <div className="additional-education-heading">
                                <h4>Additional education {index + 1}</h4>
                                <button
                                    type="button"
                                    onClick={() =>
                                        setAdditionalEducations((rows) =>
                                            rows.filter((_, rowIndex) => rowIndex !== index),
                                        )
                                    }
                                >
                                    Remove
                                </button>
                            </div>
                            <div className="corporate-form-grid">
                                <Text label="Education level" name={`additional_education[${index}][level]`} value={education.level} change={() => changeEducation(index, "level")} error={err(`additional_education.${index}.level`)} />
                                <Text label="Degree name" name={`additional_education[${index}][degree_name]`} value={education.degree_name} change={() => changeEducation(index, "degree_name")} error={err(`additional_education.${index}.degree_name`)} />
                                <Text label="Institution name" name={`additional_education[${index}][institution_name]`} value={education.institution_name} change={() => changeEducation(index, "institution_name")} error={err(`additional_education.${index}.institution_name`)} />
                                <Text label="University name" name={`additional_education[${index}][university_name]`} value={education.university_name} change={() => changeEducation(index, "university_name")} error={err(`additional_education.${index}.university_name`)} />
                                <Text label="Start date" name={`additional_education[${index}][start_date]`} type="date" value={education.start_date} change={() => changeEducation(index, "start_date")} error={err(`additional_education.${index}.start_date`)} />
                                <Text label="End date" name={`additional_education[${index}][end_date]`} type="date" value={education.end_date} change={() => changeEducation(index, "end_date")} error={err(`additional_education.${index}.end_date`)} />
                                <Text label="Grade" name={`additional_education[${index}][grade]`} value={education.grade} change={() => changeEducation(index, "grade")} error={err(`additional_education.${index}.grade`)} />
                                <div className="form-field"><label className="flex gap-2"><input type="checkbox" name={`additional_education[${index}][is_current]`} value="1" checked={education.is_current} onChange={changeEducation(index, "is_current")} /> Currently studying</label></div>
                                <div className="form-field form-field-wide education-upload-field">
                                    <label className="label" htmlFor={`additional_education_${index}_document`}>Education document / image</label>
                                    <input className="input" id={`additional_education_${index}_document`} name={`additional_education[${index}][document]`} type="file" accept=".pdf,image/jpeg,image/png,image/webp" />
                                    {education.document_url && <p className="education-upload-help"><a href={education.document_url} target="_blank" rel="noreferrer">View current document</a></p>}
                                    <ErrorMessage message={err(`additional_education.${index}.document`)} />
                                </div>
                            </div>
                        </div>
                    ))}
                    <button className="education-add-more" type="button" onClick={addEducation}>＋ Add more education</button>
                </section>

                {isDistributorType && <section data-step="6" className={`${step === 6 ? "" : "hidden"} distributor-commercial-section`}>
                    <h3 className="font-semibold">Commercial &amp; banking details</h3>
                    <p className="mb-4 text-sm text-slate-500">Configure coverage, commercial terms and settlement account details.</p>
                    <div className="corporate-form-grid commercial-terms-grid">
                        <Text label="Credit limit" name="distributor_credit_limit" type="number" value={form.distributor_credit_limit} change={change} error={err("distributor_credit_limit")} min="0" />
                        <Text label="Credit period (days)" name="distributor_credit_period_days" type="number" value={form.distributor_credit_period_days} change={change} error={err("distributor_credit_period_days")} min="0" max="3650" />
                        <Text label="Payment terms" name="distributor_payment_terms" value={form.distributor_payment_terms} change={change} error={err("distributor_payment_terms")} />
                        <Text label="Price list" name="distributor_price_list" value={form.distributor_price_list} change={change} error={err("distributor_price_list")} />
                        <Select label="Currency" name="distributor_currency" value={form.distributor_currency} change={change} error={err("distributor_currency")} options={[{ value: "INR", label: "INR — Indian Rupee" }, { value: "USD", label: "USD — US Dollar" }, { value: "EUR", label: "EUR — Euro" }]} required />
                        <Text label="Tax classification" name="distributor_tax_classification" value={form.distributor_tax_classification} change={change} error={err("distributor_tax_classification")} />
                    </div>
                    <div className="distributor-banking-panel">
                    <div className="distributor-section-heading"><h4>Banking details</h4><p>Settlement account and verification document.</p></div>
                    <div className="corporate-form-grid banking-details-grid">
                        <Text label="Bank name" name="distributor_bank_name" value={form.distributor_bank_name} change={change} error={err("distributor_bank_name")} required />
                        <Text label="Account holder name" name="distributor_account_holder_name" value={form.distributor_account_holder_name} change={change} error={err("distributor_account_holder_name")} required />
                        <Text label="Account number" name="distributor_account_number" value={form.distributor_account_number} change={change} error={err("distributor_account_number")} required pattern="[0-9]{6,35}" minLength={6} maxLength={35} inputMode="numeric" title="Enter a valid bank account number containing 6 to 35 digits." />
                        <Text label="IFSC code" name="distributor_ifsc_code" value={form.distributor_ifsc_code} change={change} error={err("distributor_ifsc_code")} required pattern="[A-Za-z]{4}0[A-Za-z0-9]{6}" minLength={11} maxLength={11} title="Enter a valid 11-character IFSC code, for example SBIN0001234." />
                        <Text label="Branch" name="distributor_bank_branch" value={form.distributor_bank_branch} change={change} error={err("distributor_bank_branch")} />
                        <div className="form-field">
                            <input className="input" id="distributor_cancelled_cheque" name="distributor_cancelled_cheque" type="file" accept=".pdf,image/jpeg,image/png,image/webp" />
                            {distributorCancelledChequeUrl && <p className="mt-2 text-xs"><a className="text-cyan-700" href={distributorCancelledChequeUrl} target="_blank" rel="noreferrer">View current cancelled cheque</a></p>}
                            <ErrorMessage message={err("distributor_cancelled_cheque")} />
                        </div>
                    </div>
                    </div>
                </section>}

                <section data-step="5" className={step === 5 ? "" : "hidden"}>
                    <div className="mb-4 flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h3 className="font-semibold">Login credentials</h3>
                            <p className="text-sm text-slate-500">
                                {method === "POST" ? "Enter the username and password used to sign in." : "Update account access without changing the existing password."}
                            </p>
                        </div>
                        {method !== "POST" && (
                            <button
                                type="button"
                                className={changePassword ? "btn-secondary" : "btn-primary"}
                                onClick={() => {
                                    setChangePassword(current => !current);
                                    setForm(current => ({ ...current, password: "", password_confirmation: "" }));
                                }}
                            >
                                {changePassword ? "Cancel password change" : "Change password"}
                            </button>
                        )}
                    </div>
                    <div className="corporate-form-grid">
                        <Text
                            label="Username"
                            name="username"
                            value={form.username}
                            change={change}
                            error={err("username")}
                            required
                        />
                        {changePassword && <>
                            <Text
                                label={method === "POST" ? "Password" : "New password"}
                                name="password"
                                type="password"
                                value={form.password}
                                change={change}
                                error={err("password")}
                                required
                                autoComplete="new-password"
                            />
                            <Text
                                label="Confirm password"
                                name="password_confirmation"
                                type="password"
                                value={form.password_confirmation}
                                change={change}
                                error={err("password_confirmation")}
                                required
                                autoComplete="new-password"
                            />
                        </>}
                        <Select
                            label="Verification status"
                            name="is_verified"
                            value={form.is_verified}
                            change={change}
                            error={err("is_verified")}
                            options={options.is_verified}
                            required
                        />
                        <Select
                            label="Status"
                            name="status"
                            value={form.status}
                            change={change}
                            error={err("status")}
                            options={options.status}
                            required
                        />
                    </div>
                </section>

                <footer style={isDistributorType && step === 2 ? { order: 3 } : undefined} className="user-modal-footer mt-6">
                    <button
                        type="button"
                        className="btn-secondary"
                        onClick={() => {
                            if (!step) return closeForm();
                            const currentIndex = wizardStepIndexes.indexOf(step);
                            setStep(wizardStepIndexes[Math.max(0, currentIndex - 1)]);
                        }}
                    >
                        {step ? "← Back" : "Cancel"}
                    </button>
                    {step !== 5 ? (
                        <button
                            type="button"
                            className="btn-primary"
                            onClick={next}
                        >
                            Next →
                        </button>
                    ) : (
                        <button
                            type="submit"
                            className="btn-primary"
                            disabled={saving}
                            onClick={() => {
                                explicitSubmitRef.current = true;
                            }}
                        >
                            {saving
                                ? "Saving…"
                                : method === "POST"
                                  ? (distributorMode ? "Create distributor" : "Create employee")
                                  : (distributorMode ? "Update distributor" : "Update employee")}
                        </button>
                    )}
                </footer>
            </form>
        </div>
    );
}
