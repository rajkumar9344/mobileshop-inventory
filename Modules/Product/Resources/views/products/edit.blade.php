@extends('layouts.app')

@section('title', 'Edit Product')

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item"><a href="{{ route('products.index') }}">Products</a></li>
        <li class="breadcrumb-item active">Edit</li>
    </ol>
@endsection

@section('content')
    <div class="container-fluid mb-4">
        <form id="product-form" action="{{ route('products.update', $product->id) }}" method="POST">
            @csrf
            @method('patch')
            <div class="row">
                <div class="col-lg-12">
                    @include('utils.alerts')
                </div>
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-body">
                            {{-- Row 1: Brand | Product Code --}}
                            <div class="form-row">
                                <div class="col-md-6">
                                    <label for="category_id">Brand <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <select class="form-control" name="category_id" id="category_id" required>
                                            <option value="" selected disabled>Select Brand</option>
                                            @php
                                                $cats = \Modules\Product\Entities\Category::where('status', true)
                                                    ->orWhere('id', $product->category_id)
                                                    ->select('id','category_name')
                                                    ->get()
                                                    ->sortBy(function($c){ return $c->category_name; }, SORT_NATURAL|SORT_FLAG_CASE)
                                                    ->values();
                                            @endphp
                                            @foreach($cats as $category)
                                                <option {{ $category->id == ($product->category->id ?? null) ? 'selected' : '' }} value="{{ $category->id }}">{{ $category->category_name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="product_code">Code <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <input type="text" class="form-control" name="product_code" id="product_code" required value="{{ $product->product_code }}" maxlength="50" title="Max 50 characters">
                                            <div class="input-group-append">
                                                <button type="button" id="add-code-btn" class="btn btn-outline-primary" title="Add another code"><i class="bi bi-plus-circle"></i></button>
                                            </div>
                                        </div>
                                        <small id="product_code_hint" class="form-text text-muted">Max 50 characters.</small>
                                        <small id="product_code_error" class="form-text text-danger" style="display:none;"></small>
                                    </div>
                                </div>
                            </div>

                            {{-- Additional Codes --}}
                            @php $hasExtraCodes = collect($productCodes)->where('is_primary', false)->count() > 0; @endphp
                            <div class="form-row" id="additional-codes-row" style="display: {{ $hasExtraCodes ? 'block' : 'none' }};">
                                <div class="col-md-12">
                                    <div id="additional-codes-wrapper">
                                        @foreach($productCodes as $pc)
                                            @if(!$pc->is_primary)
                                            <div class="mb-2 additional-code-row">
                                                <div class="input-group">
                                                    <input type="text" class="form-control" name="additional_codes[]" value="{{ $pc->code }}" maxlength="50" placeholder="Enter code">
                                                    <div class="input-group-append">
                                                        <button type="button" class="btn btn-outline-danger remove-code-btn"><i class="bi bi-trash"></i></button>
                                                    </div>
                                                </div>
                                            </div>
                                            @endif
                                        @endforeach
                                    </div>
                                </div>
                            </div>

                            {{-- Row 2: Product Name | Supplier Name --}}
                            <div class="form-row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="product_name">Product Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="product_name" required value="{{ $product->product_name }}" maxlength="50" title="Max 50 characters" oninput="this.value = this.value.slice(0,50)">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="supplier_id">Supplier Name</label>
                                        <select class="form-control" name="supplier_id" id="supplier_id">
                                            <option value="" selected>Select Supplier</option>
                                            @foreach($suppliers as $id => $name)
                                                <option value="{{ $id }}" {{ $product->supplier_id == $id ? 'selected' : '' }}>{{ $name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>

                            {{-- Row 3: Tax % | Tax Type --}}
                            <div class="form-row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="product_order_tax">Tax (%)</label>
                                        <input type="number" class="form-control" name="product_order_tax" value="{{ $product->product_order_tax }}" min="0" max="99" oninput="this.value = this.value.replace(/[^0-9]/g,'').slice(0,2)">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="product_tax_type">Tax Type</label>
                                        <select class="form-control" name="product_tax_type" id="product_tax_type">
                                            <option value="" {{ old('product_tax_type', $product->product_tax_type ?? 2) == '' ? 'selected' : '' }}>Select Tax Type</option>
                                            <option value="1" {{ old('product_tax_type', $product->product_tax_type ?? 2) == 1 ? 'selected' : '' }}>Exclusive</option>
                                            <option value="2" {{ old('product_tax_type', $product->product_tax_type ?? 2) == 2 ? 'selected' : '' }}>Inclusive</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            {{-- Row 4: Open Quantity | Purchased Quantity | Current Overall Quantity --}}
                            <div class="form-row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="open_quantity">Open Quantity</label>
                                        <input type="number" class="form-control" name="open_quantity" id="open_quantity" value="{{ old('open_quantity', $product->open_quantity ?? 0) }}" placeholder="0" min="0" max="9999" readonly>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="purchase_quantity">Purchase Quantity</label>
                                        <input type="number" class="form-control" name="purchase_quantity" id="purchase_quantity" value="{{ $product->purchase_quantity ?? 0 }}" placeholder="0" min="0" max="9999" readonly>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="product_quantity">Current Quantity (Overall) <span class="text-danger">*</span></label>
                                        <input type="number" class="form-control" name="product_quantity" id="product_quantity" value="{{ $product->product_quantity }}" placeholder="0" min="0" max="9999" readonly>
                                    </div>
                                </div>
                            </div>

                            {{-- Row 5: Alert Quantity | Re-order | Unit --}}
                            <div class="form-row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="product_stock_alert">Alert Quantity <span class="text-danger">*</span></label>
                                        <input type="number" class="form-control" id="product_stock_alert" name="product_stock_alert" required value="{{ $product->product_stock_alert }}" min="0" max="9999" oninput="this.value = this.value.replace(/[^0-9]/g,'').slice(0,4)">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="re_order">Re-order</label>
                                        <input type="number" class="form-control" name="re_order" id="re_order" readonly value="{{ $product->re_order ?? 0 }}">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="product_unit">Unit <span class="text-danger">*</span></label>
                                        <select class="form-control" name="product_unit" id="product_unit" required>
                                            <option value="" selected>Select Unit</option>
                                            @foreach(\Modules\Setting\Entities\Unit::all() as $unit)
                                                <option {{ $product->product_unit == $unit->short_name ? 'selected' : '' }} value="{{ $unit->short_name }}">{{ $unit->name . ' | ' . $unit->short_name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>

                            {{-- Row 6: Purchase Rate | List Price | Buy Price --}}
                            <div class="form-row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="product_cost">Purchase Rate (Net Rate)</label>
                                        <x-currency-input id="product_cost" name="product_cost" class="form-control" :display="old('product_cost', (isset($product) && $product->product_cost !== null) ? number_format($product->product_cost, 2, '.', '') : '')" aria-label="Purchase Rate" maxlength="15" />
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="list_price">List Price</label>
                                        <x-currency-input id="list_price" name="list_price" class="form-control" :display="old('list_price', (isset($product) && $product->list_price !== null) ? number_format($product->list_price, 2, '.', '') : '')" aria-label="List Price" maxlength="15" />
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="buy_price">Buy Price</label>
                                        <x-currency-input id="buy_price" name="buy_price" class="form-control" :display="old('buy_price', (isset($product) && $product->buy_price !== null) ? number_format($product->buy_price, 2, '.', '') : '')" aria-label="Buy Price" maxlength="15" />
                                    </div>
                                </div>
                            </div>

                            {{-- Row 7: Sell Rate | Status --}}
                            <div class="form-row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="product_price">Sell Rate</label>
                                        <x-currency-input id="product_price" name="product_price" class="form-control" :display="old('product_price', (isset($product) && $product->product_price !== null) ? number_format($product->product_price, 2, '.', '') : '')" aria-label="Sell Rate" maxlength="15" />
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="status">Status</label>
                                        <select class="form-control" name="status" id="status">
                                            <option value="active" {{ $product->status == 'active' ? 'selected' : '' }}>Active</option>
                                            <option value="inactive" {{ $product->status == 'inactive' ? 'selected' : '' }}>Inactive</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            {{-- Row 8: Comments --}}
                            <div class="form-row">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="product_note">Comments</label>
                                        <textarea name="product_note" id="product_note" rows="4" class="form-control">{{ $product->product_note }}</textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col-12 d-flex justify-content-end">
                        <a href="{{ route('products.index') }}" class="btn btn-secondary mr-2">Back</a>
                        <button type="submit" class="btn btn-primary">Update Product <i class="bi bi-check"></i></button>
                    </div>
                </div>
        </form>
    </div>
@endsection

@push('page_scripts')
    <script src="{{ asset('js/currency-input.js') }}"></script>
    <script>
        $(document).ready(function () {
            function calculateReOrder() {
                var quantity = parseInt($('#product_quantity').val()) || 0;
                var alert_qty = parseInt($('#product_stock_alert').val()) || 0;
                $('#re_order').val(alert_qty > quantity ? alert_qty - quantity : 0);
            }

            $('#product_quantity, #product_stock_alert').on('input change', calculateReOrder);

            var codeCheckTimeout = null;
            $('#product_code').on('blur', function () {
                clearTimeout(codeCheckTimeout);
                codeCheckTimeout = setTimeout(function () {
                    var code = $('#product_code').val() || '';
                    if (!code) return;
                    $.getJSON('{{ route('products.checkCode') }}', { codes: [code], exclude_id: '{{ $product->id }}' }, function (res) {
                        $('#product_code_error').hide().text('');
                        if (res.exists && res.conflicts && res.conflicts.length) {
                            var c = res.conflicts[0];
                            $('#product_code_error').show().text('Product code already exists' + (c.name ? (' — ' + c.name) : '') + '.');
                        }
                    });
                }, 150);
            });
        });
    </script>
    <script>
        $(document).ready(function () {
            if ($('#additional-codes-wrapper').children().length > 0) {
                $('#additional-codes-row').show();
            }

            $('#add-code-btn').on('click', function () {
                if ($('#additional-codes-wrapper').children().length === 0) {
                    $('#additional-codes-row').show();
                }
                var row = '<div class="mb-2 additional-code-row">' +
                    '<div class="input-group">' +
                    '<input type="text" class="form-control" name="additional_codes[]" maxlength="50" placeholder="Enter code">' +
                    '<div class="input-group-append">' +
                    '<button type="button" class="btn btn-outline-danger remove-code-btn"><i class="bi bi-trash"></i></button>' +
                    '</div></div></div>';
                $('#additional-codes-wrapper').append(row);
            });

            $(document).on('click', '.remove-code-btn', function () {
                $(this).closest('.additional-code-row').remove();
                if ($('#additional-codes-wrapper').children().length === 0) {
                    $('#additional-codes-row').hide();
                }
            });
        });
    </script>
@endpush
