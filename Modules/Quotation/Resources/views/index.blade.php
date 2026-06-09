@extends('layouts.app')

@section('title', 'Quotations')

@section('third_party_stylesheets')
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.25/css/dataTables.bootstrap4.min.css">

    <style>
        /* Custom status badge colors for Quotations listing */
        .badge.status-draft { background: #ffc107; color: #212529; }
        /* Ensure badge padding/shape consistent */
        .badge { padding: 0.35em 0.6em; border-radius: 0.375rem; font-weight: 600; }
    </style>
@endsection

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item active">Quotations</li>
    </ol>
@endsection

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        @can('create_quotations')
                            <a href="{{ route('quotations.create') }}" class="btn btn-primary">
                                Add Quotation <i class="bi bi-plus"></i>
                            </a>
                        @endcan

                        <hr>

                        <div class="table-responsive">
                            {!! $dataTable->table() !!}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('page_scripts')
    {!! $dataTable->scripts() !!}
@endpush
