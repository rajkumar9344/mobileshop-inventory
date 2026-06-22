@extends('layouts.app')

@section('title', 'Create Product')

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item"><a href="{{ route('products.index') }}">Products</a></li>
        <li class="breadcrumb-item active">Add</li>
    </ol>
@endsection

@section('content')
    <div class="container-fluid">
        <form id="product-form" action="{{ route('products.store') }}" method="POST">
            @csrf
            <div class="row">
                <div class="col-lg-12">
                    @include('utils.alerts')
                </div>
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-body">
                            {{-- Row 1: Brand (optional) | Code (auto-generated, read-only) --}}
                            <div class="form-row">
                                <div class="col-md-6">
                                    <label for="category_id">Brand</label>
                                    <div class="input-group">
                                        <select class="form-control" name="category_id" id="category_id">
                                            <option value="">— None —</option>
                                            @php
                                                $cats = \Modules\Product\Entities\Category::where('status', true)
                                                    ->select('id','category_name')
                                                    ->get()
                                                    ->sortBy(function($c){ return $c->category_name; }, SORT_NATURAL|SORT_FLAG_CASE)
                                                    ->values();
                                            @endphp
                                            @foreach($cats as $category)
                                                <option value="{{ $category->id }}" {{ old('category_id') == $category->id ? 'selected' : '' }}>{{ $category->category_name }}</option>
                                            @endforeach
                                        </select>
                                        <div class="input-group-append d-flex">
                                            @can('create_product_categories')
                                                <button data-toggle="modal" data-target="#categoryCreateModal" class="btn btn-outline-primary" type="button">
                                                    Add
                                                </button>
                                            @endcan
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Code</label>
                                        <input type="text" class="form-control" placeholder="Auto-generated" readonly>
                                    </div>
                                </div>
                            </div>

                            {{-- Row 2: Product Name --}}
                            <div class="form-row">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="product_name">Product Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="product_name" required value="{{ old('product_name') }}" maxlength="50" title="Max 50 characters" oninput="this.value = this.value.slice(0,50)">
                                    </div>
                                </div>
                            </div>

                            {{-- Row 3: VAT % | VAT Type | Unit --}}
                            <div class="form-row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="product_order_tax">VAT (%)</label>
                                        <input type="number" class="form-control" name="product_order_tax" value="{{ old('product_order_tax', 5) }}" min="0" max="99" oninput="this.value = this.value.replace(/[^0-9]/g,'').slice(0,2)">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="product_tax_type">VAT Type</label>
                                        <select class="form-control" name="product_tax_type" id="product_tax_type">
                                            <option value="" {{ old('product_tax_type', 2) == '' ? 'selected' : '' }}>Select VAT Type</option>
                                            <option value="1" {{ old('product_tax_type', 2) == 1 ? 'selected' : '' }}>Exclusive</option>
                                            <option value="2" {{ old('product_tax_type', 2) == 2 ? 'selected' : '' }}>Inclusive</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="product_unit">Unit <span class="text-danger">*</span></label>
                                        <select class="form-control" name="product_unit" id="product_unit" required>
                                            <option value="" disabled>Select Unit</option>
                                            @php $first = true; @endphp
                                            @foreach(\Modules\Setting\Entities\Unit::all() as $unit)
                                                <option value="{{ $unit->short_name }}" {{ $first ? 'selected' : '' }}>{{ $unit->name . ' | ' . $unit->short_name }}</option>
                                                @php $first = false; @endphp
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>

                            {{-- Row 4: Open Quantity | Purchased Quantity | Current Overall Quantity --}}
                            <div class="form-row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="open_quantity">Open Quantity</label>
                                        <input type="number" class="form-control" name="open_quantity" id="open_quantity" min="0" max="9999" oninput="this.value = this.value.replace(/[^0-9]/g,'').slice(0,4)">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="purchase_quantity">Purchase Quantity</label>
                                        <input type="number" class="form-control" name="purchase_quantity" id="purchase_quantity" value="0" placeholder="0" min="0" max="9999" readonly>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="product_quantity">Current Quantity (Overall) <span class="text-danger">*</span></label>
                                        <input type="number" class="form-control" name="product_quantity" id="product_quantity" value="0" placeholder="0" min="0" max="9999" readonly>
                                    </div>
                                </div>
                            </div>

                            {{-- Row 5: Alert Quantity | Re-order | Status --}}
                            <div class="form-row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="product_stock_alert">Alert Quantity <span class="text-danger">*</span></label>
                                        <input type="number" class="form-control" name="product_stock_alert" id="product_stock_alert" required value="{{ old('product_stock_alert', 0) }}" min="0" max="9999" oninput="this.value = this.value.replace(/[^0-9]/g,'').slice(0,4)">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="re_order">Re-order</label>
                                        <input type="number" class="form-control" name="re_order" id="re_order" readonly value="0">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="status">Status</label>
                                        <select class="form-control" name="status" id="status">
                                            <option value="active" selected>Active</option>
                                            <option value="inactive">Inactive</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            {{-- Row 8: Comments --}}
                            <div class="form-row">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="product_note">Comments</label>
                                        <textarea name="product_note" id="product_note" rows="4" class="form-control"></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 mt-3 d-flex justify-content-end">
                    <a href="{{ route('products.index') }}" class="btn btn-secondary mr-2">Back</a>
                    <button type="submit" class="btn btn-primary">Create Product <i class="bi bi-check"></i></button>
                </div>
        </form>
    </div>

    @include('product::includes.category-modal')
@endsection

@push('page_scripts')
    <script src="{{ asset_v('js/currency-input.js') }}"></script>
    <script>
        $(document).ready(function () {
            $('#product-form').submit(function (e) {
                var product_unit = $('#product_unit').val();
                if (!product_unit || product_unit === "") {
                    alert('Please select a product unit.');
                    e.preventDefault();
                    return false;
                }
            });

            function syncStockWithOpen() {
                var open_qty = parseInt($('#open_quantity').val()) || 0;
                var purchase_qty = parseInt($('#purchase_quantity').val()) || 0;
                $('#product_quantity').val(open_qty + purchase_qty);
            }

            function calculateReOrder() {
                var quantity = parseInt($('#product_quantity').val()) || 0;
                var alert_qty = parseInt($('#product_stock_alert').val()) || 0;
                $('#re_order').val(alert_qty > quantity ? alert_qty - quantity : 0);
            }

            $('#open_quantity').on('input change', function () {
                syncStockWithOpen();
                calculateReOrder();
            });

            $('#product_stock_alert').on('input change', calculateReOrder);

            syncStockWithOpen();
            calculateReOrder();
        });
    </script>
@endpush
