@extends('layouts.app')

@section('title', 'Customers Payment Report')

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item active">Customers Payment Report</li>
    </ol>
@endsection

@section('content')
    <div class="container-fluid">
        <livewire:reports.customers-payment-report />
    </div>
@endsection
