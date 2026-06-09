@extends('layouts.app')

@section('title', 'Rack Details')

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item"><a href="{{ route('rack.index') }}">Rack Master</a></li>
        <li class="breadcrumb-item active">Details</li>
    </ol>
@endsection

@section('content')
    <div class="container-fluid mb-4">
        <div class="row">
            <div class="col-lg-9">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h3 class="card-title mb-0">Rack Details</h3>
                        <a href="{{ route('rack.index') }}" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Back
                        </a>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped mb-0">
                                <tr>
                                    <th>Rack ID</th>
                                    <td>{{ $rack->rack_id }}</td>
                                </tr>
                                <tr>
                                    <th>Rack Name</th>
                                    <td>{{ $rack->rack_name }}</td>
                                </tr>
                                <tr>
                                    <th>Barcode</th>
                                    <td>{{ $rack->barcode ?: 'N/A' }}</td>
                                </tr>
                               
                            </table>
                        </div>
                    </div>
                </div>
            </div>

         
        </div>
    </div>
@endsection