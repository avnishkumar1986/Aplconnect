@extends('layout.app')
@section('title', $record->exists ? 'Edit distributor type' : 'Create distributor type')
@section('content')
    <form class="card corporate-record-form w-full p-4 sm:p-6" method="POST"
        action="{{ $record->exists ? route('admin.distributor-types.update', $record) : route('admin.distributor-types.store') }}">
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
            <div class="form-field"><label class="label" for="code">Type code <span
                        class="text-red-600">*</span></label><input class="input" id="code" name="code"
                    value="{{ old('code', $record->code) }}" placeholder="regional" required>
                @error('code')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
            <div class="form-field"><label class="label" for="name">Type name <span
                        class="text-red-600">*</span></label><input class="input" id="name" name="name"
                    value="{{ old('name', $record->name) }}" placeholder="Regional Distributor" required>
                @error('name')
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
                href="{{ route('admin.distributor-types.index') }}">Cancel</a><button class="btn-primary"
                type="submit">{{ $record->exists ? 'Update type' : 'Create type' }}</button></div>
    </form>
@endsection
