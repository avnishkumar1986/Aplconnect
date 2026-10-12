@extends('layout.app')
@section('title', $record->exists ? 'Edit distributor tier' : 'Create distributor tier')
@section('content')
    <form class="card corporate-record-form w-full p-4 sm:p-6" method="POST"
        action="{{ $record->exists ? route('admin.distributor-tiers.update', $record) : route('admin.distributor-tiers.store') }}">
        @csrf @if ($record->exists)
            @method('PUT')
        @endif
        @if ($errors->any())
            <div class="form-error-alert"><span class="form-error-alert-icon">!</span>
                <div><strong>Unable to save</strong>
                    <p>Please correct the highlighted fields.</p>
                </div>
            </div>
        @endif
        <div class="corporate-form-grid">
            <div class="form-field"><label class="label" for="code">Tier code <span
                        class="text-red-600">*</span></label><input class="input" id="code" name="code"
                    value="{{ old('code', $record->code) }}" placeholder="gold" required>
                @error('code')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
            <div class="form-field"><label class="label" for="name">Tier name <span
                        class="text-red-600">*</span></label><input class="input" id="name" name="name"
                    value="{{ old('name', $record->name) }}" placeholder="Gold" required>
                @error('name')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
            <div class="form-field"><label class="label" for="minimum_annual_sales">Minimum annual sales
                    (INR)</label><input class="input" id="minimum_annual_sales" name="minimum_annual_sales" type="number"
                    min="0" step="0.01" value="{{ old('minimum_annual_sales', $record->minimum_annual_sales) }}">
                @error('minimum_annual_sales')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
            <div class="form-field"><label class="label" for="maximum_discount_percent">Maximum discount (%)</label><input
                    class="input" id="maximum_discount_percent" name="maximum_discount_percent" type="number"
                    min="0" max="100" step="0.01"
                    value="{{ old('maximum_discount_percent', $record->maximum_discount_percent) }}">
                @error('maximum_discount_percent')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
            <div class="form-field form-field-wide"><label class="label" for="description">Description</label>
                <textarea class="input" id="description" name="description" rows="4">{{ old('description', $record->description) }}</textarea>
                @error('description')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </div>
        <div class="mt-6 flex flex-col-reverse gap-3 border-t border-slate-100 pt-5 sm:flex-row"><a class="btn-secondary"
                href="{{ route('admin.distributor-tiers.index') }}">Cancel</a><button class="btn-primary"
                type="submit">{{ $record->exists ? 'Update tier' : 'Create tier' }}</button></div>
    </form>
@endsection
