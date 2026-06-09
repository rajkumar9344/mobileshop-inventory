@extends('layouts.app')

@section('title', 'Purchase Outstanding Report')

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item"><a href="#">Reports</a></li>
        <li class="breadcrumb-item active">Purchase Outstanding Report</li>
    </ol>
@endsection

@section('content')
    <div class="container-fluid">
        <livewire:reports.purchase-outstanding-report />
    </div>
@endsection
