@extends('layouts.app')

@section('title', 'Edit Brand')

@section('breadcrumb')
<ol class="breadcrumb border-0 m-0">
    <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('products.index') }}">Products</a></li>
    <li class="breadcrumb-item"><a href="{{ route('product-categories.index') }}">Brands</a></li>
    <li class="breadcrumb-item active">Edit</li>
</ol>
@endsection

@section('content')
    @php
        $existing_categories = \Modules\Product\Entities\Category::distinct('category_name')->pluck('category_name')->toArray();
    @endphp
    <div class="container-fluid">
        <div class="row justify-content-center">
            <div class="col-md-7">
                {{-- Filter out unique-constraint duplicate messages (we show those as toasts) --}}
                @php
                    $allErrors = $errors && $errors->any() ? $errors->all() : [];
                    $filtered = collect($allErrors)->filter(function($e) {
                        // Common phrases for duplicate/unique validation messages
                        return !\Illuminate\Support\Str::contains($e, ['has already been taken', 'already exists', 'already been taken']);
                    })->values()->all();
                @endphp

                @if(!empty($filtered))
                    @foreach($filtered as $error)
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <div class="alert-body">
                                <span>{{ $error }}</span>
                                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                    <span aria-hidden="true">×</span>
                                </button>
                            </div>
                        </div>
                    @endforeach
                @endif
                <div class="card">
                    <div class="card-body">
                        <form action="{{ route('product-categories.update', $category->id) }}" method="POST">
                            @csrf
                            @method('patch')
                            <div class="form-group">
                                <label class="font-weight-bold" for="category_code">Brand Code <span class="text-danger">*</span></label>
                                <input class="form-control" type="text" name="category_code" id="category_code" required maxlength="15" pattern="[A-Za-z0-9_-]+" title="Only letters, numbers, hyphen and underscore" value="{{ $category->category_code }}">
                            </div>
                            <div class="form-group">
                                <label class="font-weight-bold" for="category_name">Brand Name <span class="text-danger">*</span></label>
                                <input class="form-control" type="text" name="category_name" id="category_name" required maxlength="100" value="{{ $category->category_name }}">
                            </div>
                            <div class="form-group">
                                <label class="font-weight-bold" for="status">Status</label>
                                <select name="status" id="status" class="form-control">
                                    <option value="1" {{ $category->status ? 'selected' : '' }}>Active</option>
                                    <option value="0" {{ !$category->status ? 'selected' : '' }}>Inactive</option>
                                </select>
                            </div>
                            <div class="form-group d-flex justify-content-end">
                                <a href="{{ route('product-categories.index') }}" class="btn btn-secondary mr-2">Back</a>
                                <button type="submit" class="btn btn-primary">Update <i class="bi bi-check"></i></button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('page_scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        var select = document.getElementById('category_name_select');
        var customInput = document.getElementById('custom_category_name_input');
        var form = select.closest('form');
        select.addEventListener('change', function() {
            if (this.value === 'Other') {
                customInput.style.display = 'block';
                customInput.required = true;
            } else {
                customInput.style.display = 'none';
                customInput.required = false;
            }
        });
        form.addEventListener('submit', function(e) {
            if (select.value === 'Other' && customInput.value.trim() !== '') {
                var option = document.createElement('option');
                option.value = customInput.value.trim();
                option.text = customInput.value.trim();
                option.selected = true;
                select.appendChild(option);
                select.value = customInput.value.trim();
            }
        });
    });
</script>
@endpush

