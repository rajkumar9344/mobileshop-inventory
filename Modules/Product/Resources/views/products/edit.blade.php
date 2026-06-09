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
        <form id="product-form" action="{{ route('products.update', $product->id) }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('patch')
            <div class="row">
                <div class="col-lg-12">
                    @include('utils.alerts')
                </div>
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-body">
                            {{-- Row 1: Brand | Subcategory | Product Code --}}
                            <div class="form-row">
                                    <div class="col-md-4">
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
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="subcategory_id">Subcategory <span class="text-danger">*</span></label>
                                            <select class="form-control" name="subcategory_id" id="subcategory_id" required>
                                                <option value="" disabled>Select Subcategory</option>
                                                @if($product->subcategory)
                                                    <option value="{{ $product->subcategory->id }}" selected>{{ $product->subcategory->subcategory_name }}</option>
                                                @endif
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="product_code">Code <span class="text-danger">*</span></label>
                                                <div class="input-group">
                                                    <input type="text" class="form-control" name="product_code" id="product_code" required value="{{ $product->product_code }}" maxlength="50" title="Max 50 characters">
                                                    <div class="input-group-append">
                                                        <button type="button" id="add-code-btn" class="btn btn-outline-primary" title="Add another code"><i class="bi bi-plus-circle"></i></button>
                                                    </div>
                                                </div>
                                                <small id="product_code_hint" class="form-text text-muted">Max 50 characters. If you select EAN/UPC, enter numeric code of correct length.</small>
                                                <small id="product_code_error" class="form-text text-danger" style="display:none;"></small>
                                            </div>
                                    </div>
                            </div>

                            {{-- Additional Codes (hidden unless product has extras) --}}
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

                            {{-- Row 2: Product Name | Equivalent Product's Code | MRP --}}
                            <div class="form-row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="product_name">Product Name <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" name="product_name" required value="{{ $product->product_name }}" maxlength="50" title="Max 50 characters" oninput="this.value = this.value.slice(0,50)">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="alternative_number">Equivalent Product's Code</label>
                                            <input type="text" class="form-control" name="alternative_number" value="{{ $product->alternative_number }}" maxlength="50" title="Max 50 characters">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="mrp">MRP <span class="text-danger">*</span></label>
                                            <x-currency-input
                                                id="mrp"
                                                name="mrp"
                                                class="form-control"
                                                :display="old('mrp', (isset($product) && $product->mrp !== null) ? number_format($product->mrp, 2, '.', '') : '')"
                                                aria-label="MRP"
                                                maxlength="15"
                                                required
                                            />
                                        </div>
                                    </div>
                            </div>

                            {{-- Row 3: Tax % | Tax Type | HSN --}}
                            <div class="form-row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="product_order_tax">Tax (%)</label>
                                            <input type="number" class="form-control" name="product_order_tax" value="{{ $product->product_order_tax }}" min="0" max="99" oninput="this.value = this.value.replace(/[^0-9]/g,'').slice(0,2)">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="product_tax_type">Tax type</label>
                                            <select class="form-control" name="product_tax_type" id="product_tax_type">
                                                <option value="" {{ old('product_tax_type', $product->product_tax_type ?? 2) == '' ? 'selected' : '' }}>Select Tax Type</option>
                                                <option value="1" {{ old('product_tax_type', $product->product_tax_type ?? 2) == 1 ? 'selected' : '' }}>Exclusive</option>
                                                <option value="2" {{ old('product_tax_type', $product->product_tax_type ?? 2) == 2 ? 'selected' : '' }}>Inclusive</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="hsn">HSN <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" name="hsn" id="hsn" value="{{ $product->hsn }}" maxlength="15" pattern="[0-9]+" title="Numbers only" oninput="this.value = this.value.replace(/[^0-9]/g,'').slice(0,15)">
                                            <div class="form-check mt-1">
                                                <input class="form-check-input" type="checkbox" id="hsn_unknown" name="hsn_unknown" value="1" {{ empty($product->hsn) ? 'checked' : '' }}>
                                                <label class="form-check-label" for="hsn_unknown">HSN unknown</label>
                                            </div>
                                        </div>
                                    </div>
                            </div>

                            {{-- Row 4: Rack | Bin | Supplier --}}
                            <div class="form-row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="rack_no">Rack No <span class="text-danger">*</span></label>
                                            <select class="form-control" name="rack_no" id="rack_no" required>
                                                <option value="" selected disabled>Select Rack</option>
                                                @php $sortedRacks = collect($racks)->sortBy(function($v){ return $v; }, SORT_NATURAL|SORT_FLAG_CASE)->values(); @endphp
                                                @foreach($sortedRacks as $rack)
                                                    <option value="{{ $rack }}" {{ $product->rack_no == $rack ? 'selected' : '' }}>{{ $rack }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="bin_no">Bin No <span class="text-danger">*</span></label>
                                            <select class="form-control" name="bin_no" id="bin_no" required>
                                                <option value="" selected disabled>Select Bin</option>
                                                @php $sortedBins = collect($bins)->sortBy(function($v){ return $v; }, SORT_NATURAL|SORT_FLAG_CASE)->values(); @endphp
                                                @foreach($sortedBins as $bin)
                                                    <option value="{{ $bin }}" {{ $product->bin_no == $bin ? 'selected' : '' }}>{{ $bin }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
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

                            {{-- Row 5: Open Quantity | Purchased Quantity | Current Overall Quantity --}}
                            <div class="form-row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="open_quantity">Open Quantity</label>
                                            <input type="number" class="form-control" name="open_quantity" id="open_quantity" value="{{ old('open_quantity', $product->open_quantity ?? 0) }}" placeholder="0" min="0" max="9999" readonly oninput="this.value = this.value.replace(/[^0-9]/g,'').slice(0,4)">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="purchase_quantity">Purchase Quantity</label>
                                            <input type="number" class="form-control" name="purchase_quantity" id="purchase_quantity" value="{{ $product->purchase_quantity ?? 0 }}" placeholder="0" min="0" max="9999" readonly oninput="this.value = this.value.replace(/[^0-9]/g,'').slice(0,4)">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="product_quantity">Current quantity(Overall) <span class="text-danger">*</span></label>
                                            <input type="number" class="form-control" name="product_quantity" id="product_quantity" value="{{ $product->product_quantity }}" placeholder="0" min="0" max="9999" readonly oninput="this.value = this.value.replace(/[^0-9]/g,'').slice(0,4)">
                                        </div>
                                    </div>
                            </div>

                            {{-- Row 6: Alert Quantity | Reorder | Unit --}}
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
                                            <label for="product_unit">Unit <i class="bi bi-question-circle-fill text-info" data-toggle="tooltip" data-placement="top" title="This short text will be placed after Product Quantity."></i> <span class="text-danger">*</span></label>
                                            <select class="form-control" name="product_unit" id="product_unit" required>
                                                <option value="" selected >Select Unit</option>
                                                @foreach(\Modules\Setting\Entities\Unit::all() as $unit)
                                                    <option {{ $product->product_unit == $unit->short_name ? 'selected' : '' }} value="{{ $unit->short_name }}">{{ $unit->name . ' | ' . $unit->short_name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                            </div>

                            {{-- Row 7: Purchase Rate | List Price | Buy Price --}}
                            <div class="form-row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="product_cost">Purchase Rate (Net Rate)</label>
                                            <x-currency-input
                                                id="product_cost"
                                                name="product_cost"
                                                class="form-control"
                                                :display="old('product_cost', (isset($product) && $product->product_cost !== null) ? number_format($product->product_cost, 2, '.', '') : '')"
                                                aria-label="Purchase Rate (Net Rate)"
                                                maxlength="15"
                                            />
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="list_price">List Price</label>
                                            <x-currency-input
                                                id="list_price"
                                                name="list_price"
                                                class="form-control"
                                                :display="old('list_price', (isset($product) && $product->list_price !== null) ? number_format($product->list_price, 2, '.', '') : '')"
                                                aria-label="List Price"
                                                maxlength="15"
                                            />
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="buy_price">Buy Price</label>
                                            <x-currency-input
                                                id="buy_price"
                                                name="buy_price"
                                                class="form-control"
                                                :display="old('buy_price', (isset($product) && $product->buy_price !== null) ? number_format($product->buy_price, 2, '.', '') : '')"
                                                aria-label="Buy Price"
                                                maxlength="15"
                                            />
                                        </div>
                                    </div>
                            </div>

                            {{-- Row 8: Sell Rate | Status | Barcode Symbology --}}
                            <div class="form-row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="product_price">Sell Rate</label>
                                            <x-currency-input
                                                id="product_price"
                                                name="product_price"
                                                class="form-control"
                                                :display="old('product_price', (isset($product) && $product->product_price !== null) ? number_format($product->product_price, 2, '.', '') : '')"
                                                aria-label="Sell Rate"
                                                maxlength="15"
                                            />
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="status">Status</label>
                                            <select class="form-control" name="status" id="status">
                                                <option value="active" {{ $product->status == 'active' ? 'selected' : '' }}>Active</option>
                                                <option value="inactive" {{ $product->status == 'inactive' ? 'selected' : '' }}>Inactive</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="barcode_symbology">Barcode Symbology <span class="text-danger">*</span></label>
                                            <select class="form-control" name="product_barcode_symbology" id="barcode_symbology" required>
                                                <option value="" selected disabled>Select Symbology</option>
                                                <option {{ $product->product_barcode_symbology == 'C128' ? 'selected' : '' }} value="C128">Code 128</option>
                                                <option {{ $product->product_barcode_symbology == 'C39' ? 'selected' : '' }} value="C39">Code 39</option>
                                                <option {{ $product->product_barcode_symbology == 'UPCA' ? 'selected' : '' }} value="UPCA">UPC-A</option>
                                                <option {{ $product->product_barcode_symbology == 'UPCE' ? 'selected' : '' }} value="UPCE">UPC-E</option>
                                                <option {{ $product->product_barcode_symbology == 'EAN13' ? 'selected' : '' }} value="EAN13">EAN-13</option>
                                                <option {{ $product->product_barcode_symbology == 'EAN8' ? 'selected' : '' }} value="EAN8">EAN-8</option>
                                            </select>
                                            <small class="form-text text-muted">Use EAN/UPC only for retail barcodes (numeric).</small>
                                        </div>
                                    </div>
                            </div>

                            <div class="form-row">
                                <div class="col-md-8 d-flex flex-column">
                                    <div class="form-group d-flex flex-column flex-grow-1">
                                        <label for="product_note">Compatibility</label>
                                        <textarea name="product_note" id="product_note" rows="4" class="form-control flex-grow-1">{{ $product->product_note }}</textarea>
                                    </div>
                                </div>
                                <div class="col-md-4 d-flex flex-column">
                                    <div class="form-group d-flex flex-column flex-grow-1">
                                        <label for="image">Product Images <i class="bi bi-question-circle-fill text-info" data-toggle="tooltip" data-placement="top" title="Max Files: 3, Max File Size: 1MB, Image Size: 400x400"></i></label>
                                        <div class="dropzone d-flex flex-wrap align-items-center justify-content-center flex-grow-1" id="document-dropzone" style="min-height: 120px;">
                                            <div class="dz-message" data-dz-message>
                                                <i class="bi bi-cloud-arrow-up"></i>
                                            </div>
                                        </div>
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

@section('third_party_scripts')
    <script src="{{ asset('js/dropzone.js') }}"></script>
@endsection

@push('page_scripts')
    <script src="{{ asset('js/currency-input.js') }}"></script>
    <script>
        var uploadedDocumentMap = {}
        Dropzone.options.documentDropzone = {
            url: '{{ route('dropzone.upload') }}',
            maxFilesize: 1,
            acceptedFiles: '.jpg, .jpeg, .png',
            maxFiles: 3,
            addRemoveLinks: true,
            dictRemoveFile: "<i class='bi bi-x-circle text-danger'></i> remove",
            headers: {
                'X-CSRF-TOKEN': "{{ csrf_token() }}"
            },
            success: function (file, response) {
                $('form').append('<input type="hidden" name="document[]" value="' + response.name + '">');
                uploadedDocumentMap[file.name] = response.name;
            },
            removedfile: function (file) {
                file.previewElement.remove();
                var name = '';
                if (typeof file.file_name !== 'undefined') {
                    name = file.file_name;
                } else {
                    name = uploadedDocumentMap[file.name];
                }
                $('form').find('input[name="document[]"][value="' + name + '"]').remove();
            },
            init: function () {
                // If there are images, add them to Dropzone
                @if(isset($product) && $product->getMedia('images'))
                var files = {!! json_encode($product->getMedia('images')) !!};
                for (var i in files) {
                    var file = files[i];
                    this.options.addedfile.call(this, file);
                    this.options.thumbnail.call(this, file, file.original_url);
                    file.previewElement.classList.add('dz-complete');
                    $('form').append('<input type="hidden" name="document[]" value="' + file.file_name + '">');
                }
                @endif
            }
        }
    </script>

    <script>
        $(document).ready(function () {
            // Form submit: do not mask/unmask purchase/sell rate; keep behaviour like other fields
            $('#product-form').submit(function () {
                // No special handling for product_cost/product_price — submit as-is
            });

            // HSN unknown checkbox toggling
            function toggleHsnField() {
                if ($('#hsn_unknown').is(':checked')) {
                    $('#hsn').prop('disabled', true).removeAttr('required');
                } else {
                    $('#hsn').prop('disabled', false).attr('required', 'required');
                }
            }

            // initialize on page load
            toggleHsnField();
            $('#hsn_unknown').on('change', toggleHsnField);

            // Calculate re-order based on actual stock and alert quantity
            function calculateReOrder() {
                var quantity = parseInt($('#product_quantity').val()) || 0;
                var alert_qty = parseInt($('#product_stock_alert').val()) || 0;
                var reorder = 0;
                if (alert_qty > quantity) {
                    reorder = alert_qty - quantity;
                }
                $('#re_order').val(reorder);
            }

            // bind events to actual stock and alert inputs (do not overwrite stock from open quantity)
            $('#product_quantity, #product_stock_alert').on('input change', calculateReOrder);

            // Load subcategories via AJAX for a given category, optionally pre-selecting a subcategory
            function loadSubcategories(categoryId, selectedSubcategoryId) {
                if (!categoryId) return;
                $.ajax({
                    url: '{{ route("get-subcategories") }}',
                    type: 'GET',
                    data: { category_id: categoryId },
                    success: function(data) {
                        $('#subcategory_id').empty();
                        $('#subcategory_id').append('<option value="">Select Subcategory</option>');
                        var items = [];
                        $.each(data, function(index, value) { items.push({id: index, name: value}); });
                        items.sort(function(a,b){ return a.name.localeCompare(b.name); });
                        items.forEach(function(item){
                            var selected = (String(item.id) === String(selectedSubcategoryId)) ? ' selected' : '';
                            $('#subcategory_id').append('<option value="' + item.id + '"' + selected + '>' + item.name + '</option>');
                        });
                    }
                });
            }

            // On page load: populate subcategories for the current brand and keep current selection
            var initialCategoryId = $('#category_id').val();
            var initialSubcategoryId = '{{ $product->subcategory_id }}';
            if (initialCategoryId) {
                loadSubcategories(initialCategoryId, initialSubcategoryId);
            }

            // On brand change: reload subcategories for newly selected brand
            $('#category_id').change(function() {
                var categoryId = $(this).val();
                $('#subcategory_id').empty();
                $('#subcategory_id').append('<option value="">Select Subcategory</option>');
                if (categoryId) {
                    loadSubcategories(categoryId, null);
                }
            });

            // Constants for better maintainability
            const AJAX_TIMEOUT = 10000; // 10 seconds
            const MAX_PRODUCT_CODE_LENGTH = 50;

            // Cache DOM elements for better performance
            const $binNo = $('#bin_no');
            const $rackNo = $('#rack_no');
            const $productCode = $('#product_code');
            const $productCodeError = $('#product_code_error');

            // Handle bin dropdown for rack selection
            function initializeBinDropdown() {
                const initialBinValue = $binNo.val();
                $binNo.prop('disabled', !initialBinValue);
            }

            function loadBinsForRack(rackId, callback) {
                if (!rackId) {
                    $binNo.prop('disabled', true).empty().append('<option value="">Select Bin</option>');
                    return;
                }

                const currentBin = $binNo.val();
                $binNo.prop('disabled', true).empty().append('<option value="">Loading...</option>');

                $.ajax({
                    url: '{{ route("get-bins") }}',
                    type: 'GET',
                    data: { rack_id: rackId },
                    timeout: AJAX_TIMEOUT,
                    success: function(data) {
                        $binNo.empty().append('<option value="">Select Bin</option>');
                        var bins = [];
                        $.each(data, function(index, value) { bins.push(value); });
                        bins.sort(function(a,b){ return String(a).localeCompare(String(b)); });
                        bins.forEach(function(value){
                            $binNo.append('<option value="' + value + '">' + value + '</option>');
                        });

                        // Re-select previous value if it exists
                        if (currentBin && $binNo.find('option[value="' + currentBin + '"]').length) {
                            $binNo.val(currentBin);
                        }

                        $binNo.prop('disabled', false);
                        if (callback) callback();
                    },
                    error: function(xhr, status, error) {
                        console.error('Failed to load bins for rack:', rackId, error);
                        $binNo.empty().append('<option value="">Select Bin</option>');
                        $binNo.prop('disabled', false);

                        // Show user-friendly error message for timeout
                        if (status === 'timeout') {
                            alert('Request timed out while loading bins. Please try again.');
                        }
                    }
                });
            }

            // Initialize bin dropdown state
            initializeBinDropdown();

            // Handle rack changes
            $rackNo.change(function() {
                loadBinsForRack($(this).val());
            });

            // Load bins for initially selected rack
            const initialRack = $rackNo.val();
            if (initialRack) {
                loadBinsForRack(initialRack);
            }
        });
    </script>
    <script>
        (function () {
            function symRules(sym) {
                sym = (sym || '').toUpperCase();
                switch (sym) {
                    case 'EAN13': return { type: 'numeric', regex: /^\d{13}$/, message: 'Enter exactly 13 digits for EAN-13.' };
                    case 'EAN8':  return { type: 'numeric', regex: /^\d{8}$/, message: 'Enter exactly 8 digits for EAN-8.' };
                    case 'UPCA':  return { type: 'numeric', regex: /^\d{12}$/, message: 'Enter exactly 12 digits for UPC-A.' };
                    case 'UPCE':  return { type: 'numeric', regex: /^\d{6}(?:\d{2})?$/, message: 'Enter 6 or 8 digits for UPC-E.' };
                    default:      return { type: 'alphanumeric', regex: /^[A-Za-z0-9\-_]{1,15}$/, message: 'Alphanumeric allowed (max 15 chars).' };
                }
            }

            function updateProductCodeHint() {
                var sym = $('#barcode_symbology').val();
                var r = symRules(sym);
                $('#product_code_error').hide().text('');
                if (r.type === 'numeric') {
                    $('#product_code_hint').text('Numeric only: ' + r.message);
                    $('#product_code').attr('inputmode', 'numeric');
                } else {
                    $('#product_code_hint').text('Max 50 characters. All characters allowed.');
                    $('#product_code').removeAttr('inputmode');
                }
            }

            function validateProductCode() {
                var sym = $('#barcode_symbology').val();
                var code = $('#product_code').val() || '';
                var originalSym = '{{ $product->product_barcode_symbology }}';

                // Clear previous errors
                $('#product_code_error').hide().text('');

                // Basic length validation
                if (code.length > 50) {
                    $('#product_code_error').show().text('Product code must not exceed 50 characters.');
                    $('#product_code').focus();
                    return false;
                }

                // If symbology hasn't changed, skip strict format validation
                if (sym === originalSym) {
                    return true;
                }

                // Strict format validation only when symbology changes
                var rules = symRules(sym);
                if (rules.type === 'numeric' && !rules.regex.test(code)) {
                    $('#product_code_error').show().text(rules.message);
                    $('#product_code').focus();
                    return false;
                }

                return true;
            }

            // init
            updateProductCodeHint();

            $('#barcode_symbology').on('change', function () {
                updateProductCodeHint();
            });

            $('#product-form').on('submit', function (e) {
                if (!validateProductCode()) {
                    e.preventDefault();
                    return false;
                }
            });

            // On blur: check uniqueness for primary + additional codes via AJAX when format is valid (exclude current product)
            var codeCheckTimeout = null;

            function showAdditionalErrorForInput($input, message) {
                var $row = $input.closest('.additional-code-row');
                $row.find('.additional-code-error').remove();
                if (message) {
                    $row.append('<small class="form-text text-danger additional-code-error d-block mt-1">' + message + '</small>');
                }
            }

            function clearAllAdditionalErrors() {
                $('.additional-code-error').remove();
                $('#product_code_error').hide().text('');
            }

            function checkAllCodesAjax() {
                clearTimeout(codeCheckTimeout);
                codeCheckTimeout = setTimeout(function () {
                    if (!validateProductCode()) return;

                    var codes = [];
                    var primary = $('#product_code').val() || '';
                    if (primary) codes.push(primary);

                    $('input[name="additional_codes[]"]').each(function () {
                        var v = $(this).val() || '';
                        if (v && v.trim() !== '') codes.push(v.trim());
                    });

                    if (codes.length === 0) {
                        clearAllAdditionalErrors();
                        return;
                    }

                    $.getJSON('{{ route('products.checkCode') }}', { codes: codes, exclude_id: '{{ $product->id }}' }, function (res) {
                        clearAllAdditionalErrors();
                        if (res.exists && res.conflicts && res.conflicts.length) {
                            res.conflicts.forEach(function (c) {
                                var msg = 'Product code already exists' + (c.name ? (' — ' + c.name) : '') + '.';
                                if (String(c.code) === String(primary)) {
                                    $('#product_code_error').show().text(msg);
                                } else {
                                    // find matching additional input
                                    var $input = null;
                                    $('input[name="additional_codes[]"]').each(function () {
                                        if ($(this).val() && $(this).val().trim() === String(c.code)) {
                                            $input = $(this);
                                            return false;
                                        }
                                    });
                                    if ($input) {
                                        showAdditionalErrorForInput($input, msg);
                                    }
                                }
                            });
                        }
                    }).fail(function () {
                        // ignore
                    });
                }, 150);
            }

            // Primary code blur
            $('#product_code').on('blur', checkAllCodesAjax);

            // Delegate blur for dynamically added additional codes
            $(document).on('blur', 'input[name="additional_codes[]"]', checkAllCodesAjax);
        })();
    </script>
    <script>
        // Additional Codes – add / remove row behaviour
        $(document).ready(function () {
            // Show additional codes row if there are existing extra codes
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