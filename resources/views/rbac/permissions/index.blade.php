@extends('layout.app')
@section('title', 'Permissions')
@section('content')<h1 class="mb-6 text-2xl font-semibold sm:text-3xl">
        Permissions</h1>
    @can('permissions.create')
        <div data-permission-form></div>
        <script type="application/json">{!! json_encode(['action'=>route('admin.permissions.store'),'name'=>old('name'),'editing'=>false],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!}</script>
    @endcan
    <div class="card overflow-hidden p-0">
        <div class="overflow-x-auto">
            <table class="table min-w-[640px]">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Roles</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($permissions as $permission)
                        <tr>
                            <td>
                                @can('permissions.edit')
                                    <div data-permission-form></div>
                                    <script type="application/json">{!! json_encode(['action'=>route('admin.permissions.update',$permission),'name'=>$permission->name,'editing'=>true],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!}</script>@else{{ $permission->name }}
                                @endcan
                            </td>
                            <td>{{ $permission->roles_count }}</td>
                            <td>
                                @can('permissions.delete')
                                    <form method="POST" action="{{ route('admin.permissions.destroy', $permission) }}">@csrf
                                        @method('DELETE')<button class="btn-danger">Delete</button></form>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="overflow-x-auto p-4">{{ $permissions->links() }}</div>
</div>@endsection
