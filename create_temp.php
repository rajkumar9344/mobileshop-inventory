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
        <form id="product-form" action="{{ route('products.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
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
                                            @foreach(\Modules\Product\Entities\Category::where('status', true)->orderBy('category_name')->get() as $category)
                                                <option value="{{ $category->id }}">{{ $category->category_name }}</option>
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
                                <div class="col-md-4">
                                    <label for="subcategory_id">Subcategory <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <select class="form-control" name="subcategory_id" id="subcategory_id" required>
                                            <option value="" selected disabled>Select Subcategory</option>
                                        </select>
                                        <div class="input-group-append d-flex">
                                            @can('create_product_subcategories')
                                                <button data-toggle="modal" data-target="#subcategoryCreateModal" class="btn btn-outline-primary" type="button">Add</button>
                                            @endcan
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="product_code">Code <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <input type="text" class="form-control" name="product_code" id="product_code" required value="{{ old('product_code') }}" maxlength="50" title="Max 50 characters">
                                            <div class="input-group-append">
                                                <button type="button" id="add-code-btn" class="btn btn-outline-primary" title="Add another code"><i class="bi bi-plus-circle"></i></button>
                                            </div>
                                        </div>
                                        <small id="product_code_hint" class="form-text text-muted">Max 50 characters. If you select EAN/UPC, enter numeric code of correct length.</small>
                                        <small id="product_code_error" class="form-text text-danger" style="display:none;"></small>
                                    </div>
                                </div>
                            </div>

                            {{-- Additional Codes (hidden by default until used) --}}
                            <div class="form-row" id="additional-codes-row" style="display: none;">
                                <div class="col-md-12">
                                    <div id="additional-codes-wrapper">
                                        @foreach(old('additional_codes', []) as $extraCode)
                                            <div class="mb-2 additional-code-row">
                                                <div class="input-group">
                                                    <input type="text" class="form-control" name="additional_codes[]" value="{{ $extraCode }}" maxlength="50" placeholder="Enter code">
                                                    <div class="input-group-append">
                                                        <button type="button" class="btn btn-outline-danger remove-code-btn"><i class="bi bi-trash"></i></button>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>

                            {{-- Row 2: Product Name | Equivalent Product's Code | Unit --}}
                            <div class="form-row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="product_name">Product Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="product_name" required value="{{ old('product_name') }}" maxlength="50" title="Max 50 characters" oninput="this.value = this.value.slice(0,50)">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="alternative_number">Equivalent Product's Code</label>
                                        <input type="text" class="form-control" name="alternative_number" value="{{ old('alternative_number') }}" maxlength="50" title="Max 50 characters">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="product_unit">Unit <i class="bi bi-question-circle-fill text-info" data-toggle="tooltip" data-placement="top" title="This short text will be placed after Product Quantity."></i> <span class="text-danger">*</span></label>
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

                            {{-- Row 3: MRP | Tax % | Tax Type --}}
                            <div class="form-row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="mrp">MRP <span class="text-danger">*</span></label>
                                        <x-currency-input
                                            id="mrp"
                                            name="mrp"
                                            class="form-control"
                                            :display="old('mrp')"
                                            aria-label="MRP"
                                            maxlength="15"
                                            required
                                        />
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="product_order_tax">Tax (%)</label>
                                        <input type="number" class="form-control" name="product_order_tax" value="{{ old('product_order_tax') }}" min="0" max="99" oninput="this.value = this.value.replace(/[^0-9]/g,'').slice(0,2)">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="product_tax_type">Tax type</label>
                                        <select class="form-control" name="product_tax_type" id="product_tax_type">
                                            <option value="" {{ old('product_tax_type', 2) == '' ? 'selected' : '' }}>Select Tax Type</option>
                                            <option value="1" {{ old('product_tax_type', 2) == 1 ? 'selected' : '' }}>Exclusive</option>
                                            <option value="2" {{ old('product_tax_type', 2) == 2 ? 'selected' : '' }}>Inclusive</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            {{-- Row 4: Rack | Bin | Barcode Symbology --}}
                            <div class="form-row">
                                <div class="col-md-4">
                                    <label for="rack_no">Rack No <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <select class="form-control" name="rack_no" id="rack_no" required>
                                            <option value="" selected disabled>Select Rack</option>
                                            @foreach($racks as $rack)
                                                <option value="{{ $rack }}">{{ $rack }}</option>
                                            @endforeach
                                        </select>
                                        <div class="input-group-append d-flex">
                                            @can('create_racks')
                                                <button data-toggle="modal" data-target="#rackCreateModal" class="btn btn-outline-primary" type="button">Add</button>
                                            @endcan
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label for="bin_no">Bin No <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <select class="form-control" name="bin_no" id="bin_no" required>
                                            <option value="" selected disabled>Select Bin</option>
                                            @foreach($bins as $bin)
                                                <option value="{{ $bin }}">{{ $bin }}</option>
                                            @endforeach
                                        </select>
                                        <div class="input-group-append d-flex">
                                            @can('create_bins')
                                                <button data-toggle="modal" data-target="#binCreateModal" class="btn btn-outline-primary" type="button">Add</button>
                                            @endcan
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="barcode_symbology">Barcode Symbology <span class="text-danger">*</span></label>
                                        <select class="form-control" name="product_barcode_symbology" id="barcode_symbology" required>
                                            <option value="" selected disabled>Select Symbology</option>
                                            <option selected value="C128">Code 128</option>
                                            <option value="C39">Code 39</option>
                                            <option value="UPCA">UPC-A</option>
                                            <option value="UPCE">UPC-E</option>
                                            <option value="EAN13">EAN-13</option>
                                            <option value="EAN8">EAN-8</option>
                                        </select>
                                        <small class="form-text text-muted">Use EAN/UPC only for retail barcodes (numeric).</small>
                                    </div>
                                </div>
                            </div>

                            {{-- Row 5: HSN | Supplier | Open Quantity --}}
                            <div class="form-row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="hsn">HSN <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="hsn" id="hsn" required value="{{ old('hsn') }}" maxlength="15" pattern="[0-9]+" title="Numbers only" oninput="this.value = this.value.replace(/[^0-9]/g,'').slice(0,15)">
                                        <div class="form-check mt-1">
                                            <input class="form-check-input" type="checkbox" id="hsn_unknown" name="hsn_unknown" value="1">
                                            <label class="form-check-label" for="hsn_unknown">HSN unknown</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="supplier_id">Supplier Name</label>
                                        <select class="form-control" name="supplier_id" id="supplier_id">
                                            <option value="" selected>Select Supplier</option>
                                            @foreach($suppliers as $id => $name)
                                                <option value="{{ $id }}">{{ $name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="open_quantity">Open Quantity</label>
                                        <input type="number" class="form-control" name="open_quantity" id="open_quantity" min="0" max="9999" oninput="this.value = this.value.replace(/[^0-9]/g,'').slice(0,4)">
                                    </div>
                                </div>
                            </div>

                            {{-- Row 6: Purchased Quantity | Overall Quantity | Alert Quantity --}}
                            <div class="form-row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="purchase_quantity">Purchase Quantity</label>
                                        <input type="number" class="form-control" name="purchase_quantity" id="purchase_quantity" value="0" placeholder="0" min="0" max="9999" readonly oninput="this.value = this.value.replace(/[^0-9]/g,'').slice(0,4)">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="product_quantity">Current quantity(Overall) <span class="text-danger">*</span></label>
                                        <input type="number" class="form-control" name="product_quantity" id="product_quantity" value="0" placeholder="0" min="0" max="9999" readonly oninput="this.value = this.value.replace(/[^0-9]/g,'').slice(0,4)">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="product_stock_alert">Alert Quantity <span class="text-danger">*</span></label>
                                        <input type="number" class="form-control" name="product_stock_alert" id="product_stock_alert" required value="{{ old('product_stock_alert', 0) }}" min="0" max="9999" oninput="this.value = this.value.replace(/[^0-9]/g,'').slice(0,4)">
                                    </div>
                                </div>
                            </div>

                            {{-- Row 7: Reorder | Sell Rate (Net Price) | List Price --}}
                            <div class="form-row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="re_order">Re-order</label>
                                        <input type="number" class="form-control" name="re_order" id="re_order" readonly value="0">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="product_price">Sell Rate (Net Price)</label>
                                        <x-currency-input
                                            id="product_price"
                                            name="product_price"
                                            class="form-control"
                                            :display="old('product_price')"
                                            aria-label="Sell Rate (Net Price)"
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
                                            :display="old('list_price')"
                                            aria-label="List Price"
                                            maxlength="15"
                                        />
                                    </div>
                                </div>
                            </div>

                            {{-- Row 8: Buy Price | Purchase Rate | Status --}}
                            <div class="form-row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="buy_price">Buy Price</label>
                                        <x-currency-input
                                            id="buy_price"
                                            name="buy_price"
                                            class="form-control"
                                            :display="old('buy_price')"
                                            aria-label="Buy Price"
                                            maxlength="15"
                                        />
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="product_cost">Purchase Rate</label>
                                        <x-currency-input
                                            id="product_cost"
                                            name="product_cost"
                                            class="form-control"
                                            :display="old('product_cost')"
                                            aria-label="Purchase Rate"
                                            maxlength="15"
                                        />
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



                            <div class="form-group">
                                <label for="product_note">Compatibility</label>
                                <textarea name="product_note" id="product_note" rows="4" class="form-control"></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-body">
                            <div class="form-group">
                                <label for="image">Product Images <i class="bi bi-question-circle-fill text-info" data-toggle="tooltip" data-placement="top" title="Max Files: 3, Max File Size: 1MB, Image Size: 400x400"></i></label>
                                <div class="dropzone d-flex flex-wrap align-items-center justify-content-center" id="document-dropzone">
                                    <div class="dz-message" data-dz-message>
                                        <i class="bi bi-cloud-arrow-up"></i>
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
                        <button type="submit" class="btn btn-primary">Create Product <i class="bi bi-check"></i></button>
                    </div>
                </div>
        </form>
    </div>

    <!-- Create Category / Subcategory / Rack / Bin Modals -->
    @include('product::includes.category-modal')
    @include('product::subcategories.includes.subcategory-modal')
    @include('product::includes.rack-modal')
    @include('product::includes.bin-modal')
@endsection

@section('third_party_scripts')
    <script src="{{ asset('js/dropzone.js') }}"></script>
@endsection

@push('page_scripts')
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
                "X-CSRF-TOKEN": "{{ csrf_token() }}"
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
                $.ajax({
                    type: "POST",
                    url: "{{ route('dropzone.delete') }}",
                    data: {
                        '_token': "{{ csrf_token() }}",
                        'file_name': `${name}`
                    },
                });
                $('form').find('input[name="document[]"][value="' + name + '"]').remove();
            },
            init: function () {
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

            // Form submit validations only; do not mask/unmask purchase/sell rate
            $('#product-form').submit(function (e) {
                // Prevent submit if subcategory_id is not valid
                var subcategory_id = $('#subcategory_id').val();
                if (!subcategory_id || subcategory_id === "0") {
                    alert('Please select a valid subcategory.');
                    e.preventDefault();
                    return false;
                }

                // Prevent submit if product unit not selected
                var product_unit = $('#product_unit').val();
                if (!product_unit || product_unit === "") {
                    alert('Please select a product unit.');
                    e.preventDefault();
                    return false;
                }
            });

            // Disable subcategory dropdown initially
            $('#subcategory_id').prop('disabled', true);

            $('#category_id').change(function() {
                var category_id = $(this).val();
                // Reset subcategory dropdown
                $('#subcategory_id').empty();
                $('#subcategory_id').append('<option value="">Select Subcategory</option>');
                if (category_id) {
                    $('#subcategory_id').prop('disabled', false);
                    $.ajax({
                        url: '{{ route("get-subcategories") }}',
                        type: 'GET',
                        data: { category_id: category_id },
                        success: function(data) {
                            $('#subcategory_id').empty();
                            $('#subcategory_id').append('<option value="">Select Subcategory</option>');
                            // data may be an object {id: name} — convert to array and sort by name
                            var items = [];
                            $.each(data, function(index, value) { items.push({id: index, name: value}); });
                            items.sort(function(a,b){ return a.name.localeCompare(b.name); });
                            items.forEach(function(item){
                                $('#subcategory_id').append('<option value="' + item.id + '">' + item.name + '</option>');
                            });
                        }
                    });
                } else {
                    $('#subcategory_id').prop('disabled', true);
                }
            });

            // Disable bin dropdown by default; enable only when bins are loaded for a rack
            $('#bin_no').prop('disabled', true);

            // Load bins for selected rack
            $('#rack_no').change(function() {
                var rack_id = $(this).val();
                // Reset bin dropdown
                var previousBin = $('#bin_no').val();
                $('#bin_no').empty();
                $('#bin_no').append('<option value="">Select Bin</option>');
                $('#bin_no').prop('disabled', true);
                if (rack_id) {
                    $.ajax({
                        url: '{{ route("get-bins") }}',
                        type: 'GET',
                        data: { rack_id: rack_id },
                        success: function(data) {
                            $('#bin_no').empty();
                            $('#bin_no').append('<option value="">Select Bin</option>');
                            // data may be array of bin identifiers — sort alphabetically
                            var bins = [];
                            $.each(data, function(index, value) { bins.push(value); });
                            bins.sort(function(a,b){ return String(a).localeCompare(String(b)); });
                            bins.forEach(function(value){
                                $('#bin_no').append('<option value="' + value + '">' + value + '</option>');
                            });
                            // enable and re-select previous if available
                            if ($('#bin_no option').length > 1) {
                                $('#bin_no').prop('disabled', false);
                                if (previousBin && $('#bin_no option[value="' + previousBin + '"]').length) {
                                    $('#bin_no').val(previousBin);
                                }
                            }
                        }
                    });
                }
            });

            // Sync stock with open quantity and calculate re-order
            function syncStockWithOpen() {
                var open_qty = parseInt($('#open_quantity').val()) || 0;
                var purchase_qty = parseInt($('#purchase_quantity').val()) || 0;
                $('#product_quantity').val(open_qty + purchase_qty);
            }

            function calculateReOrder() {
                var quantity = parseInt($('#product_quantity').val()) || 0;
                var alert_qty = parseInt($('#product_stock_alert').val()) || 0;
                var reorder = 0;
                if (alert_qty > quantity) {
                    reorder = alert_qty - quantity;
                }
                $('#re_order').val(reorder);
            }

            // bind events
            $('#open_quantity').on('input change', function () {
                syncStockWithOpen();
                calculateReOrder();
            });

            $('#product_stock_alert').on('input change', calculateReOrder);

            // initialize values
            syncStockWithOpen();
            calculateReOrder(); // initial calculation
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
                var r = symRules(sym);
                if (r.type === 'numeric') {
                    if (!r.regex.test(code)) {
                        $('#product_code_error').show().text(r.message);
                        $('#product_code').focus();
                        return false;
                    }
                } else {
                    if (code.length > 50) {
                        $('#product_code_error').show().text('Product code must not exceed 50 characters.');
                        $('#product_code').focus();
                        return false;
                    }
                }
                $('#product_code_error').hide().text('');
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

            // On blur: check uniqueness for primary + additional codes via AJAX when format is valid
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

                    $.getJSON('{{ route('products.checkCode') }}', { codes: codes }, function (res) {
                        clearAllAdditionalErrors();
                        if (res.exists && res.conflicts && res.conflicts.length) {
                            res.conflicts.forEach(function (c) {
                                var msg = 'Product code already exists' + (c.name ? (' — ' + c.name) : '') + '.';
                                if (String(c.code) === String(primary)) {
                                    $('#product_code_error').show().text(msg);
                                } else {
                                    // find matching additional input (may be multiple same values)
                                    var $input = null;
                                    $('input[name="additional_codes[]"]').each(function () {
                                        if ($(this).val() && $(this).val().trim() === String(c.code)) {
                                            $input = $(this);
                                            return false; // break
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
    
    <script src="{{ asset('js/currency-input.js') }}"></script>
    <script>
        // Additional Codes – add / remove row behaviour
        $(document).ready(function () {
            // Show additional codes row if any pre-existing additional codes (from old input)
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
                // hide wrapper when no additional codes remain
                if ($('#additional-codes-wrapper').children().length === 0) {
                    $('#additional-codes-row').hide();
                }
            });
        });
    </script>
@endpush