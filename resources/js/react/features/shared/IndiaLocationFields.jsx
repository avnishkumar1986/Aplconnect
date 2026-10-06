import { useEffect, useMemo, useState } from "react";

const base = "/admin/india-locations";

function SearchableField({ label, name, value, options, onChange, error, required, disabled = false, placeholder }) {
    const [open, setOpen] = useState(false);
    const filtered = useMemo(() => {
        const search = String(value ?? "").trim().toLocaleLowerCase();
        return (search ? options.filter(option => option.toLocaleLowerCase().includes(search)) : options).slice(0, 150);
    }, [options, value]);

    return <div className="form-field">
        <label className="label" htmlFor={name}>{label}{required && <span className="text-red-600"> *</span>}</label>
        <div className="relative">
            <input className="input pr-9" id={name} name={name} value={value ?? ""}
                onChange={event => { onChange(event.target.value); setOpen(true); }}
                onFocus={() => setOpen(true)} onBlur={() => setOpen(false)}
                onKeyDown={event => { if (event.key === "Escape") setOpen(false); }}
                placeholder={placeholder ?? `Search ${label.toLowerCase()}`}
                autoComplete="off" role="combobox" aria-expanded={open} aria-autocomplete="list"
                required={required} disabled={disabled}/>
            <button type="button" tabIndex="-1" disabled={disabled} aria-label={`Show ${label.toLowerCase()} options`}
                className="absolute inset-y-0 right-0 px-3 text-slate-500" onMouseDown={event => event.preventDefault()} onClick={() => setOpen(current => !current)}>▾</button>
            {open && !disabled ? <div className="absolute z-50 mt-1 max-h-56 w-full overflow-auto rounded-lg border border-slate-200 bg-white py-1 shadow-lg" role="listbox">
                {filtered.length ? filtered.map(option => <button type="button" role="option" aria-selected={option === value} key={option}
                    className="block w-full px-3 py-2 text-left text-sm hover:bg-cyan-50"
                    onMouseDown={event => { event.preventDefault(); onChange(option); setOpen(false); }}>{option}</button>)
                    : <p className="px-3 py-2 text-sm text-slate-500">No matching {label.toLowerCase()}</p>}
            </div> : null}
        </div>
        {error ? <p className="mt-1 text-sm text-red-600">{error}</p> : null}
    </div>;
}

async function load(path, params = {}, signal) {
    const query = new URLSearchParams(params);
    const response = await fetch(`${base}/${path}${query.size ? `?${query}` : ""}`, { headers: { Accept: "application/json" }, signal });
    if (!response.ok) return [];
    return response.json();
}

export default function IndiaLocationFields({ prefix, value, onChange, errors = {}, required = true }) {
    const [states, setStates] = useState([]), [districts, setDistricts] = useState([]), [cities, setCities] = useState([]), [postalCodes, setPostalCodes] = useState([]);
    useEffect(() => { const controller = new AbortController(); load("states", {}, controller.signal).then(setStates).catch(() => {}); return () => controller.abort(); }, []);
    useEffect(() => { if (!value.state) return setDistricts([]); const controller = new AbortController(), timer = setTimeout(() => load("districts", { state: value.state }, controller.signal).then(setDistricts).catch(() => {}), 200); return () => { clearTimeout(timer); controller.abort(); }; }, [value.state]);
    useEffect(() => { if (!value.state || !value.district) return setCities([]); const controller = new AbortController(), timer = setTimeout(() => load("cities", { state: value.state, district: value.district }, controller.signal).then(setCities).catch(() => {}), 200); return () => { clearTimeout(timer); controller.abort(); }; }, [value.state, value.district]);
    useEffect(() => { if (!value.state || !value.district || !value.city) return setPostalCodes([]); const controller = new AbortController(), timer = setTimeout(() => load("postal-codes", { state: value.state, district: value.district, city: value.city }, controller.signal).then(setPostalCodes).catch(() => {}), 200); return () => { clearTimeout(timer); controller.abort(); }; }, [value.state, value.district, value.city]);
    const set = (key, next) => {
        const cleared = key === "state" ? { district: "", city: "", postal_code: "" } : key === "district" ? { city: "", postal_code: "" } : key === "city" ? { postal_code: "" } : {};
        onChange({ ...value, ...cleared, [key]: next, country: "India" });
    };
    return <>
        <SearchableField label="State" name={`${prefix}[state]`} value={value.state} options={states} onChange={next => set("state", next)} error={errors.state} required={required}/>
        <SearchableField label="District" name={`${prefix}[district]`} value={value.district} options={districts} onChange={next => set("district", next)} error={errors.district} disabled={!value.state} placeholder={value.state ? "Search district" : "Select state first"}/>
        <SearchableField label="City" name={`${prefix}[city]`} value={value.city} options={cities} onChange={next => set("city", next)} error={errors.city} required={required} disabled={!value.district} placeholder={value.district ? "Search city" : "Select district first"}/>
        <SearchableField label="Postal code" name={`${prefix}[postal_code]`} value={value.postal_code} options={postalCodes} onChange={next => set("postal_code", next)} error={errors.postal_code} required={required} disabled={!value.city} placeholder={value.city ? "Search postal code" : "Select city first"}/>
        <input type="hidden" name={`${prefix}[country]`} value="India" />
    </>;
}
