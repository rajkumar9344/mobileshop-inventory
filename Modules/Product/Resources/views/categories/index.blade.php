@extends('layouts.app')

@section('title', 'Product Brands')

@section('third_party_stylesheets')
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.25/css/dataTables.bootstrap4.min.css">
@endsection

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item"><a href="{{ route('products.index') }}">Products</a></li>
    <li class="breadcrumb-item active">Brands</li>
    </ol>
@endsection

@section('content')
    @php
        $existing_categories = \Modules\Product\Entities\Category::distinct('category_name')->pluck('category_name')->toArray();
    @endphp
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                @if(session('success'))
                    @include('utils.alerts')
                @endif
                <div class="card">
                    <div class="card-body">
                        <!-- Button trigger modal -->
                        @can('create_product_categories')
                        <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#categoryCreateModal">
                            Add Brand <i class="bi bi-plus"></i>
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

    <!-- Create Modal -->
    @include('product::includes.category-modal')
@endsection

@push('page_scripts')
    {!! $dataTable->scripts() !!}
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
@endpush
