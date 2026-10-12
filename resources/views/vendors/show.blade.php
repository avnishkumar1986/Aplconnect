@extends('layout.app')
@section('title', 'Distributor Details')
@section('content')
    <x-page-header title="Distributor Details" description="Distributor account, contact and commercial information.">
        <span class="crud-breadcrumb"><a href="{{ route('admin.distributors.index') }}">Distributors</a> <b>/</b> {{ $user->business_name ?: $user->full_name }}</span>
    </x-page-header>
    <section class="crud-list-card distributor-detail">
        <div class="distributor-detail-head">
            <div><small>DISTRIBUTOR #{{ $user->code }}</small><h2>{{ $user->business_name ?: $user->distributorProfile?->business_name ?: $user->full_name }}</h2><p>{{ $user->legal_business_name ?: $user->distributorProfile?->legal_business_name }}</p></div>
            <div class="row-actions">@can('users.edit')<a class="crud-add" data-form-modal data-modal-size="distributor" data-modal-title="Edit Distributor" href="{{ route('admin.distributors.edit',$user) }}">Edit Distributor</a>@endcan</div>
        </div>
        <div class="distributor-detail-grid">
            <div><span>Contact person</span><strong>{{ $user->full_name }}</strong></div>
            <div><span>Category</span><strong>{{ $user->distributor_category ? str($user->distributor_category)->headline() : '—' }}</strong></div>
            <div><span>GSTIN</span><strong>{{ $user->gst_number ?: '—' }}</strong></div>
            <div><span>PAN</span><strong>{{ $user->pan_number ?: '—' }}</strong></div>
            <div><span>Status</span><strong>{{ $user->status ? 'Active' : 'Inactive' }}</strong></div>
            <div><span>Login</span><strong>{{ $user->login?->username ?: '—' }}</strong></div>
        </div>
        <h3>Contacts</h3><div class="distributor-detail-grid">@forelse($user->contacts as $contact)<div><span>{{ ['1'=>'Mobile','2'=>'Telephone','3'=>'Email','4'=>'Emergency'][(string)$contact->contact_type] ?? 'Contact' }}</span><strong>{{ $contact->contact_value }}</strong></div>@empty<div>—</div>@endforelse</div>
        <h3>Addresses</h3><div class="distributor-detail-grid">@forelse($user->addresses as $address)<div><span>{{ $address->address_type }}</span><strong>{{ $address->full_address ?: $address->address_line_1 }}</strong></div>@empty<div>—</div>@endforelse</div>
    </section>
@endsection
@push('styles')<style>
.distributor-detail{padding:24px}.distributor-detail-head{display:flex;justify-content:space-between;gap:20px;align-items:flex-start;border-bottom:1px solid #e2e8f0;padding-bottom:20px}.distributor-detail-head small,.distributor-detail-grid span{display:block;color:#64748b;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em}.distributor-detail-head h2{margin:5px 0}.distributor-detail-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin:18px 0 28px}.distributor-detail-grid>div{padding:14px;border:1px solid #dbe5ee;border-radius:10px;background:#fbfdff}.distributor-detail h3{margin-top:20px}@media(max-width:800px){.distributor-detail-grid{grid-template-columns:1fr}.distributor-detail-head{flex-direction:column}}
</style>@endpush
