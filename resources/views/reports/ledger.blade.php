@extends('layouts.app')

@section('title', 'Customer Ledger Report')

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item"><a href="#">Reports</a></li>
        <li class="breadcrumb-item active">Customer Ledger Report</li>
    </ol>
@endsection

@section('content')
    <div class="container-fluid">
        <livewire:reports.ledger-report />
    </div>
@endsection
