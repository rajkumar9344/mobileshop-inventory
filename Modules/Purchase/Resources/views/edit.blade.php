@extends('layouts.app')

@php $isReadOnly = !empty($readonly); @endphp
@section('title', $isReadOnly ? 'View Purchase' : 'Edit Purchase')

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item"><a href="{{ route('purchases.index') }}">Purchases</a></li>
        <li class="breadcrumb-item active">{{ $isReadOnly ? 'View' : 'Edit' }}</li>
    </ol>
@endsection

@section('content')
    <div class="container-fluid mb-4">
        <div class="row mt-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body">
                                @if(!$isReadOnly)
                                    <form id="purchase-form" action="{{ route('purchases.update', $purchase) }}" method="POST">
                                        @csrf
                                        @method('patch')
                                @endif
                            <div id="validation-errors" style="display: none;"></div>

                            <div class="border p-3 mb-3">
                            <div class="form-row">
                                <div class="col-md-3 pr-1">
                                    <label for="reference" class="mb-1">Purchase Reference No <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="reference" id="reference" readonly maxlength="7" pattern="[A-Za-z0-9]+" value="{{ $purchase->reference }}" placeholder="Auto-generated" @if($isReadOnly) disabled @endif>
                                </div>
                                <div class="col-md-2 pr-1">
                                    <label for="ref_date" class="mb-1">Ref Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" name="ref_date" id="ref_date" required value="{{ $purchase->date ?? $purchase->ref_date ?? now()->format('Y-m-d') }}" @if($isReadOnly) disabled @endif>
                                </div>
                                <div class="col-md-3 pr-1">
                                    <label for="supplier_id" class="mb-1">Supplier Name <span class="text-danger">*</span></label>
                                    <select class="form-control" name="supplier_id" id="supplier_id" required @if($isReadOnly) disabled @endif>
                                        <option value="{{ $purchase->supplier_id }}" data-due-days="{{ optional($purchase->supplier)->due_days ?? 0 }}" selected>{{ $purchase->supplier_name }}</option>
                                    </select>
                                </div>
                                <div class="col-md-2 pr-1">
                                    <label for="area" class="mb-1">Area</label>
                                    <input type="text" class="form-control" name="area" id="area" maxlength="30" value="{{ $purchase->area }}" placeholder="Area" @if($isReadOnly) disabled @endif>
                                </div>
                                <div class="col-md-2 pr-1">
                                    <label for="balance" class="mb-1">Open Balance <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="balance" id="balance" maxlength="15" pattern="^\d+(\.\d{1,2})?$|^\d+(,\d{1,2})?$" placeholder="0.00" value="{{ $purchase->balance }}" readonly>
                                </div>
                                <div class="col-md-2 pr-1">
                                    <label for="bill_balance_display" class="mb-1">Bill Balance</label>
                                    <input type="text" class="form-control" id="bill_balance_display" readonly value="{{ $purchase->supplier_id ? number_format($purchase->bill_balance_before ?? (optional($purchase->supplier)->bill_balance ?? 0), 2, '.', '') : '0.00' }}">
                                </div>
                                <div class="col-md-2 pr-1">
                                    <label for="total_balance_display" class="mb-1">Total Balance</label>
                                    <input type="text" class="form-control" id="total_balance_display" readonly value="{{ $purchase->supplier_id ? number_format(((float)($purchase->balance ?? 0)) + ((float)($purchase->bill_balance_before ?? (optional($purchase->supplier)->bill_balance ?? 0))), 2, '.', '') : '0.00' }}">
                                </div>
                            </div>
                            <div class="form-row mt-2 mb-3">
                                <div class="col-md-2 pr-1">
                                    <label for="invoice_no" class="mb-1">Invoice No <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="invoice_no" id="invoice_no" maxlength="20" value="{{ $purchase->invoice_no }}" required placeholder="Invoice Number" @if($isReadOnly) disabled @endif>
                                </div>
                                <div class="col-md-2 pr-1">
                                    <label for="invoice_date" class="mb-1">Invoice Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" name="invoice_date" id="invoice_date" value="{{ $purchase->invoice_date }}" required @if($isReadOnly) disabled @endif>
                                </div>
                                <div class="col-md-2 pr-1">
                                    <label for="excess_amount" class="mb-1">Excess</label>
                                    <input type="text" class="form-control" name="excess_amount" id="excess_amount" maxlength="15" readonly value="{{ optional($purchase->supplier)->excess_amount ?? '0.00' }}" @if($isReadOnly) disabled @endif>
                                </div>
                            </div>
                            </div>

                            @unless($isReadOnly)
                                @can('create_products')
                                    <div class="d-flex justify-content-end mb-2">
                                        <a href="{{ route('products.create') }}" target="_blank" class="btn btn-outline-primary" title="Add New Product">
                                            <i class="bi bi-plus-lg"></i> Add Product
                                        </a>
                                    </div>
                                @endcan

                                <livewire:search-product :context="'purchase'"/>
                            @endunless

                            {{-- Cart quick-search: visible in both edit and view modes --}}
                            <div class="mb-2">
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                                    </div>
                                    <input id="quick-cart-search" type="text" class="form-control" placeholder="Search cart: product name, code or id...">
                                    <button id="quick-cart-search-btn" class="btn btn-outline-secondary" type="button">Find in Cart</button>
                                </div>
                                <small class="text-muted">Search within the products already added below. Press Enter or click Find in Cart.</small>
                                <div id="quick-cart-search-msg" style="display:none;"></div>
                            </div>

                            <livewire:product-cart :cartInstance="$cartInstance ?? 'purchase'" :data="$purchase" :readonly="$isReadOnly"/>

                            {{--
                            <div class="form-row">
                                <div class="col-lg-3">
                                    <div class="from-group">
                                        <div class="form-group">
                                            <label for="payment_method">Payment Method <span class="text-danger">*</span></label>
                                            <select class="form-control" name="payment_method" id="payment_method" required>
                                                <option {{ $purchase->payment_method == 'Cash' ? 'selected' : '' }} value="Cash">Cash</option>
                                                <option {{ $purchase->payment_method == 'Credit Card' ? 'selected' : '' }} value="Credit Card">Credit Card</option>
                                                <option {{ $purchase->payment_method == 'Bank Transfer' ? 'selected' : '' }} value="Bank Transfer">Bank Transfer</option>
                                                <option {{ $purchase->payment_method == 'Cheque' ? 'selected' : '' }} value="Cheque">Cheque</option>
                                                <option {{ $purchase->payment_method == 'Other' ? 'selected' : '' }} value="Other">Other</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-3">
                                    <div class="form-group">
                                        <label for="paid_amount">Amount Paid <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <input id="paid_amount" type="text" class="form-control" name="paid_amount" required value="{{ $purchase->paid_amount }}">
                                            <div class="input-group-append">
                                                <button id="getTotalAmount" class="btn btn-primary" type="button">
                                                    <i class="bi bi-check-square"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-3">
                                    <div class="form-group">
                                        <label for="discount_amount">Discount Amount</label>
                                        <input id="discount_amount" type="text" class="form-control" name="discount_amount" value="{{ $purchase->discount_amount }}">
                                    </div>
                                </div>
                                <div class="col-lg-3">
                                    <div class="form-group">
                                        <label for="balance">Balance</label>
                                        <input id="balance_amount" type="text" class="form-control" readonly>
                                    </div>
                                </div>
                            </div>
                            --}}

                            <div class="form-group">
                                <label for="note">Note (If Needed)</label>
                                <textarea name="note" id="note" rows="5" class="form-control" @if($isReadOnly) disabled @endif>{{ $purchase->note }}</textarea>
                            </div>

                            <!-- Hidden required fields -->
                            <input type="hidden" name="tax_percentage" id="hidden_tax_percentage" value="0">
                            <input type="hidden" name="discount_percentage" id="hidden_discount_percentage" value="0">
                            <input type="hidden" name="shipping_amount" id="hidden_shipping_amount" value="0">
                            <input type="hidden" name="total_amount" id="hidden_total_amount" value="0">
                            <input type="hidden" name="status" value="Pending">

                            <div class="mt-3 d-flex justify-content-end">
                                 <a href="{{ route('purchases.index') }}" class="btn btn-secondary mr-2">Back</a>
                                @unless($isReadOnly)
                                    <button type="submit" class="btn btn-primary">
                                        Update Purchase <i class="bi bi-check"></i>
                                    </button>
                                @endunless
                            </div>
                        @if(!$isReadOnly)
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('page_scripts')
    <script src="{{ asset('js/jquery-mask-money.js') }}"></script>
    <script src="{{ asset('js/currency-input.js') }}"></script>
    <script>
        // Quick-cart search: find product in the cart by id or name
        (function(){
            var _msgTimer = null;
            function clearHighlights() { $('.product-cart-table tbody tr').removeClass('table-success'); }
            function showMessage(html) {
                var $m = $('#quick-cart-search-msg');
                clearTimeout(_msgTimer);
                $m.stop(true, true).html(html).show();
                _msgTimer = setTimeout(function(){ $m.fadeOut(); }, 3000);
            }

            function doSearch() {
                var q = ($('#quick-cart-search').val() || '').trim();
                if (!q) { showMessage('<div class="alert alert-info">Please enter a product id or name to search the cart.</div>'); return; }

                var found = null;
                var cq = q.toLowerCase();

                // 1. Exact numeric product-id match
                if (/^\d+$/.test(q)) {
                    var byId = $('.product-cart-table tbody tr[data-product-id="' + q + '"]');
                    if (byId.length) found = byId;
                }

                // 2. Product-code column contains (case-insensitive)
                if (!found || !found.length) {
                    var byCode = $('.product-cart-table tbody tr').filter(function() {
                        var codeText = ($(this).find('td.product-code').text() || '').trim().toLowerCase();
                        return codeText.indexOf(cq) !== -1;
                    });
                    if (byCode.length) found = byCode;
                }

                // 3. Product-name column contains (case-insensitive)
                if (!found || !found.length) {
                    $('.product-cart-table tbody tr').each(function() {
                        if (found && found.length) return false;
                        var name = $(this).find('td.product-name').text().trim();
                        if (name.toLowerCase().indexOf(cq) !== -1) { found = $(this); }
                    });
                }

                clearHighlights();

                if (found && found.length) {
                    var $row = found.first();
                    $row.addClass('table-success');
                    $('html, body').animate({ scrollTop: $row.offset().top - 120 }, 300);
                    var productName = $row.find('td.product-name').text().trim();
                    var codeText = $row.find('td.product-code').text().trim();
                                            var qtyInput = $row.find('input[data-quick-qty]').first();
                    var qty = qtyInput.length ? (qtyInput.val() || '0') : 'N/A';
                    showMessage('<div class="alert alert-success"><strong>' + productName + '</strong> (' + codeText + ') is in the cart &mdash; Quantity: <strong>' + qty + '</strong></div>');
                } else {
                    showMessage('<div class="alert alert-warning"><strong>' + q + '</strong> was not found in the current purchase cart.</div>');
                }
            }

            $(document).on('click', '#quick-cart-search-btn', doSearch);
            $(document).on('keydown', '#quick-cart-search', function(e){ if (e.key === 'Enter') { e.preventDefault(); doSearch(); } });
        })();
        $(document).ready(function () {
            // Initialize maskMoney for currency inputs (only if payment fields present)
            if ($('#paid_amount').length) {
                $('#paid_amount').maskMoney({
                    prefix:'{{ settings()->currency->symbol }}',
                    thousands:'{{ settings()->currency->thousand_separator }}',
                    decimal:'{{ settings()->currency->decimal_separator }}',
                    allowZero: true,
                });
                // Mask the initial value
                $('#paid_amount').maskMoney('mask');
            }
            @if(! $isReadOnly)
            // Initialize Select2 for supplier dropdown (only when editable)
            $('#supplier_id').select2({
                placeholder: 'Select a supplier',
                allowClear: true,
                ajax: {
                    url: '{{ route("api.suppliers.search") }}',
                    dataType: 'json',
                    delay: 300,
                    data: function (params) {
                        return {
                            q: params.term || '', // Allow empty search
                            page: params.page
                        };
                    },
                    processResults: function (data, params) {
                        params.page = params.page || 1;
                        return {
                            results: data.results,
                            pagination: {
                                more: (params.page * 30) < data.results.length
                            }
                        };
                    },
                    cache: true
                },
                minimumInputLength: 0, // Allow searching with no input
                escapeMarkup: function (markup) {
                    return markup;
                },
                templateResult: function (supplier) {
                    if (supplier.loading) return supplier.text;
                    return supplier.text;
                },
                templateSelection: function (supplier) {
                    return supplier.text || supplier.supplier_name;
                }
            });

            // Set initial value for Select2
            var initialSupplierId = '{{ $purchase->supplier_id }}';
            var initialSupplierName = '{{ $purchase->supplier_name }}';
            if (initialSupplierId && initialSupplierName) {
                var option = new Option(initialSupplierName, initialSupplierId, true, true);
                $('#supplier_id').append(option);
            }

            // Update excess_amount when option has data attributes
            $('#supplier_id').on('change', function() {
                var opt = $(this).find('option:selected');
                var excess = opt.data('excess');
                if (excess !== undefined) {
                    $('#excess_amount').val(parseFloat(excess).toFixed(2));
                }
                var id = $(this).val();
                if (!id) {
                    $('#area').val('');
                    return;
                }

                $.get('/api/suppliers/' + id).done(function(res) {
                    $('#area').val(res.area || '');
                    $('#bill_balance_display').val(res.bill_balance_formatted !== undefined ? res.bill_balance_formatted : '0.00');
                    $('#total_balance_display').val(res.total_balance_formatted !== undefined ? res.total_balance_formatted : '0.00');
                }).fail(function() {
                    $('#area').val('');
                });
            });

            @else
            // View mode: ensure supplier select is disabled and initial area is present
            $('#supplier_id').prop('disabled', true);
            $('#area').prop('disabled', true);
            @endif

            // Calculate balance only if payment fields present
            if ($('#paid_amount').length) {
                $('#paid_amount').on('keyup change', function() {
                    calculateBalance();
                });
            }

            // Get total amount button click (guarded)
            if ($('#getTotalAmount').length) {
                $('#getTotalAmount').on('click', function() {
                    var totalAmount = parseFloat($('#overall_amount').val()) || 0;
                    if ($('#paid_amount').length) {
                        $('#paid_amount').maskMoney('mask', totalAmount);
                    }
                    calculateBalance();
                });
            }

            // Initialize balance on page load only if relevant fields exist
            if ($('#paid_amount').length || $('#balance_amount').length) {
                calculateBalance();
            }

            $('#purchase-form').submit(function (e) {
                e.preventDefault(); // Prevent default form submission

                // Prevent duplicate submits
                if (this.dataset.submitted === 'true') {
                    return false;
                }

                // Mark submitted and disable submit control
                this.dataset.submitted = 'true';
                const submitBtn = this.querySelector('button[type="submit"]');
                if (submitBtn) {
                    try {
                        submitBtn.disabled = true;
                        submitBtn.dataset.originalHtml = submitBtn.innerHTML;
                        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...';
                    } catch (err) {}
                }

                if ($('#paid_amount').length) {
                    var paid_amount = $('#paid_amount').maskMoney('unmasked')[0];
                    $('#paid_amount').val(paid_amount);
                }
                // Update hidden fields before submission
                updateHiddenFields();

                // Clear previous errors
                $('#validation-errors').hide().html('');
                $('.form-control').removeClass('is-invalid');
                $('.invalid-feedback').remove();

                var $form = $(this);
                // Submit form via AJAX
                $.ajax({
                    url: $form.attr('action'),
                    method: $form.attr('method'),
                    data: $form.serialize(),
                    success: function(response) {
                        // Show success message
                        $('#validation-errors').html('<div class="alert alert-success">Purchase Updated Successfully!</div>').show();

                        // Scroll to top
                        $('html, body').animate({ scrollTop: 0 }, 'fast');

                        // Redirect after a short delay
                        setTimeout(function() {
                            window.location.href = '{{ route("purchases.index") }}';
                        }, 1500);
                    },
                    error: function(xhr) {
                        // Re-enable submit control so user can retry/fix errors
                        try {
                            delete $form[0].dataset.submitted;
                            if (submitBtn) {
                                submitBtn.disabled = false;
                                if (submitBtn.dataset.originalHtml) {
                                    submitBtn.innerHTML = submitBtn.dataset.originalHtml;
                                    delete submitBtn.dataset.originalHtml;
                                }
                            }
                        } catch (err) {}

                        if (xhr.status === 422) {
                            // Validation errors
                            var errors = xhr.responseJSON.errors;
                            var errorHtml = '<div class="alert alert-danger"><ul class="mb-0">';

                            $.each(errors, function(field, messages) {
                                $.each(messages, function(index, message) {
                                    errorHtml += '<li>' + message + '</li>';

                                    // Add error styling to field
                                    var fieldElement = $('[name="' + field + '"]');
                                    fieldElement.addClass('is-invalid');

                                    // Add error message below field
                                    if (fieldElement.next('.invalid-feedback').length === 0) {
                                        fieldElement.after('<div class="invalid-feedback">' + message + '</div>');
                                    }
                                });
                            });

                            errorHtml += '</ul></div>';
                            $('#validation-errors').html(errorHtml).show();

                            // Scroll to top to show errors
                            $('html, body').animate({ scrollTop: 0 }, 'fast');
                        } else {
                            // Other server errors
                            var errorMessage = 'An error occurred while updating the purchase.';
                            if (xhr.responseJSON && xhr.responseJSON.message) {
                                errorMessage = xhr.responseJSON.message;
                            }
                            $('#validation-errors').html('<div class="alert alert-danger">' + errorMessage + '</div>').show();
                            $('html, body').animate({ scrollTop: 0 }, 'fast');
                        }
                    }
                });
            });

            // Update hidden fields on page load
            updateHiddenFields();

            // Expose for Livewire/product-cart to call
            window.updateHiddenFields = updateHiddenFields;

            function calculateBalance() {
                var totalAmount = parseFloat($('#overall_amount').val()) || 0;
                var paidAmount = $('#paid_amount').length && $('#paid_amount').data('maskMoney') ? ($('#paid_amount').maskMoney('unmasked')[0] || 0) : (parseFloat($('#paid_amount').val()) || 0);
                var balance = totalAmount - paidAmount;
                $('#balance_amount').val('{{ settings()->currency->symbol }}' + balance.toFixed(2));
            }

            // Function to update hidden fields with current values
            function updateHiddenFields() {
                // Get current cart total dynamically (support masked / formatted inputs)
                var cartTotal = 0;
                var $overall = $('#overall_amount');
                if ($overall.length && $overall.data('maskMoney')) {
                    cartTotal = $overall.maskMoney('unmasked')[0] || 0;
                } else {
                    var raw = $overall.length ? String($overall.val()).replace(/[^0-9.\-]/g, '') : (document.getElementById('hidden_total_amount').value || '0');
                    cartTotal = parseFloat(raw) || 0;
                }
                document.getElementById('hidden_total_amount').value = cartTotal;

                // Set other required form fields with default values
                document.getElementById('hidden_tax_percentage').value = '0';
                document.getElementById('hidden_discount_percentage').value = '0';
                document.getElementById('hidden_shipping_amount').value = '0';

                // Collect per-item submitted rates and MRPs from the hidden raw currency inputs.
                // This bypasses the Livewire async race: typed MRP/Rate may not have flushed
                // into the session cart before form submit, so we post them directly.
                $('#purchase-form input[name^="submitted_rates"]').remove();
                $('#purchase-form input[name^="submitted_mrps"]').remove();
                $('input[type="hidden"][id$="_raw"]').each(function() {
                    var rawId = this.id; // e.g. "rate_1_raw" or "mrp_1_raw"
                    var mRateMatch = rawId.match(/^rate_(\d+)_raw$/);
                    var mMrpMatch = rawId.match(/^mrp_(\d+)_raw$/);
                    if (mRateMatch) {
                        var productId = mRateMatch[1];
                        var rateVal = this.value;
                        if (rateVal !== '' && parseFloat(rateVal) > 0) {
                            $('<input>').attr({ type: 'hidden', name: 'submitted_rates[' + productId + ']', value: rateVal }).appendTo('#purchase-form');
                        }
                    } else if (mMrpMatch) {
                        var productId = mMrpMatch[1];
                        var mrpVal = this.value;
                        if (mrpVal !== '' && parseFloat(mrpVal) > 0) {
                            $('<input>').attr({ type: 'hidden', name: 'submitted_mrps[' + productId + ']', value: mrpVal }).appendTo('#purchase-form');
                        }
                    }
                });
            }
        });
    </script>
@endpush
