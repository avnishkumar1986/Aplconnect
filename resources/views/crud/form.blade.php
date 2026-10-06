@extends('layout.app')
@section('title', ($record->exists ? 'Edit ' : 'Create ') . $definition['title'])
@section('content')
    <x-page-header :title="($record->exists ? 'Edit ' : 'Create ') . strtolower($definition['title'])"
        description="Complete the information below. Required fields are validated before saving.">
        <a class="btn-secondary" href="{{ route('admin.crud.index', $resource) }}">← Back to list</a>
    </x-page-header>
    <form
        class="card corporate-record-form {{ in_array($resource, ['users', 'user-types'], true) ? 'user-corporate-form' : '' }} {{ request()->boolean('readonly') ? 'is-readonly' : '' }} w-full p-4 sm:p-6"
        method="POST"
        action="{{ $record->exists ? route('admin.crud.update', [$resource, $record->id]) : route('admin.crud.store', $resource) }}">
        @csrf @if ($record->exists)
            @method('PUT')
        @endif
        @if ($errors->any())
            <div class="form-error-alert" role="alert"><span class="form-error-alert-icon">!</span>
                <div><strong>Unable to save this record</strong>
                    <p>Please correct the following errors:</p>
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif
        <div class="corporate-form-grid">
            @foreach ($definition['fields'] as $name => $field)
                @continue($field['form_hidden'] ?? false) @php($type = $field['type'] ?? 'text')
                <div class="form-field {{ $type === 'textarea' || $type === 'roles' ? 'form-field-wide' : '' }}"><label class="label"
                        for="{{ $name }}">{{ $field['label'] ?? ucwords(str_replace('_', ' ', $name)) }}</label>
                    @if ($type === 'select')
                        <select class="input" id="{{ $name }}" name="{{ $name }}">
                            @foreach ($field['options'] as $value => $label)
                                <option value="{{ $value }}" @selected((string) old($name, $record->$name ?? ($field['default'] ?? '')) === (string) $value)>{{ $label }}
                                </option>
                            @endforeach
                        </select>
                    @elseif($type === 'model')
                        <select class="input" id="{{ $name }}" name="{{ $name }}">
                            <option value="">— None —</option>
                            @foreach ($options[$name] as $option)
                                <option value="{{ $option->id }}" @selected((string) old($name, $record->$name) === (string) $option->id)>
                                    {{ $option->{$field['display']} }}</option>
                            @endforeach
                        </select>
                    @elseif($type === 'textarea')
                        <textarea class="input" rows="3" id="{{ $name }}" name="{{ $name }}">{{ old($name, $record->$name) }}</textarea>
                    @elseif($type === 'checkbox')
                        <label class="flex items-center gap-2"><input type="checkbox" id="{{ $name }}"
                                name="{{ $name }}" value="1" @checked(old($name, $record->$name))> Yes</label>
                    @elseif($type === 'roles')
                        <div class="grid gap-2 sm:grid-cols-3">
                            @foreach (\Spatie\Permission\Models\Role::orderBy('name')->get() as $role)
                                <label class="flex gap-2 rounded border p-2"><input type="checkbox" name="roles[]"
                                        value="{{ $role->name }}"
                                        @checked($record->exists && $record->hasRole($role))>{{ $role->name }}</label>
                            @endforeach
                        </div>
                    @else<input class="input" type="{{ $type }}" id="{{ $name }}"
                            name="{{ $name }}"
                            value="{{ old($name, $record->$name instanceof \DateTimeInterface ? $record->$name->format($type === 'datetime-local' ? 'Y-m-d\TH:i' : 'Y-m-d') : $record->$name ?? ($field['default'] ?? '')) }}">
                    @endif
                    @if (isset($field['help']))
                        <p class="mt-1 text-xs text-slate-500">{{ $field['help'] }}</p>
                    @endif @error($name)
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
        @endforeach
    </div>
    <div class="mt-6 flex flex-col-reverse gap-3 border-t border-slate-100 pt-5 sm:flex-row"><a
            class="btn-secondary w-full sm:w-auto" href="{{ route('admin.crud.index', $resource) }}">Cancel</a><button
            class="btn-primary w-full sm:w-auto">Save changes</button></div>
</form>
@endsection
