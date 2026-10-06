@extends('layout.app')

@php
    $editing = (bool) $record;
    $recordCompanyCode = $record ? optional($companies->firstWhere('id', $record->company_id))->company_code : null;
    $recordPlantCode = $record ? optional($plants->firstWhere('id', $record->plant_id))->company_code : null;
    $selectedCompanyCode = old('company_id', $recordCompanyCode ?? $companies->first()?->company_code);
    $selectedPlantCode = old('plant_id', $recordPlantCode);
@endphp

@section('title', $editing ? 'Edit Daily Consumption' : 'Add Daily Consumption')
<style>
/* Global native dropdown styling */
select:not([multiple]):not([size]),
select.input:not([multiple]):not([size]),
select.form-control:not([multiple]):not([size]),
select.form-select:not([multiple]):not([size]) {
    box-sizing: border-box;
    width: 100%;
    min-width: 0;
    height: 42px;
    min-height: 42px;
    margin: 0;
    padding: 9px 38px 9px 12px;

    font-family: inherit;
    font-size: 13px;
    font-weight: 400;
    line-height: 1.5;
    color: #334155;

    background-color: #fff;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 12px center;
    background-size: 16px;

    border: 1px solid #cbd5e1;
    border-radius: 8px;
    box-shadow: none;

    appearance: none;
    -webkit-appearance: none;
    cursor: pointer;
    transition: border-color .15s ease, box-shadow .15s ease;
}

select:not([multiple]):not([size]):hover:not(:disabled) {
    border-color: #94a3b8;
}

select:not([multiple]):not([size]):focus {
    outline: none;
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgb(37 99 235 / 12%);
}

select:not([multiple]):not([size]):disabled {
    color: #64748b;
    background-color: #f1f5f9;
    border-color: #e2e8f0;
    cursor: not-allowed;
    opacity: 1;
}

select.is-invalid,
select[aria-invalid="true"] {
    border-color: #dc2626;
}

/* Keep table page-size dropdowns compact. */
.dataTables_length select,
.dt-length select,
.datatable-selector {
    width: auto !important;
    min-width: 76px;
}
</style>

