@extends('layouts.app')

@section('title', 'Edit Subcategory')

@section('breadcrumb')
<ol class="breadcrumb border-0 m-0">
    <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('products.index') }}">Products</a></li>
    <li class="breadcrumb-item"><a href="{{ route('product-subcategories.index') }}">Sub-categories</a></li>
    <li class="breadcrumb-item active">Edit</li>
</ol>
@endsection

@section('content')
    <div class="container-fluid">
        <div class="row justify-content-center">
            <div class="col-md-7">
                {{-- Filter out unique-constraint duplicate messages (we show those as toasts) --}}
                @php
                    $allErrors = $errors && $errors->any() ? $errors->all() : [];
                    $filtered = collect($allErrors)->filter(function($e) {
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
                        <form action="{{ route('product-subcategories.update', $subcategory->id) }}" method="POST">
                            @csrf
                            @method('patch')

                            <div class="form-group">
                                <label class="font-weight-bold" for="category_id">Brand <span class="text-danger">*</span></label>
                                <select class="form-control" name="category_id" id="category_id" required>
                                    <option value="" selected disabled>Select Brand</option>
                                    @php
                                        $cats = \Modules\Product\Entities\Category::where('status', true)
                                            ->select('id','category_name')
                                            ->get()
                                            ->sortBy(function($c){ return $c->category_name; }, SORT_NATURAL|SORT_FLAG_CASE)
                                            ->values();
                                    @endphp
                                    @foreach($cats as $cat)
                                        <option value="{{ $cat->id }}" {{ $subcategory->category_id == $cat->id ? 'selected' : '' }}>{{ $cat->category_name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="font-weight-bold" for="subcategory_name">Subcategory Name <span class="text-danger">*</span></label>
                                <input class="form-control" type="text" name="subcategory_name" required maxlength="100" value="{{ $subcategory->subcategory_name }}">
                            </div>

                            <div class="form-group">
                                <label class="font-weight-bold" for="status">Status</label>
                                <select name="status" id="status" class="form-control">
                                    <option value="1" {{ $subcategory->status ? 'selected' : '' }}>Active</option>
                                    <option value="0" {{ !$subcategory->status ? 'selected' : '' }}>Inactive</option>
                                </select>
                            </div>

                            <div class="form-group d-flex justify-content-end">
                                <a href="{{ route('product-subcategories.index') }}" class="btn btn-secondary mr-2">Back</a>
                                <button type="submit" class="btn btn-primary">Update <i class="bi bi-check"></i></button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
