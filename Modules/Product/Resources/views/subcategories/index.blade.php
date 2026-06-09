@extends('layouts.app')

@section('title', 'Product Subcategories')

@section('third_party_stylesheets')
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.25/css/dataTables.bootstrap4.min.css">
@endsection

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item"><a href="{{ route('products.index') }}">Products</a></li>
        <li class="breadcrumb-item active">Sub-categories</li>
    </ol>
@endsection

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                @if(session('success'))
                    @include('utils.alerts')
                @endif
                <div class="card">
                    <div class="card-body">
                        @can('create_product_subcategories')
                        <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#subcategoryCreateModal">
                            Add Sub-category <i class="bi bi-plus"></i>
                        </button>
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

    @include('product::subcategories.includes.subcategory-modal')
@endsection

@push('page_scripts')
    {!! $dataTable->scripts() !!}
@endpush
