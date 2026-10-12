@extends('layout.app')
@section('title', $user->full_name.' | '.(($salesCustomerProfile ?? false) ? 'Customer Profile' : 'Employee Profile'))
@section('content')
@php
    $initials = collect(preg_split('/\s+/', $user->full_name))->filter()->take(2)->map(fn($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('');
    $gender = ['1'=>'Male','2'=>'Female','3'=>'Other','4'=>'Prefer not to say'][(string)$user->gender] ?? 'Not provided';
    $marital = ['1'=>'Single','2'=>'Married','3'=>'Divorced','4'=>'Widowed'][(string)$user->marital_status] ?? 'Not provided';
    $contact = $user->contact?->contact_value;
    $address = $user->address?->full_address;
    $isDistributor = request()->routeIs('admin.distributors.*', 'admin.sales.customers.show');
    $isSalesCustomer = (bool) ($salesCustomerProfile ?? false);
    $targetYear = (int) ($user->distributorTargets?->max('target_year') ?: now()->year);
    $targetRows = $user->distributorTargets?->where('target_year', $targetYear)->keyBy('target_month') ?? collect();
    $targetMonths = [1=>'January',2=>'February',3=>'March',4=>'April',5=>'May',6=>'June',7=>'July',8=>'August',9=>'September',10=>'October',11=>'November',12=>'December'];
@endphp
<article class="employee-profile" id="profile-overview">
    <header class="employee-profile-hero">
        <div class="employee-profile-cover" @if($user->coverImage) style="background-image:linear-gradient(90deg,rgba(9,27,48,.58),rgba(16,104,111,.34)),url('{{ asset('storage/'.$user->coverImage->file_path) }}')" @endif><span>APL CONNECT</span>@can('users.edit')<form class="employee-image-upload employee-cover-upload" method="POST" action="{{ route('admin.users.cover-image',$user) }}" enctype="multipart/form-data">@csrf<label><input type="file" name="cover_image" accept="image/jpeg,image/png,image/webp" onchange="this.form.submit()"><b>Upload wall image</b></label></form>@endcan</div>
        <div class="employee-profile-identity">
            <div class="employee-profile-avatar">@if($user->profileImage)<img src="{{ asset('storage/'.$user->profileImage->file_path) }}" alt="{{ $user->full_name }} profile photo">@else<span>{{ $initials ?: 'U' }}</span>@endif @can('users.edit')<form class="employee-image-upload employee-avatar-upload" method="POST" action="{{ route('admin.users.profile-image',$user) }}" enctype="multipart/form-data">@csrf<label title="Upload profile image"><input type="file" name="profile_image" accept="image/jpeg,image/png,image/webp" onchange="this.form.submit()"><b>+</b></label></form>@endcan</div>
            <div class="employee-profile-name">
                <div class="employee-profile-title-line">
                    <span class="employee-profile-status {{ (string)$user->status==='1'?'active':'inactive' }}">{{ (string)$user->status==='1'?'Active':'Inactive' }}</span>
                </div>
                <div class="employee-profile-identity-grid">
                    <div class="employee-profile-name-row"><span>{{ $isSalesCustomer ? 'Customer business' : 'Employee name' }}</span><strong>{{ $isSalesCustomer ? ($user->business_name ?: $user->distributorProfile?->business_name ?: $user->full_name) : $user->full_name }}</strong></div>
                    <div><span>Associated company</span><strong>{{ $user->company ? $user->company->company_code.' - '.$user->company->company_name : 'Not assigned' }}</strong></div>
                    <div><span>Sitting location</span><strong>{{ $user->sittingLocation ? $user->sittingLocation->company_code.' - '.$user->sittingLocation->company_name : 'Not assigned' }}</strong></div>
                    <div><span>Department</span><strong>{{ $user->department?->department_name ?? 'Not assigned' }}</strong></div>
                    <div><span>Designation</span><strong>{{ $user->designation?->designation_name ?? 'Not assigned' }}</strong></div>
                </div>
            </div>
        </div>
        <nav class="employee-profile-tabs" aria-label="Profile sections"><button class="active" type="button" data-profile-tab="overview">Overview</button><button type="button" data-profile-tab="employment">{{ $isSalesCustomer ? 'Commercial' : 'Employment' }}</button><button type="button" data-profile-tab="contact">Contact & addresses</button>@unless($isSalesCustomer)<button type="button" data-profile-tab="education">Education</button>@endunless @if($isDistributor)<button type="button" data-profile-tab="targets">Targets</button>@endif<button type="button" data-profile-tab="audit">Record details</button></nav>
    </header>

    <div class="employee-profile-layout">
        <aside class="employee-profile-sidebar">
            <section class="employee-profile-card" data-profile-section="overview"><h2>About</h2><dl class="employee-profile-list">
                <div><dt>Verification</dt><dd>{{ (string)$user->is_verified==='1'?'Verified':'Not verified' }}</dd></div>
                <div><dt>Gender</dt><dd>{{ $gender }}</dd></div>
                <div><dt>Date of birth</dt><dd>{{ $user->date_of_birth?->format('d.m.Y') ?? '—' }}</dd></div>
                <div><dt>Marital status</dt><dd>{{ $marital }}</dd></div>
                <div><dt>Anniversary</dt><dd>{{ $user->anniversary_date?->format('d.m.Y') ?? '—' }}</dd></div>
            </dl></section>
            <section class="employee-profile-card" data-profile-section="contact"><h2>Contact information</h2><dl class="employee-profile-list">
                @forelse($user->contacts as $item)
                    <div><dt>{{ ['1'=>'Mobile','2'=>'Telephone','3'=>'Email','4'=>'Emergency contact'][(string)$item->contact_type] ?? 'Contact' }}{{ $item->is_primary ? ' (Primary)' : '' }}</dt><dd>{{ $item->contact_value }}</dd></div>
                @empty
                    <div><dt>Contact</dt><dd>{{ $contact ?: 'Not provided' }}</dd></div>
                @endforelse
            </dl></section>

            <section class="employee-profile-card" data-profile-section="contact"><h2>Addresses</h2><dl class="employee-profile-list">
                @forelse($user->addresses as $item)
                    <div>
                        <dt>{{ (string)$item->address_type === '1' && $item->is_current_permanent_same ? 'Current & Permanent' : (['1'=>'Current','2'=>'Permanent','3'=>'Office','4'=>'Plant','5'=>'Subsidiary'][(string)$item->address_type] ?? 'Address') }}</dt>
                        <dd>{{ $item->full_address }}</dd>
                        @if($item->latitude !== null && $item->longitude !== null)
                            @php
                                $lat = (float) $item->latitude;
                                $lng = (float) $item->longitude;
                                $mapUrl = 'https://www.openstreetmap.org/export/embed.html?bbox='.
                                    ($lng - 0.01).'%2C'.($lat - 0.01).'%2C'.($lng + 0.01).'%2C'.($lat + 0.01).
                                    '&layer=mapnik&marker='.$lat.'%2C'.$lng;
                            @endphp
                            <iframe src="{{ $mapUrl }}" title="Office location map" loading="lazy" style="width:100%;height:220px;margin-top:12px;border:1px solid #dbe4ef;border-radius:10px"></iframe>
                            <a href="https://www.openstreetmap.org/?mlat={{ $lat }}&mlon={{ $lng }}#map=16/{{ $lat }}/{{ $lng }}" target="_blank" rel="noopener">Open larger map</a>
                        @endif
                    </div>
                @empty
                    <div><dt>Address</dt><dd>{{ $address ?: 'Not provided' }}</dd></div>
                @endforelse
            </dl></section>

            @if($user->distributorSubsidiaries->isNotEmpty())
            <section class="employee-profile-card" data-profile-section="contact"><h2>Subsidiaries</h2><dl class="employee-profile-list">
                @foreach($user->distributorSubsidiaries as $subsidiary)
                    <div><dt>{{ $subsidiary->subsidiary_name }}</dt><dd>
                        {{ $subsidiary->phone_number }}<br>
                        <a href="mailto:{{ $subsidiary->email }}">{{ $subsidiary->email }}</a><br>
                        {{ collect([$subsidiary->address_line_1, $subsidiary->address_line_2, $subsidiary->city, $subsidiary->district, $subsidiary->state, $subsidiary->postal_code, $subsidiary->country])->filter()->implode(', ') }}
                    </dd></div>
                @endforeach
            </dl></section>
            @endif
        </aside>

        <main class="employee-profile-main">
            @if($isDistributor)
            <section class="employee-profile-card distributor-target-card" data-profile-section="targets">
                <div class="employee-card-heading"><div><span>SALES PLANNING</span><h2>Monthly and yearly targets</h2></div><b>{{ $targetRows->first()?->currency ?? 'INR' }}</b></div>
                @can('users.edit')
                <form method="POST" action="{{ route('admin.distributors.targets.store', $user) }}">@csrf
                    <div class="distributor-target-summary">
                        <label><span>Target year</span><input type="number" name="target_year" min="2000" max="2100" value="{{ old('target_year', $targetYear) }}" required></label>
                        <label><span>Yearly target</span><input type="number" name="yearly_target" min="0" step="0.01" value="{{ old('yearly_target', $targetRows->get(0)?->target_amount ?? 0) }}" required></label>
                    </div>
                    <div class="distributor-target-months">
                        @foreach($targetMonths as $month => $label)
                            <label><span>{{ $label }}</span><input type="number" name="monthly_targets[{{ $month }}]" min="0" step="0.01" value="{{ old('monthly_targets.'.$month, $targetRows->get($month)?->target_amount ?? 0) }}" required></label>
                        @endforeach
                    </div>
                    <div class="distributor-target-actions"><button class="crud-add" type="submit">Save targets</button></div>
                </form>
                @else
                    <div class="distributor-target-months">@foreach($targetMonths as $month => $label)<div><span>{{ $label }}</span><strong>{{ number_format((float)($targetRows->get($month)?->target_amount ?? 0),2) }}</strong></div>@endforeach</div>
                @endcan
            </section>
            @endif
            @if($isSalesCustomer)
            <section class="employee-profile-card employee-profile-highlight" data-profile-section="employment"><div class="employee-card-heading"><div><span>SALES CUSTOMER</span><h2>Commercial details</h2></div></div><div class="employee-profile-facts">
                <div><span>Customer ID</span><strong>{{ $user->code ?: '—' }}</strong></div>
                <div><span>Company association</span><strong>{{ ['1000'=>'APL','5000'=>'KPL'][$user->distributorProfile?->company_association] ?? ($user->distributorProfile?->company_association ?: 'APL + KPL') }}</strong></div>
                <div><span>Distributor category</span><strong>{{ str($user->distributor_category ?: $user->distributorProfile?->distributor_category ?: 'Not assigned')->headline() }}</strong></div>
                <div><span>Distributor tier</span><strong>{{ $user->distributorProfile?->tier?->name ?? $user->distributorProfile?->distributor_tier_code ?? 'Not assigned' }}</strong></div>
                <div><span>Credit limit</span><strong>INR {{ number_format((float) ($user->distributorProfile?->credit_limit ?? 0), 2) }}</strong></div>
                <div><span>Credit period</span><strong>{{ $user->distributorProfile?->credit_period_days ? $user->distributorProfile->credit_period_days.' days' : 'Not assigned' }}</strong></div>
                <div><span>Payment terms</span><strong>{{ $user->distributorProfile?->payment_terms ?: 'Not assigned' }}</strong></div>
                <div><span>Price list</span><strong>{{ $user->distributorProfile?->price_list ?: 'Not assigned' }}</strong></div>
                <div class="wide"><span>Material groups</span><strong>{{ collect($user->distributorProfile?->material_groups)->filter()->implode(', ') ?: 'All applicable material groups' }}</strong></div>
                <div><span>Record status</span><strong>{{ (string)$user->status==='1'?'Active customer':'Inactive customer' }}</strong></div>
            </div></section>
            @else
            <section class="employee-profile-card employee-profile-highlight" data-profile-section="employment"><div class="employee-card-heading"><div><span>ORGANIZATION</span><h2>Employment details</h2></div></div><div class="employee-profile-facts">
                <div><span>Associated company</span><strong>{{ $user->company ? $user->company->company_code.' - '.$user->company->company_name : 'Not assigned' }}</strong></div>
                <div><span>Sitting location</span><strong>{{ $user->sittingLocation ? $user->sittingLocation->company_code.' - '.$user->sittingLocation->company_name : 'Not assigned' }}</strong></div>
                <div><span>Designation</span><strong>{{ $user->designation?->designation_name ?? 'Not assigned' }}</strong></div>
                <div><span>Department</span><strong>{{ $user->department?->department_name ?? 'Not assigned' }}</strong></div>
                <div><span>Reporting to</span><strong>{{ $user->reportingTo ? $user->reportingTo->full_name.' · '.$user->reportingTo->designation?->designation_name : ($user->designation?->parentDesignation?->designation_name ? $user->designation->parentDesignation->designation_name.' (position currently vacant)' : 'Top-level position') }}</strong></div>
                <div><span>Record status</span><strong>{{ (string)$user->status==='1'?'Active employee':'Inactive employee' }}</strong></div>
            </div></section>
            @endif

            <section class="employee-profile-card" data-profile-section="education"><div class="employee-card-heading"><div><span>QUALIFICATIONS</span><h2>Education</h2></div><b>{{ $user->education->count() }} record(s)</b></div>
                @forelse($user->education as $education)
                <div class="employee-timeline-item"><i></i><div class="employee-education-content"><h3>{{ $education->degree_name ?: $education->education_level }}</h3><p>{{ $education->institution_name }}{{ $education->university_name?', '.$education->university_name:'' }}</p><small>{{ $education->start_date?->format('Y') ?? '—' }} - {{ $education->is_current?'Present':($education->end_date?->format('Y') ?? '—') }} · {{ ucfirst($education->status ?? 'pending') }}</small><div class="employee-document-actions">@if($education->image_path)<a href="{{ asset('storage/'.$education->image_path) }}" target="_blank" rel="noopener">View document</a>@endif @can('users.edit')<form method="POST" action="{{ route('admin.users.education-document',[$user,$education]) }}" enctype="multipart/form-data">@csrf<label><input type="file" name="education_document" accept=".pdf,image/jpeg,image/png,image/webp" onchange="this.form.submit()"><b>{{ $education->image_path?'Replace':'Upload' }} document</b></label></form>@endcan</div></div></div>
                @empty<p class="employee-profile-empty">No education records have been added.</p>@endforelse
            </section>

            <section class="employee-profile-card" data-profile-section="overview"><div class="employee-card-heading"><div><span>ACCESS</span><h2>Login and roles</h2></div></div><div class="employee-profile-facts">
                <div><span>Username</span><strong>{{ $user->login?->username ?? 'No login account' }}</strong></div>
                <div><span>Account status</span><strong>{{ $user->login ? ($user->login->status?'Active':'Inactive') : 'Not available' }}</strong></div>
                <div class="wide"><span>Assigned roles</span><strong>{{ $user->login?->roles?->pluck('name')->implode(', ') ?: 'No roles assigned' }}</strong></div>
            </div></section>

            <section class="employee-profile-card" data-profile-section="audit"><div class="employee-card-heading"><div><span>DATABASE RECORD</span><h2>Record details</h2></div></div><div class="employee-profile-facts">
                <div><span>User record ID</span><strong>#{{ $user->id }}</strong></div>
                <div><span>Company reference</span><strong>{{ $user->employmentDepartment?->company_id }} · {{ $user->company?->company_name ?? 'Not mapped' }}</strong></div>
                <div><span>Sitting location reference</span><strong>{{ $user->employmentDepartment?->sitting_location_id ?? '—' }} · {{ $user->sittingLocation?->company_name ?? 'Not mapped' }}</strong></div>
                <div><span>Designation reference</span><strong>{{ $user->employmentDepartment?->designation_id ?? '—' }} · {{ $user->designation?->designation_name ?? 'Not mapped' }}</strong></div>
                <div><span>Department reference</span><strong>{{ $user->employmentDepartment?->department_id ?? '—' }} · {{ $user->department?->department_name ?? 'Not mapped' }}</strong></div>
                <div><span>Primary contact reference</span><strong>{{ $user->contact_id ?? '—' }}</strong></div>
                <div><span>Primary address reference</span><strong>{{ $user->address_id ?? '—' }}</strong></div>
                <div><span>Primary education reference</span><strong>{{ $user->education_id ?? '—' }}</strong></div>
                <div><span>Login reference</span><strong>{{ $user->login_id ?? '—' }} · {{ $user->login?->username ?? 'Not mapped' }}</strong></div>
                <div><span>Profile image record</span><strong>{{ $user->profileImage?->id ?? ($user->photo_id ?? '—') }}</strong></div>
                <div><span>Created at</span><strong>{{ $user->created_at?->format('d.m.Y, h:i A') ?? '—' }}</strong></div>
                <div><span>Created by</span><strong>{{ $user->createdBy?->name ?? ($user->created_by ? '#'.$user->created_by : 'System') }}</strong></div>
                <div><span>Last updated</span><strong>{{ $user->updated_at?->format('d.m.Y, h:i A') ?? '—' }}</strong></div>
                <div><span>Updated by</span><strong>{{ $user->updatedBy?->name ?? ($user->updated_by ? '#'.$user->updated_by : 'System') }}</strong></div>
            </div></section>
        </main>
    </div>
</article>
@if($isDistributor)
<style>
.distributor-target-card form{display:grid;gap:18px}.distributor-target-summary{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;padding:16px;border:1px solid #cfe2e6;border-radius:12px;background:#f3fafb}.distributor-target-summary label,.distributor-target-months label,.distributor-target-months>div{display:grid;gap:7px}.distributor-target-summary span,.distributor-target-months span{color:#60748b;font-size:11px;font-weight:750;text-transform:uppercase;letter-spacing:.04em}.distributor-target-card input{width:100%;height:42px;border:1px solid #cbd9e5;border-radius:9px;background:#fff;padding:0 12px;color:#172b45;outline:none}.distributor-target-card input:focus{border-color:#36aab5;box-shadow:0 0 0 3px rgba(21,151,162,.1)}.distributor-target-months{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.distributor-target-months>label,.distributor-target-months>div{padding:13px;border:1px solid #dce6ed;border-radius:10px;background:#fbfcfe}.distributor-target-months strong{color:#172b45;font-size:15px}.distributor-target-actions{display:flex;justify-content:flex-end}.distributor-target-actions .crud-add{border:0;cursor:pointer}@media(max-width:900px){.distributor-target-months{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:600px){.distributor-target-summary,.distributor-target-months{grid-template-columns:1fr}}
</style>
@endif
<script>
(()=>{const profile=document.querySelector('.employee-profile');if(!profile)return;const tabs=[...profile.querySelectorAll('[data-profile-tab]')],sections=[...profile.querySelectorAll('[data-profile-section]')],layout=profile.querySelector('.employee-profile-layout'),columns=[profile.querySelector('.employee-profile-sidebar'),profile.querySelector('.employee-profile-main')],scroller=profile.closest('[data-global-form-content]');const show=name=>{tabs.forEach(tab=>{const active=tab.dataset.profileTab===name;tab.classList.toggle('active',active);tab.setAttribute('aria-selected',String(active));});sections.forEach(section=>section.classList.toggle('hidden',section.dataset.profileSection!==name));columns.forEach(column=>column?.classList.toggle('hidden',!column.querySelector('[data-profile-section]:not(.hidden)')));layout?.classList.toggle('is-single',name!=='overview');if(scroller)scroller.scrollTo({top:0,behavior:'smooth'});};tabs.forEach(tab=>tab.addEventListener('click',()=>show(tab.dataset.profileTab)));show('overview');})();
</script>
@endsection
