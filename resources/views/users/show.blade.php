@extends('layout.app')
@section('title', $user->full_name.' | Employee Profile')
@section('content')
@php
    $initials = collect(preg_split('/\s+/', $user->full_name))->filter()->take(2)->map(fn($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('');
    $gender = ['1'=>'Male','2'=>'Female','3'=>'Other','4'=>'Prefer not to say'][(string)$user->gender] ?? 'Not provided';
    $marital = ['1'=>'Single','2'=>'Married','3'=>'Divorced','4'=>'Widowed'][(string)$user->marital_status] ?? 'Not provided';
    $contact = $user->contact?->contact_value;
    $address = $user->address?->full_address;
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
                    <div class="employee-profile-name-row"><span>Employee name</span><strong>{{ $user->full_name }}</strong></div>
                    <div><span>Associated company</span><strong>{{ $user->company ? $user->company->company_code.' - '.$user->company->company_name : 'Not assigned' }}</strong></div>
                    <div><span>Sitting location</span><strong>{{ $user->sittingLocation ? $user->sittingLocation->company_code.' - '.$user->sittingLocation->company_name : 'Not assigned' }}</strong></div>
                    <div><span>Department</span><strong>{{ $user->department?->department_name ?? 'Not assigned' }}</strong></div>
                    <div><span>Designation</span><strong>{{ $user->designation?->designation_name ?? 'Not assigned' }}</strong></div>
                </div>
            </div>
        </div>
        <nav class="employee-profile-tabs" aria-label="Profile sections"><button class="active" type="button" data-profile-tab="overview">Overview</button><button type="button" data-profile-tab="employment">Employment</button><button type="button" data-profile-tab="contact">Contact</button><button type="button" data-profile-tab="education">Education</button><button type="button" data-profile-tab="audit">Record details</button></nav>
    </header>

    <div class="employee-profile-layout">
        <aside class="employee-profile-sidebar">
            <section class="employee-profile-card" data-profile-section="overview"><h2>About</h2><dl class="employee-profile-list">
                <div><dt>Verification</dt><dd>{{ (string)$user->is_verified==='1'?'Verified':'Not verified' }}</dd></div>
                <div><dt>Gender</dt><dd>{{ $gender }}</dd></div>
                <div><dt>Date of birth</dt><dd>{{ $user->date_of_birth?->format('d M Y') ?? '—' }}</dd></div>
                <div><dt>Marital status</dt><dd>{{ $marital }}</dd></div>
                <div><dt>Anniversary</dt><dd>{{ $user->anniversary_date?->format('d M Y') ?? '—' }}</dd></div>
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
        </aside>

        <main class="employee-profile-main">
            <section class="employee-profile-card employee-profile-highlight" data-profile-section="employment"><div class="employee-card-heading"><div><span>ORGANIZATION</span><h2>Employment details</h2></div></div><div class="employee-profile-facts">
                <div><span>Associated company</span><strong>{{ $user->company ? $user->company->company_code.' - '.$user->company->company_name : 'Not assigned' }}</strong></div>
                <div><span>Sitting location</span><strong>{{ $user->sittingLocation ? $user->sittingLocation->company_code.' - '.$user->sittingLocation->company_name : 'Not assigned' }}</strong></div>
                <div><span>Designation</span><strong>{{ $user->designation?->designation_name ?? 'Not assigned' }}</strong></div>
                <div><span>Department</span><strong>{{ $user->department?->department_name ?? 'Not assigned' }}</strong></div>
                <div><span>Reporting to</span><strong>{{ $user->reportingTo ? $user->reportingTo->full_name.' · '.$user->reportingTo->designation?->designation_name : ($user->designation?->parentDesignation?->designation_name ? $user->designation->parentDesignation->designation_name.' (position currently vacant)' : 'Top-level position') }}</strong></div>
                <div><span>Record status</span><strong>{{ (string)$user->status==='1'?'Active employee':'Inactive employee' }}</strong></div>
            </div></section>

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
                <div><span>Created at</span><strong>{{ $user->created_at?->format('d M Y, h:i A') ?? '—' }}</strong></div>
                <div><span>Created by</span><strong>{{ $user->createdBy?->name ?? ($user->created_by ? '#'.$user->created_by : 'System') }}</strong></div>
                <div><span>Last updated</span><strong>{{ $user->updated_at?->format('d M Y, h:i A') ?? '—' }}</strong></div>
                <div><span>Updated by</span><strong>{{ $user->updatedBy?->name ?? ($user->updated_by ? '#'.$user->updated_by : 'System') }}</strong></div>
            </div></section>
        </main>
    </div>
</article>
<script>
(()=>{const profile=document.querySelector('.employee-profile');if(!profile)return;const tabs=[...profile.querySelectorAll('[data-profile-tab]')],sections=[...profile.querySelectorAll('[data-profile-section]')],layout=profile.querySelector('.employee-profile-layout'),columns=[profile.querySelector('.employee-profile-sidebar'),profile.querySelector('.employee-profile-main')],scroller=profile.closest('[data-global-form-content]');const show=name=>{tabs.forEach(tab=>{const active=tab.dataset.profileTab===name;tab.classList.toggle('active',active);tab.setAttribute('aria-selected',String(active));});sections.forEach(section=>section.classList.toggle('hidden',section.dataset.profileSection!==name));columns.forEach(column=>column?.classList.toggle('hidden',!column.querySelector('[data-profile-section]:not(.hidden)')));layout?.classList.toggle('is-single',name!=='overview');if(scroller)scroller.scrollTo({top:0,behavior:'smooth'});};tabs.forEach(tab=>tab.addEventListener('click',()=>show(tab.dataset.profileTab)));show('overview');})();
</script>
@endsection
