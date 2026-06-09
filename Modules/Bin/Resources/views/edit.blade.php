@extends('layouts.app')

@section('title', 'Edit Bin')

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item"><a href="{{ route('bin.index') }}">Bin Master</a></li>
        <li class="breadcrumb-item active">Edit</li>
    </ol>
@endsection

@section('content')
    <div class="container-fluid mb-4">
        <form id="bin-form" action="{{ route('bin.update', $bin->id) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="row">
                <div class="col-lg-12">
                    @include('utils.alerts')
                </div>
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-body">
                            <div class="form-row">
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label for="rack_id">Rack Name/Number <span class="text-danger">*</span></label>
                                        <select class="form-control" id="rack_id" name="rack_id" required>
                                            <option value="">Select Rack</option>
                                            @foreach($racks as $rack)
                                                <option value="{{ $rack->id }}" {{ $bin->rack_id == $rack->id ? 'selected' : '' }}>{{ $rack->rack_name }} ({{ $rack->rack_id }})</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label for="bin_id">Bin ID <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="bin_id" name="bin_id"
                                            value="{{ $bin->bin_id }}" placeholder="B001" required maxlength="20" pattern="[A-Za-z0-9 _\-]+" title="Only letters, numbers, spaces, hyphens and underscore allowed" oninput="this.value = this.value.replace(/[^A-Za-z0-9_\- ]/g,'').slice(0,20)">
                                    </div>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label for="bin_name">Bin Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="bin_name" name="bin_name"
                                            value="{{ $bin->bin_name }}" placeholder="Bin A" required maxlength="100" title="Max 100 characters" oninput="this.value = this.value.slice(0,100)">
                                    </div>
                                </div>
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label for="capacity">Bin Capacity <span class="text-danger">*</span></label>
                                        <input type="number" class="form-control" id="capacity" name="capacity"
                                            value="{{ $bin->capacity }}" placeholder="Max items/weight" required min="0" max="9999" title="Max 4 digits" oninput="this.value = this.value.replace(/[^0-9]/g,'').slice(0,4)">
                                    </div>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label for="status">Status <span class="text-danger">*</span></label>
                                        <select class="form-control" id="status" name="status" required>
                                            <option value="active" {{ $bin->status == 'active' ? 'selected' : '' }}>Active</option>
                                            <option value="inactive" {{ $bin->status == 'inactive' ? 'selected' : '' }}>Inactive</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label for="barcode">Barcode</label>
                                        <input type="text" class="form-control" id="barcode" name="barcode"
                                            value="{{ $bin->barcode }}" placeholder="Barcode" maxlength="255">
                                        <small class="form-text text-muted">Optional — leave blank to use Bin ID</small>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-4 d-flex justify-content-end">
                                <a href="{{ route('bin.index') }}" class="btn btn-secondary mr-2">Back</a>
                                <button class="btn btn-primary">Update Bin <i class="bi bi-check"></i></button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection