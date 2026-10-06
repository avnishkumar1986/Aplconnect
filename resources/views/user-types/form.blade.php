@extends('layout.app')
@section('title',$record->exists?'Edit user type':'Create user type')
@section('content')<x-react-crud-mount form-name="user-types" :record="$record" :definition="$definition" :options="$options" :resource="$resource" :action="$record->exists?route('admin.user-types.update',$record):route('admin.user-types.store')" :cancel-url="route('admin.user-types.index')"/>@endsection
