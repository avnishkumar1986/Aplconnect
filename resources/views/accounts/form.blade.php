@extends('layout.app')
@section('title',$record->exists?'Edit login account':'Create login account')
@section('content')
<x-react-crud-mount form-name="accounts" :record="$record" :definition="$definition" :options="$options" :resource="$resource" :action="$record->exists?route('admin.crud.update',[$resource,$record->id]):route('admin.crud.store',$resource)" :cancel-url="route('admin.crud.index',$resource)"/>
<script>document.getElementById('react-accounts-form-props').textContent=JSON.stringify({...JSON.parse(document.getElementById('react-accounts-form-props').textContent),roles:@json($roles)});</script>
@endsection
