@extends('layouts.app')

@section('title', 'Current Stock Report')

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item active">Current Stock Report</li>
    </ol>
@endsection

@section('content')
    <div class="container-fluid">
        <livewire:reports.current-stock-report/>
    </div>
@endsection
