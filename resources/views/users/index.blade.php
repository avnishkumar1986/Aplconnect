@extends('layout.app')
@section('title', 'Users')
@section('content')
    @php
        $activeSort = request('sort', 'id');
        $activeDirection = request('direction', 'desc');
        $sortUrl = function (string $field) use ($activeSort, $activeDirection) {
            $params = request()->except('page');
            $params['sort'] = $field;
            $params['direction'] = $activeSort === $field && $activeDirection === 'asc' ? 'desc' : 'asc';
            return url()->current() . '?' . http_build_query($params);
        };
        $sortIcon = fn(string $field) => $activeSort === $field ? ($activeDirection === 'asc' ? '↑' : '↓') : '↕';
    @endphp

    <x-page-header title="Users" description="Manage employee records from the users database.">
        <span class="crud-breadcrumb">Master Control <b>/</b> Users</span>
    </x-page-header>

    <section class="crud-list-card">
        <div class="crud-toolbar" data-datatable-toolbar-for="users-datatable">
            @can('users.edit')
                <form class="user-bulk-actions" method="POST" action="{{ route('admin.users.bulk-action') }}" data-user-bulk-form>
                    @csrf
                    <select name="action" required aria-label="Bulk action">
                        <option value="">Bulk actions</option>
                        <option value="activate">Activate</option>
                        <option value="deactivate">Deactivate</option>
                    </select>
                    <button type="submit" disabled>Apply</button>
                    <span data-selected-count>0 selected</span>
                </form>
            @endcan
            @can('users.create')
                <a class="crud-add" data-form-modal data-modal-title="Create User"
                    href="{{ route('admin.users.create') }}"><span>＋</span> Add New User</a>
            @endcan
        </div>

        <div class="crud-results" data-crud-results>
            <div class="crud-mobile-note">Swipe horizontally to view all columns</div>
            <div class="crud-table-wrap">
                <table class="crud-table nowrap" id="users-datatable">
                    <thead>
                        <tr>
                            <th data-priority="1"></th>
                            <th class="check"><input type="checkbox" data-select-all-users aria-label="Select all users"></th>
                            <th>S.NO.</th>
                            <th><a class="table-sort" data-sort-link href="{{ $sortUrl('id') }}">ID
                                    <i>{{ $sortIcon('id') }}</i></a></th>
                            <th><a class="table-sort" data-sort-link href="{{ $sortUrl('first_name') }}">First Name
                                    <i>{{ $sortIcon('first_name') }}</i></a></th>
                            <th><a class="table-sort" data-sort-link href="{{ $sortUrl('last_name') }}">Last Name
                                    <i>{{ $sortIcon('last_name') }}</i></a></th>
                            <th>Designation</th>
                            
                            <th>Company</th>
                            <th>Sitting Location</th>
                            <th>Department</th>
                            <th>Gender</th>
                            <th>Date of Birth</th>
                            
                            <th>Contact</th>
                            <th>Email</th>
                            <th><a class="table-sort" data-sort-link href="{{ $sortUrl('status') }}">Status
                                    <i>{{ $sortIcon('status') }}</i></a></th>
                            <th>Updated At</th>
                            <th>Updated By</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($records as $user)
                            @php
                                $gender = ['1' => 'Male', '2' => 'Female', '3' => 'Other', '4' => 'Prefer not to say'];
                                $maritalStatus = [
                                    '1' => 'Single',
                                    '2' => 'Married',
                                    '3' => 'Divorced',
                                    '4' => 'Widowed',
                                ];
                                $education = $user->primaryEducation;
                            @endphp
                            <tr>
                                <td></td>
                                <td class="check"><input type="checkbox" value="{{ $user->id }}" data-user-select aria-label="Select user {{ $user->id }}">
                                </td>
                                <td class="record-serial">{{ $loop->iteration }}</td>
                                <td class="record-id">#{{ $user->id }}</td>
                                <td><strong>{{ $user->first_name }}</strong></td>
                                <td>{{ $user->last_name }}</td>
                                <td>{{ $user->designation?->designation_name ?? '—' }}</td>
                                <td>{{ $user->company?->company_name ?? '—' }}</td>
                                <td>{{ $user->sittingLocation?->company_name ?? '—' }}</td>
                                <td>{{ $user->department?->department_name ?? '—' }}</td>
                                <td>{{ $gender[(string) $user->gender] ?? '—' }}</td>
                                <td>{{ $user->date_of_birth?->format('d M Y') ?? '—' }}</td>
                                <td>{{ $user->contact?->contact_value ?? '—' }}</td>
                                <td>{{ $user->emailContacts->pluck('contact_value')->implode(', ') ?: '—' }}</td>
                                <td><span
                                        class="status-pill {{ $user->status ? 'active' : 'inactive' }}">{{ $user->status ? 'Active' : 'Inactive' }}</span>
                                </td>
                                <td>{{ $user->updated_at?->format('d M Y, h:i A') ?? '—' }}</td>
                                <td>{{ $user->updatedBy?->name ?? ($user->updated_by ? '#' . $user->updated_by : '—') }}</td>
                                <td>
                                    <div class="row-actions">
                                        @can('users.view')
                                            <a class="user-action-button user-action-view" href="{{ route('admin.users.show', $user) }}" aria-label="View {{ $user->full_name }}" title="View details">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
                                            </a>
                                        @endcan
                                        @can('users.edit')
                                            <a class="edit user-action-button user-action-edit" data-form-modal data-modal-title="Edit User"
                                                href="{{ route('admin.users.edit', $user) }}" aria-label="Edit {{ $user->full_name }}" title="Edit user"><svg
                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <path d="M12 20h9" />
                                                    <path d="m16.5 3.5 4 4L8 20l-5 1 1-5Z" />
                                                </svg></a>
                                        @endcan
                                        @can('users.delete')
                                            <form method="POST" action="{{ route('admin.users.destroy', $user) }}"
                                                onsubmit="return confirm('Delete this user?')">@csrf @method('DELETE')<button
                                                    class="delete user-action-button user-action-delete" type="submit" aria-label="Delete {{ $user->full_name }}" title="Delete user"><svg
                                                        viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                        stroke-width="2">
                                                        <path d="M3 6h18M8 6V4h8v2M6 6l1 15h10l1-15" />
                                                    </svg></button></form>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </section>
@endsection

@push('styles')
<style>
    .user-bulk-actions{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
    .user-bulk-actions select{height:40px;border:1px solid #d8dee8;border-radius:8px;background:#fff;padding:0 32px 0 12px;color:#334155;font-size:14px}
    .user-bulk-actions button{height:40px;border:0;border-radius:8px;background:#334155;padding:0 16px;color:#fff;font-size:14px;font-weight:700;cursor:pointer}
    .user-bulk-actions button:disabled{cursor:not-allowed;opacity:.45}
    .user-bulk-actions [data-selected-count]{color:#64748b;font-size:13px;white-space:nowrap}
    #users-datatable_wrapper .user-action-view{border-color:#bfdbfe!important;background:#eff6ff!important;color:#2563eb!important}
    #users-datatable_wrapper .user-action-view:hover{border-color:#93c5fd!important;background:#dbeafe!important;color:#1d4ed8!important;transform:translateY(-1px)}
</style>
@endpush
