@extends('layout.app')
@section('title',$record->exists?'Edit contact':'Create contact')
@section('content')<x-react-crud-mount form-name="contacts" :record="$record" :definition="$definition" :options="$options" :resource="$resource" :action="$record->exists?route('admin.crud.update',[$resource,$record->id]):route('admin.crud.store',$resource)" :cancel-url="route('admin.crud.index',$resource)"/>@endsection
