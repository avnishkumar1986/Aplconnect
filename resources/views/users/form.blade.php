@extends('layout.app')
@section('title', ($record->exists ? 'Edit ' : 'Create ') . $definition['title'])
@section('content')
    @php
        $reactFields = collect($definition['fields'])
            ->map(function ($field, $name) use ($options) {
                $type = $field['type'] ?? 'text';
                $fieldOptions = [];
                if ($type === 'model') {
                    $display = $field['display'];
                    $optionValue = $field['option_value'] ?? 'id';
                    $fieldOptions = collect($options[$name] ?? [])
                        ->map(
                            fn($option) => [
                                'value' => (string) $option->{$optionValue},
                                'label' => (string) $option->{$display},
                            ],
                        )
                        ->values()
                        ->all();
                } elseif ($type === 'select') {
                    $fieldOptions = collect($field['options'] ?? [])
                        ->map(fn($label, $value) => ['value' => (string) $value, 'label' => (string) $label])
                        ->values()
                        ->all();
                }
                return [
                    'name' => $name,
                    'label' => $field['label'] ?? ucwords(str_replace('_', ' ', $name)),
                    'type' => $type,
                    'required' => str_contains($field['rules'] ?? '', 'required'),
                    'help' => $field['help'] ?? null,
                    'options' => $fieldOptions,
                ];
            })
            ->values()
            ->all();
        $values = collect($definition['fields'])
            ->mapWithKeys(function ($field, $name) use ($record) {
                $value = old($name, $record->{$name} ?? ($field['default'] ?? ''));
                if ($value instanceof \DateTimeInterface) {
                    $value = $value->format('Y-m-d');
                }
                return [$name => $value === null ? '' : (string) $value];
            })
            ->all();
                $education = $record->exists ? $record->primaryEducation : null;
                $values = array_merge($values, [
            'username' => old('username', $record->login?->username ?? ''),
            'password' => '',
            'password_confirmation' => '',
            'role_id' => old('role_id', $record->employmentDepartment?->role_id ?? $record->login?->roles->first()?->id ?? ''),
            'new_education_level' => old('new_education_level', $education?->education_level ?? ''),
            'new_degree_name' => old('new_degree_name', $education?->degree_name ?? ''),
            'new_institution_name' => old('new_institution_name', $education?->institution_name ?? ''),
            'new_university_name' => old('new_university_name', $education?->university_name ?? ''),
            'new_education_start_date' => old(
                'new_education_start_date',
                $education?->start_date?->format('Y-m-d') ?? '',
            ),
            'new_education_end_date' => old('new_education_end_date', $education?->end_date?->format('Y-m-d') ?? ''),
            'new_education_is_current' => old('new_education_is_current', $education?->is_current ? '1' : '0'),
            'new_education_grade' => old('new_education_grade', $education?->grade ?? ''),
            'distributor_business_name' => old('distributor_business_name', $record->business_name ?? $record->distributorProfile?->business_name ?? ''),
            'distributor_legal_business_name' => old('distributor_legal_business_name', $record->legal_business_name ?? $record->distributorProfile?->legal_business_name ?? ''),
            'distributor_category' => old('distributor_category', $record->distributor_category ?? $record->distributorProfile?->distributor_category ?? ''),
            'distributor_company_association' => old('distributor_company_association', $record->distributorProfile?->company_association ?? ''),
            'distributor_applicable_plants' => old('distributor_applicable_plants', $record->distributorProfile?->applicable_plants ?? []),
            'distributor_material_groups' => old('distributor_material_groups', $record->distributorProfile?->material_groups ?? []),
            'distributor_credit_limit' => old('distributor_credit_limit', $record->distributorProfile?->credit_limit ?? ''),
            'distributor_credit_period_days' => old('distributor_credit_period_days', $record->distributorProfile?->credit_period_days ?? ''),
            'distributor_payment_terms' => old('distributor_payment_terms', $record->distributorProfile?->payment_terms ?? ''),
            'distributor_price_list' => old('distributor_price_list', $record->distributorProfile?->price_list ?? ''),
            'distributor_currency' => old('distributor_currency', $record->distributorProfile?->currency ?? 'INR'),
            'distributor_tax_classification' => old('distributor_tax_classification', $record->distributorProfile?->tax_classification ?? ''),
            'distributor_bank_name' => old('distributor_bank_name', $record->distributorProfile?->bank_name ?? ''),
            'distributor_account_holder_name' => old('distributor_account_holder_name', $record->distributorProfile?->account_holder_name ?? ''),
            'distributor_account_number' => old('distributor_account_number', $record->distributorProfile?->account_number ?? ''),
            'distributor_ifsc_code' => old('distributor_ifsc_code', $record->distributorProfile?->ifsc_code ?? ''),
            'distributor_bank_branch' => old('distributor_bank_branch', $record->distributorProfile?->bank_branch ?? ''),
        ]);
        $formProps = [
            'title' => $record->exists ? 'Edit user' : 'Create user',
            'description' => 'Complete the employee, designation and account details below.',
            'action' => $record->exists ? route('admin.users.update', $record) : route('admin.users.store'),
            'method' => $record->exists ? 'PUT' : 'POST',
            'cancelUrl' => route('admin.users.index'),
            'fields' => $reactFields,
            'values' => $values,
            'errors' => $errors->toArray(),
            'roleOptions' => $roles
                ->map(fn($role) => ['value' => (string) $role->id, 'label' => $role->name])
                ->values()
                ->all(),
            'supplierOptions' => $supplierOptions ?? [],
            'materialGroupOptions' => $materialGroupOptions ?? [],
            'supplierDocuments' => $supplierDocuments ?? [],
            'distributorCancelledChequeUrl' => $record->distributorProfile?->cancelled_cheque_path
                ? asset('storage/' . $record->distributorProfile->cancelled_cheque_path)
                : null,
            'initialContacts' => $record->exists
                ? $record->contacts
                    ->sortByDesc('is_primary')
                    ->map(
                        fn($contact) => [
                            'id' => (string) $contact->id,
                            'type' => (string) $contact->contact_type,
                            'value' => (string) $contact->contact_value,
                            'is_primary' => (bool) $contact->is_primary,
                        ],
                    )
                    ->values()
                    ->all()
                : [],
            'initialAddresses' => $record->exists
                ? $record->addresses
                    ->map(
                        fn($address) => [
                            'id' => (string) $address->id,
                            'type' => (string) $address->address_type,
                            'is_current_permanent_same' => (bool) $address->is_current_permanent_same,
                            'line_1' => (string) $address->address_line_1,
                            'line_2' => (string) ($address->address_line_2 ?? ''),
                            'city' => (string) ($address->city ?? ''),
                            'district' => (string) ($address->district ?? ''),
                            'state' => (string) ($address->state ?? ''),
                            'postal_code' => (string) ($address->postal_code ?? ''),
                            'country' => (string) ($address->country ?? 'India'),
                            'latitude' => $address->latitude === null ? '' : (string) $address->latitude,
                            'longitude' => $address->longitude === null ? '' : (string) $address->longitude,
                        ],
                    )
                    ->values()
                    ->all()
                : [],
            'initialAdditionalEducations' => $record->exists
                ? $record->education
                    ->where('id', '!=', $record->education_id)
                    ->map(
                        fn($item) => [
                            'id' => (string) $item->id,
                            'level' => (string) $item->education_level,
                            'degree_name' => (string) $item->degree_name,
                            'institution_name' => (string) $item->institution_name,
                            'university_name' => (string) ($item->university_name ?? ''),
                            'start_date' => $item->start_date?->format('Y-m-d') ?? '',
                            'end_date' => $item->end_date?->format('Y-m-d') ?? '',
                            'grade' => (string) ($item->grade ?? ''),
                            'is_current' => (bool) $item->is_current,
                            'document_url' => $item->image_path ? asset('storage/' . $item->image_path) : null,
                        ],
                    )
                    ->values()
                    ->all()
                : [],
        ];
    @endphp
    <div id="react-user-form"></div>
    <script id="react-user-form-props" type="application/json">{!! json_encode($formProps, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
@endsection
