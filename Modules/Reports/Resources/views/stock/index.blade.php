@extends('layouts.app')

@section('title', 'Purchase Order Report')

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item active">Purchase Order Report</li>
    </ol>
@endsection

@section('content')
    <div class="container-fluid">
        <livewire:reports.stock-report :categories="\Modules\Product\Entities\Category::orderBy('category_name','asc')->get()"/>
    </div>
@endsection