@extends('layouts.app')

@section('title', 'Edit Rack')

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item"><a href="{{ route('rack.index') }}">Rack Master</a></li>
        <li class="breadcrumb-item active">Edit</li>
    </ol>
@endsection

@section('content')
    <div class="container-fluid mb-4">
        <form id="rack-form" action="{{ route('rack.update', $rack->id) }}" method="POST">
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
                                        <label for="rack_id">Rack ID <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="rack_id" name="rack_id"
                                            value="{{ $rack->rack_id }}" placeholder="R001" required readonly maxlength="20" pattern="[A-Za-z0-9 _\-]+" title="Only letters, numbers, spaces, hyphens and underscore allowed" oninput="this.value = this.value.replace(/[^A-Za-z0-9_\- ]/g,'').slice(0,20)">
                                    </div>
                                </div>
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label for="rack_name">Rack Name/Number <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="rack_name" name="rack_name"
                                            value="{{ $rack->rack_name }}" placeholder="Rack A or Rack 5" required maxlength="100" title="Max 100 characters" oninput="this.value = this.value.slice(0,100)">
                                    </div>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label for="barcode">Barcode</label>
                                        <input type="text" class="form-control" id="barcode" name="barcode"
                                            value="{{ $rack->barcode }}" placeholder="Barcode" maxlength="255">
                                        <small class="form-text text-muted">Optional — leave blank to use Rack ID</small>
                                    </div>
                                </div>
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label for="status">Status <span class="text-danger">*</span></label>
                                        <select class="form-control" id="status" name="status" required>
                                            <option value="Active" {{ $rack->status == 'Active' ? 'selected' : '' }}>Active</option>
                                            <option value="Inactive" {{ $rack->status == 'Inactive' ? 'selected' : '' }}>Inactive</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-4 d-flex justify-content-end">
                                <a href="{{ route('rack.index') }}" class="btn btn-secondary mr-2">Back</a>
                                <button class="btn btn-primary">Update Rack <i class="bi bi-check"></i></button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection