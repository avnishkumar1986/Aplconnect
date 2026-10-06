@extends('layout.app')
@section('title', 'Edit Material')
@section('content')
    <x-page-header title="Edit Material" description="Update the material master details used across procurement planning."><span class="crud-breadcrumb">Procurement <b>/</b> Materials <b>/</b> Edit</span></x-page-header>
    <form class="card w-full" method="POST" action="{{ route('admin.procurement.materials.update', $record->matnr) }}">
        @csrf @method('PUT')
        @if (request('return_to') === 'master')
            <input type="hidden" name="return_to" value="master">
        @endif
        @if ($errors->any())
            <div class="form-error-alert mb-4"><strong>Unable to save.</strong>
                <p>{{ $errors->first() }}</p>
            </div>
        @endif
        <div class="corporate-form-grid">
            <div class="form-field"><label class="label">Material code</label><input class="input bg-slate-50"
                    value="{{ $record->matnr }}" readonly></div>
            <div class="form-field"><label class="label">Description</label><input class="input" name="maktx"
                    value="{{ old('maktx', $record->maktx) }}" required></div>
            <div class="form-field"><label class="label">Type</label><input class="input" name="mtart"
                    value="{{ old('mtart', $record->mtart) }}"></div>
            <div class="form-field"><label class="label">Group</label><input class="input" name="matkl"
                    value="{{ old('matkl', $record->matkl) }}"></div>
            <div class="form-field"><label class="label">Unit</label><input class="input" name="meins"
                    value="{{ old('meins', $record->meins) }}"></div>
            <div class="form-field"><label class="label">Status</label><select class="input" name="status">
                    <option value="1" @selected(old('status', $record->status) == 1)>Active</option>
                    <option value="0" @selected(old('status', $record->status) == 0)>Inactive</option>
                </select></div>
        </div>
        <div class="mt-6 flex gap-3 border-t border-slate-100 pt-5"><a class="btn-secondary"
                href="{{ request('return_to') === 'master' ? route('admin.materials.index') : route('admin.procurement.materials') }}">Cancel</a><button
                class="btn-primary">Save changes</button></div>
    </form>
@endsection
