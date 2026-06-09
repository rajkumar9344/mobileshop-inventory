@extends('layouts.app')

@section('title', 'Print Bin Barcode')

@push('page_css')
    @livewireStyles
@endpush

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item active">Print Bin Barcode</li>
    </ol>
@endsection

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <livewire:search-bin/>
            </div>
        </div>

        <div class="row mt-4">
            {{-- <div class="col-md-12">
                <div class="alert alert-info">
                    <strong>NOTE: Bin codes support alphanumeric (Code128) barcode symbology.</strong>
                </div>
            </div> --}}
            <div class="col-md-12">
                <livewire:barcode.bin-table/>
            </div>
        </div>
    </div>
@endsection
