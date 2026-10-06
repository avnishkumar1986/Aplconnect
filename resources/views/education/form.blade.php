@extends('layout.app')
@section('title',$record->exists?'Edit education':'Create education')
@section('content')<x-react-crud-mount form-name="education" :record="$record" :definition="$definition" :options="$options" :resource="$resource" :action="$record->exists?route('admin.crud.update',[$resource,$record->id]):route('admin.crud.store',$resource)" :cancel-url="route('admin.crud.index',$resource)"/>@endsection
