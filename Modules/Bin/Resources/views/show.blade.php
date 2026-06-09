@extends('layouts.app')

@section('title', 'Bin Details')

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item"><a href="{{ route('bin.index') }}">Bin Master</a></li>
        <li class="breadcrumb-item active">Details</li>
    </ol>
@endsection

@section('content')
    <div class="container-fluid mb-4">
        <div class="row">
            <div class="col-lg-9">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h3 class="card-title mb-0">Bin Details</h3>
                        <a href="{{ route('bin.index') }}" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Back
                        </a>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped mb-0">
                                <tr>
                                    <th>Rack Name</th>
                                    <td>{{ $bin->rack->rack_name }} ({{ $bin->rack->rack_id }})</td>
                                </tr>
                                <tr>
                                    <th>Bin ID</th>
                                    <td>{{ $bin->bin_id }}</td>
                                </tr>
                                <tr>
                                    <th>Bin Name</th>
                                    <td>{{ $bin->bin_name }}</td>
                                </tr>
                                <tr>
                                    <th>Bin Capacity</th>
                                    <td>{{ $bin->capacity }}</td>
                                </tr>
                                <tr>
                                    <th>Status</th>
                                    <td>
                                        <span class="badge {{ $bin->status == 'active' ? 'badge-success' : 'badge-secondary' }}">
                                            {{ ucfirst($bin->status) }}
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Barcode</th>
                                    <td>{{ $bin->barcode ?: 'N/A' }}</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection