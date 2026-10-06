@extends('layout.app')
@section('title', 'Dashboard')
@section('content')<div id="dashboard-root" data-stats='@json($stats)'
    data-user="{{ auth()->user()->name }}"></div>@endsection
