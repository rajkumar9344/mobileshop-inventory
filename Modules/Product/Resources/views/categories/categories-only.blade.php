@extends('layouts.app')

@section('title', 'Brands')

@section('breadcrumb')
<ol class="breadcrumb border-0 m-0">
    <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('products.index') }}">Products</a></li>
    <li class="breadcrumb-item active">Brands</li>
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
                        @can('create_product_categories')
                        <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#categoryCreateModal">
                            Add Brand <i class="bi bi-plus"></i>
                        </button>
                        @endcan

                        <hr>

                        <div class="table-responsive">
                            {!! $dataTable->table(['class' => 'table table-bordered']) !!}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @include('product::includes.category-modal')
@endsection

@push('page_scripts')
    {!! $dataTable->scripts() !!}
@endpush