@section('content')
    <x-page-header :title="$editing ? 'Edit Daily Consumption' : 'Add Daily Consumption'" description="Maintain the plant material consumption plan used for stock projections."><span class="crud-breadcrumb">Procurement <b>/</b> Daily Consumption <b>/</b> {{ $editing ? 'Edit' : 'Add' }}</span></x-page-header>
    @if ($errors->any())
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-red-700">
            {{ $errors->first() }}
        </div>
    @endif

    <form class="card consumption-record-form"
          method="POST"
          action="{{ $editing ? route('admin.procurement.consumptions.update', $record->id) : route('admin.procurement.consumptions.store') }}"
          data-procurement-form
          data-material-form
          data-stock-map="{{ json_encode($stockMap) }}">
        @csrf

        @if ($editing)
            @method('PUT')
        @else
            <input type="hidden"
                   name="submission_token"
                   value="{{ old('submission_token', $token) }}">
        @endif

        {{-- Hidden hooks retained for existing stock-related JavaScript. --}}
        <input type="hidden" data-current-stock>
        <input type="hidden" data-stock-shortage>

        <div class="corporate-form-grid">
            <div class="form-field">
                <label class="label" for="consumption-company">Company</label>

                <select class="input"
                        id="consumption-company"
                        name="company_id"
                        required
                        data-company
                        data-searchable-select>
                    <option value="">— Select company —</option>

                    @foreach ($companies as $company)
                        <option value="{{ $company->company_code }}"
                                @selected($selectedCompanyCode == $company->company_code)>
                            {{ $company->company_code }} - {{ $company->company_name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="form-field">
                <label class="label" for="consumption-plant">Plant</label>

                <select class="input"
                        id="consumption-plant"
                        name="plant_id"
                        required
                        data-plant
                        data-searchable-select
                        data-selected="{{ $selectedPlantCode }}">
                    <option value="">— Select company first —</option>

                    @foreach ($plants as $plant)
                        <option value="{{ $plant->company_code }}"
                                data-company-code="{{ $plant->parent_company_code }}"
                                data-company-id="{{ $plant->parent_company_code }}"
                                @selected($selectedPlantCode == $plant->company_code)>
                            {{ $plant->company_code }} - {{ $plant->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="form-field">
                <label class="label" for="consumption-material">Material</label>

                <select class="input"
                        id="consumption-material"
                        name="material"
                        required
                        data-material
                        data-searchable-select
                        data-selected="{{ old('material', $record?->material) }}">
                    <option value="">— Select plant first —</option>

                    @foreach ($materials as $material)
                        <option value="{{ $material->matnr }}"
                                data-plant-code="{{ $material->plant_code }}"
                                @selected(old('material', $record?->material) == $material->matnr)>
                            {{ $material->matnr }}{{ $material->maktx ? ' - '.$material->maktx : '' }}{{ $material->matkl ? ' · '.$material->matkl : '' }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="form-field">
                <label class="label" for="consumption-period-type">Consumption type</label>
                <select class="input" id="consumption-period-type" name="period_type" data-consumption-period required>
                    <option value="">— Select consumption type —</option>
                    <option value="monthly" @selected(old('period_type', $record?->period_type ?? '') === 'monthly')>Monthly</option>
                    <option value="datewise" @selected(old('period_type', $record?->period_type ?? '') === 'datewise')>Date-wise</option>
                </select>
            </div>

            @php
                $datewiseRows = old('datewise_entries', [[
                    'from' => $record?->consumption_date ?? now()->toDateString(),
                    'until' => $record?->effective_to ?? '',
                    'quantity_mt' => $record?->quantity_mt ?? '',
                ]]);
            @endphp
            <div class="form-field form-field-wide hidden datewise-entry-section" data-datewise-period>
                <div class="datewise-entry-header">
                    <div>
                        <span class="label">Date-wise consumption</span>
                        <small>Enter an effective date range and its consumption quantity.</small>
                    </div>
                    @unless($editing)
                        <button class="btn-secondary datewise-add-button" type="button" data-add-datewise>
                            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                            Add another period
                        </button>
                    @endunless
                </div>
                <div class="datewise-entry-list" data-datewise-rows>
                    @foreach($datewiseRows as $index => $row)
                        <div class="datewise-entry-row" data-datewise-row>
                            <div>
                                <label class="label">Effective from</label>
                                <input class="input" type="date" name="datewise_entries[{{ $index }}][from]"
                                       value="{{ $row['from'] ?? '' }}" @unless($editing) min="{{ now()->toDateString() }}" @endunless data-datewise-from>
                            </div>
                            <div>
                                <label class="label">Effective until</label>
                                <input class="input" type="date" name="datewise_entries[{{ $index }}][until]"
                                       value="{{ $row['until'] ?? '' }}" min="{{ $row['from'] ?? now()->toDateString() }}" data-datewise-until>
                            </div>
                            <div>
                                <label class="label">Consumption quantity (MT)</label>
                                <input class="input" type="number" min="0.001" step="0.001"
                                       name="datewise_entries[{{ $index }}][quantity_mt]" value="{{ $row['quantity_mt'] ?? '' }}" data-datewise-quantity>
                            </div>
                            @unless($editing)
                                <button class="datewise-remove" type="button" title="Remove row" aria-label="Remove date-wise row" data-remove-datewise>×</button>
                            @endunless
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="form-field" data-monthly-period>
                <label class="label" for="consumption-monthly-date">Consumption date</label>
                <input class="input"
                       id="consumption-monthly-date"
                       name="consumption_date"
                       type="date"
                       value="{{ old('consumption_date', $record?->consumption_date ?? now()->toDateString()) }}"
                       @unless($editing) min="{{ now()->toDateString() }}" @endunless
                       data-monthly-date>
                <small class="mt-1 block text-xs text-slate-500">Select the effective date from the calendar.</small>
            </div>

            <div class="form-field" data-monthly-period>
                <label class="label" for="consumption-monthly-days">Number of days</label>
                <input class="input bg-slate-50" id="consumption-monthly-days" value="" readonly data-monthly-days>
                <small class="mt-1 block text-xs text-slate-500">Remaining calendar days including today.</small>
            </div>

            <div class="form-field" data-monthly-period>
                <label class="label" for="consumption-quantity">
                    Consumption quantity (MT)
                </label>

                <input class="input"
                       id="consumption-quantity"
                       name="quantity_mt"
                       type="number"
                       min="0.001"
                       step="0.001"
                       value="{{ old('quantity_mt', $record?->quantity_mt) }}"
                       required>
                <small class="mt-1 block text-xs text-slate-500">Carries forward until a newer quantity is entered.</small>
            </div>

            @if ($editing)
                <div class="form-field">
                    <span class="label">Status</span>

                    <input type="hidden" name="status" value="0">

                    <label class="inline-flex cursor-pointer items-center gap-3">
                        <input class="peer sr-only"
                               type="checkbox"
                               name="status"
                               value="1"
                               @checked(old('status', $record->status))>

                        <span class="relative h-6 w-11 rounded-full bg-slate-300 transition peer-checked:bg-emerald-500 after:absolute after:left-1 after:top-1 after:h-4 after:w-4 after:rounded-full after:bg-white after:transition peer-checked:after:translate-x-5"></span>

                        <span class="text-sm font-semibold text-slate-700">
                            Active
                        </span>
                    </label>
                </div>
            @endif

            <div class="form-field form-field-wide">
                <label class="label" for="consumption-remarks">Remarks</label>

                <textarea class="input"
                          id="consumption-remarks"
                          name="remarks"
                          maxlength="1000"
                          rows="3"
                          placeholder="Enter remarks (optional)">{{ old('remarks', $record?->remarks) }}</textarea>
            </div>
        </div>

        <div class="consumption-form-actions">
            <a class="btn-secondary"
               href="{{ route('admin.procurement.consumptions') }}">
                Cancel
            </a>

            <button type="submit" class="btn-primary">
                {{ $editing ? 'Update consumption' : 'Save consumption' }}
            </button>
        </div>
</form>
@endsection
