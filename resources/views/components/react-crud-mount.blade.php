@props(['formName', 'record', 'definition', 'options' => [], 'resource', 'action', 'cancelUrl'])
@php
    $fieldOptions = collect($definition['fields'])
        ->mapWithKeys(function ($field, $name) use ($options) {
            if (($field['type'] ?? '') === 'model') {
                $display = $field['display'];
                return [
                    $name => collect($options[$name] ?? [])
                        ->map(fn($item) => ['value' => (string) $item->id, 'label' => (string) $item->{$display}])
                        ->values()
                        ->all(),
                ];
            }
            if (($field['type'] ?? '') === 'select') {
                return [
                    $name => collect($field['options'] ?? [])
                        ->map(fn($label, $value) => ['value' => (string) $value, 'label' => (string) $label])
                        ->values()
                        ->all(),
                ];
            }
            return [$name => []];
        })
        ->all();
    $values = collect($definition['fields'])
        ->mapWithKeys(function ($field, $name) use ($record) {
            $value = old($name, $record->{$name} ?? ($field['default'] ?? ''));
            if ($value instanceof \DateTimeInterface) {
                $value = $value->format(($field['type'] ?? '') === 'datetime-local' ? 'Y-m-d\TH:i' : 'Y-m-d');
            }
            return [$name => $value ?? ''];
        })
        ->all();
    $props = [
        'action' => $action,
        'method' => $record->exists ? 'PUT' : 'POST',
        'cancelUrl' => $cancelUrl,
        'values' => $values,
        'options' => $fieldOptions,
        'errors' => $errors->toArray(),
        'editing' => $record->exists,
    ];
@endphp
<div id="react-{{ $formName }}-form"></div>
<script id="react-{{ $formName }}-form-props" type="application/json">{!! json_encode($props, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
