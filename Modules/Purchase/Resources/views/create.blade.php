@extends('layouts.app')

@section('title', 'Create Purchase')

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item"><a href="{{ route('purchases.index') }}">Purchases</a></li>
        <li class="breadcrumb-item active">Add</li>
    </ol>
@endsection

@section('content')
    <div class="container-fluid mb-4">
        <!-- Auto-save status indicator -->
        <div id="auto-save-status" class="position-fixed" style="bottom: 20px; right: 20px; z-index: 1050; display: none;">
            <div class="alert alert-info alert-dismissible mb-0 py-2 px-3" style="min-width: 200px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                <span id="auto-save-message"><i class="bi bi-cloud-arrow-up"></i> Add product to save the Draft</span>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body">
                        <form id="purchase-form" action="{{ route('purchases.store') }}" method="POST">
                            @csrf
                            <div id="validation-errors" style="display: none;"></div>

                            <div class="border p-3 mb-3">
                            {{-- Row 1: Reference No | Ref Date | Supplier Name | Area | Open Balance --}}
                            <div class="form-row">
                                <div class="col-md-2 pr-1">
                                    <label for="reference" class="mb-1">Purchase Reference No <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="reference" id="reference" readonly maxlength="7" pattern="[A-Za-z0-9]+" value="" placeholder="Auto-generated">
                                </div>
                                <div class="col-md-2 pr-1">
                                    <label for="ref_date" class="mb-1">Ref Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" name="ref_date" id="ref_date" required value="{{ now()->format('Y-m-d') }}">
                                </div>
                                <div class="col-md-4 pr-1">
                                    <label for="supplier_id" class="mb-1">Supplier Name <span class="text-danger">*</span></label>
                                    <select class="form-control select2-supplier" name="supplier_id" id="supplier_id" required>
                                        <option value="">-- Select supplier --</option>
                                        @foreach(\Modules\People\Entities\Supplier::where('status', 'active')->orderBy('supplier_name', 'asc')->get() as $supplier)
                                            <option value="{{ $supplier->id }}" data-area="{{ $supplier->area }}" data-balance="{{ $supplier->open_balance ?? 0 }}" data-excess="{{ $supplier->excess_amount ?? 0 }}" data-due-days="{{ $supplier->due_days ?? 0 }}">{{ $supplier->supplier_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2 pr-1">
                                    <label for="area" class="mb-1">Area</label>
                                    <input type="text" class="form-control" name="area" id="area" maxlength="30" readonly placeholder="Area">
                                </div>
                                <div class="col-md-2 pr-1">
                                    <label for="balance" class="mb-1">Open Balance <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="balance" id="balance" maxlength="15" pattern="^\d+(\.\d{1,2})?$|^\d+(,\d{1,2})?$" readonly placeholder="0.00" value="0.00">
                                </div>
                            </div>
                            {{-- Row 2: Invoice No | Invoice Date | Bill Balance | Total Balance | Excess --}}
                            <div class="form-row mt-2 mb-3">
                                <div class="col-md-3 pr-1">
                                    <label for="invoice_no" class="mb-1">Invoice No <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="invoice_no" id="invoice_no" maxlength="20" required placeholder="Invoice Number">
                                </div>
                                <div class="col-md-3 pr-1">
                                    <label for="invoice_date" class="mb-1">Invoice Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" name="invoice_date" id="invoice_date" required value="{{ now()->format('Y-m-d') }}">
                                </div>
                                <div class="col-md-2 pr-1">
                                    <label for="bill_balance_display" class="mb-1">Bill Balance</label>
                                    <input type="text" class="form-control" id="bill_balance_display" readonly placeholder="0.00" value="0.00">
                                </div>
                                <div class="col-md-2 pr-1">
                                    <label for="total_balance_display" class="mb-1">Total Balance</label>
                                    <input type="text" class="form-control" id="total_balance_display" readonly placeholder="0.00" value="0.00">
                                </div>
                                <div class="col-md-2 pr-1">
                                    <label for="excess_amount" class="mb-1">Excess</label>
                                    <input type="text" class="form-control" name="excess_amount" id="excess_amount" maxlength="15" readonly placeholder="0.00" value="0.00">
                                </div>
                            </div>
                            </div>

                            @can('create_products')
                                <div class="d-flex justify-content-end mb-2">
                                    <a href="{{ route('products.create') }}" target="_blank" class="btn btn-outline-primary" title="Add New Product">
                                        <i class="bi bi-plus-lg"></i> Add Product
                                    </a>
                                </div>
                            @endcan

                            <livewire:search-product :context="'purchase'"/>

                            {{-- Cart quick-search: find already-added products by name or id --}}
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

                            <livewire:product-cart :cartInstance="'purchase'"/>

                            {{--
                            <div class="form-row">
                                <div class="col-lg-3">
                                    <div class="from-group">
                                        <div class="form-group">
                                            <label for="payment_method">Payment Method <span class="text-danger">*</span></label>
                                            <select class="form-control" name="payment_method" id="payment_method" required>
                                                <option value="Cash">Cash</option>
                                                <option value="Credit Card">Credit Card</option>
                                                <option value="Bank Transfer">Bank Transfer</option>
                                                <option value="Cheque">Cheque</option>
                                                <option value="Other">Other</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-3">
                                    <div class="form-group">
                                        <label for="paid_amount">Amount Paid <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <input id="paid_amount" type="text" class="form-control" name="paid_amount" required>
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
                                        <input id="discount_amount" type="text" class="form-control" name="discount_amount">
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
                                <textarea name="note" id="note" rows="5" class="form-control"></textarea>
                            </div>

                            <!-- Hidden required fields -->
                            <input type="hidden" name="tax_percentage" id="hidden_tax_percentage" value="0">
                            <input type="hidden" name="discount_percentage" id="hidden_discount_percentage" value="0">
                            <input type="hidden" name="shipping_amount" id="hidden_shipping_amount" value="0">
                            <input type="hidden" name="total_amount" id="hidden_total_amount" value="0">
                            <input type="hidden" name="status" value="Pending">
                            <input type="hidden" name="draft_id" id="draft_id" value="">

                            <div class="row mt-3 mb-3">
                                <div class="col-12 purchase-action-buttons">
                                    <a href="{{ route('purchases.index') }}" class="btn btn-secondary" id="back-btn">
                                        <i class="bi bi-arrow-left"></i> Back
                                    </a>
                                    <button type="button" id="save-draft-btn" class="btn btn-outline-secondary">
                                        <i class="bi bi-save"></i> Save as Draft
                                    </button>
                                    <button type="submit" class="btn btn-primary">
                                        Create Purchase <i class="bi bi-check"></i>
                                    </button>
                                    
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('page_scripts')
    <script src="{{ asset('js/jquery-mask-money.js') }}"></script>
    <script src="{{ asset_v('js/currency-input.js') }}"></script>
    <style>
        .purchase-action-buttons {
            display: flex;
            justify-content: flex-end;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .is-invalid {
            border-color: #dc3545;
        }
        .invalid-feedback {
            display: block;
            color: #dc3545;
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }
    </style>
    <script>
        $(document).ready(function () {
            // Generate reference number on page load
            generateReferenceNumber();

            // Initialize Select2 for supplier dropdown
            $('.select2-supplier').select2({
                placeholder: 'Search and select supplier...',
                allowClear: true,
                width: '100%'
            });

            // Handle supplier selection
                $('#supplier_id').on('change', function() {
                    var selectedOption = $(this).find('option:selected');
                    var area = selectedOption.data('area') || '';
                    var balance = selectedOption.data('balance') || 0;
                    var excess = selectedOption.data('excess') || 0;
                        var dueDays = selectedOption.data('due-days');

                    $('#area').val(area);
                    $('#balance').val(parseFloat(balance).toFixed(2));
                    $('#excess_amount').val(parseFloat(excess).toFixed(2));
                    var sid = $(this).val();
                    if (sid) {
                        $.get('/api/suppliers/' + sid).done(function(res){
                            $('#bill_balance_display').val(res.bill_balance_formatted !== undefined ? res.bill_balance_formatted : '0.00');
                            $('#total_balance_display').val(res.total_balance_formatted !== undefined ? res.total_balance_formatted : '0.00');
                        });
                    } else {
                        $('#bill_balance_display,#total_balance_display').val('0.00');
                    }
                });

            if ($('#paid_amount').length) {
                $('#paid_amount').maskMoney({
                    prefix:'{{ settings()->currency->symbol }}',
                    thousands:'{{ settings()->currency->thousand_separator }}',
                    decimal:'{{ settings()->currency->decimal_separator }}',
                    allowZero: true,
                });
            }

            if ($('#getTotalAmount').length) {
                $('#getTotalAmount').click(function () {
                    var totalAmount = parseFloat($('#overall_amount').val()) || 0;
                    if ($('#paid_amount').length) {
                        $('#paid_amount').maskMoney('mask', totalAmount);
                    }
                    if (typeof calculateBalance === 'function') calculateBalance();
                });
            }

            // Update balance on keyup (real-time updates)
            if ($('#paid_amount').length) {
                $('#paid_amount').on('keyup', function() {
                    if (typeof calculateBalance === 'function') calculateBalance();
                });
            }

            // Update balance when cart total changes
            if ($('#overall_amount').length && ($('#paid_amount').length || $('#balance_amount').length)) {
                $('#overall_amount').on('change input', function() {
                    if (typeof calculateBalance === 'function') calculateBalance();
                });
            }

            // Initialize balance on page load
            if ($('#paid_amount').length || $('#balance_amount').length) {
                if (typeof calculateBalance === 'function') calculateBalance();
            }

            $('#purchase-form').submit(function (e) {
                e.preventDefault(); // Prevent default form submission

                // Prevent duplicate submits
                if (this.dataset.submitted === 'true') {
                    return false;
                }

                // Mark submitted and disable submit controls
                this.dataset.submitted = 'true';
                const submitBtn = this.querySelector('button[type="submit"]');
                const draftBtn = document.getElementById('save-draft-btn');
                if (submitBtn) {
                    try {
                        submitBtn.disabled = true;
                        submitBtn.dataset.originalHtml = submitBtn.innerHTML;
                        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...';
                    } catch (err) {}
                }
                if (draftBtn) draftBtn.disabled = true;

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

                // Submit form via AJAX
                var $form = $(this);
                $.ajax({
                    url: $form.attr('action'),
                    method: $form.attr('method'),
                    data: $form.serialize(),
                    success: function(response) {
                        // Show success message
                        $('#validation-errors').html('<div class="alert alert-success">Purchase Created Successfully!</div>').show();

                        // Scroll to top
                        $('html, body').animate({ scrollTop: 0 }, 'fast');

                        // Redirect after a short delay
                        setTimeout(function() {
                            window.location.href = '{{ route("purchases.index") }}';
                        }, 1500);
                    },
                    error: function(xhr) {
                        // Re-enable submit controls so user can retry/fix errors
                        try {
                            delete $form[0].dataset.submitted;
                            if (submitBtn) {
                                submitBtn.disabled = false;
                                if (submitBtn.dataset.originalHtml) {
                                    submitBtn.innerHTML = submitBtn.dataset.originalHtml;
                                    delete submitBtn.dataset.originalHtml;
                                }
                            }
                            if (draftBtn) draftBtn.disabled = false;
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
                            var errorMessage = 'An error occurred while saving the purchase.';
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

            function generateReferenceNumber() {
                // Generate PU format reference number (PU00051)
                $.ajax({
                    url: '{{ route("purchases.generate-reference") }}',
                    method: 'GET',
                    success: function(response) {
                        $('#reference').val(response.reference);
                    },
                    error: function() {
                        // Fallback to basic format if API fails
                        $('#reference').val('PU00001');
                    }
                });
            }

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

                // Collect current Rate-before-Discount values directly from the DOM.
                // This bypasses the Livewire async race condition: typing a new rate and
                // immediately clicking Submit may not have flushed the value to the
                // Livewire session cart yet. Reading the hidden raw-value inputs here
                // guarantees the controller always receives the latest typed value.
                $('#purchase-form input[name^="submitted_rates"]').remove();
                $('#purchase-form input[name^="submitted_mrps"]').remove();
                $('input[type="hidden"][id$="_raw"]').each(function() {
                    var rawId = this.id; // e.g. "rate_1_raw"
                    // Handle both rate_*_raw and mrp_*_raw hidden raw inputs
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

    <script>
        // Quick-cart search: find product in the cart by id or name
        (function(){
            function clearHighlights() {
                $('.product-cart-table tbody tr').removeClass('table-success');
            }

            var _msgTimer = null;
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
        // Auto-save draft functionality for Purchase module
        (function() {
            // Configuration
            const config = {
                autoSaveUrl: '{{ route("purchases.auto-save-draft") }}',
                debounceDelay: 1000,      // 1 second debounce
                autoSaveInterval: 45000,  // 45 seconds
                idleTimeout: 5000         // 5 seconds
            };

            // State management
            const state = {
                lastSaved: null,
                isSaving: false,
                draftId: null,
                idleTimer: null,
                autoSaveTimer: null,
                debounceTimer: null,
                lastActivity: Date.now(),
                hasUnsavedChanges: false,
                cartObserver: null,
                isFormSubmitting: false
            };

            // DOM elements
            const elements = {
                form: document.getElementById('purchase-form'),
                status: document.getElementById('auto-save-status'),
                statusBottom: document.getElementById('auto-save-status-bottom'),
                draftBtn: document.getElementById('save-draft-btn'),
                draftIdField: document.getElementById('draft_id')
            };

            // Hide the inline bottom status (we use the floating indicator instead)
            if (elements.statusBottom) {
                elements.statusBottom.style.display = 'none';
            }

            // CSRF token
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

            // Update status display
            function updateStatus(message, type = 'info') {
                const icons = { info: 'bi-info-circle', success: 'bi-check-circle', error: 'bi-exclamation-triangle', saving: 'bi-cloud-arrow-up' };

                // Prefer the floating status element (elements.status) and do not update the inline bottom status.
                if (elements.status) {
                    const alertEl = elements.status.querySelector('.alert');
                    const messageEl = elements.status.querySelector('#auto-save-message');
                    if (alertEl && messageEl) {
                        // Update message
                        messageEl.innerHTML = `<i class="bi ${icons[type]}"></i> ${message}`;

                        // Update alert classes
                        alertEl.classList.remove('alert-info', 'alert-success', 'alert-danger');
                        if (type === 'success') alertEl.classList.add('alert-success');
                        else if (type === 'error') alertEl.classList.add('alert-danger');
                        else alertEl.classList.add('alert-info');

                        // Show floating status
                        elements.status.style.display = 'block';

                        // Auto-hide for saved/error states
                        if (type === 'success') {
                            setTimeout(() => { elements.status.style.display = 'none'; }, 2000);
                        } else if (type === 'error') {
                            setTimeout(() => { elements.status.style.display = 'none'; }, 3000);
                        }
                    }
                }
            }

            // Check if form has meaningful data
            function hasFormData() {
                return true; // Let server decide
            }

            // Check if there's something worth saving (only consider the product-cart table)
            function hasContentToSave() {
                // Only count rows inside the product cart table to avoid matching product-search results
                var cartItems = document.querySelectorAll('.product-cart-table tbody tr[data-row-id]');
                return cartItems.length > 0;
            }

            // Synchronous save for beforeunload events
            function saveDraftSync() {
                if (state.isSaving || state.isFormSubmitting) return false;

                if (!hasContentToSave()) return false;

                try {
                    if (window.updateHiddenFields) {
                        window.updateHiddenFields();
                    }

                    const formData = new FormData(elements.form);
                    formData.append('_token', csrfToken);

                    if (state.draftId) {
                        formData.set('draft_id', state.draftId);
                    }

                    const xhr = new XMLHttpRequest();
                    xhr.open('POST', config.autoSaveUrl, false);
                    xhr.setRequestHeader('X-CSRF-TOKEN', csrfToken);
                    xhr.setRequestHeader('Accept', 'application/json');

                    xhr.send(formData);

                    if (xhr.status === 200) {
                        const result = JSON.parse(xhr.responseText);
                        if (result.success && result.draft_id) {
                            state.draftId = result.draft_id;
                            if (elements.draftIdField) {
                                elements.draftIdField.value = state.draftId;
                            }
                            state.lastSaved = Date.now();
                            return true;
                        }
                    }
                } catch (error) {
                    console.error('Sync save error:', error);
                }

                return false;
            }

            // Save draft
            async function saveDraft(isManual = false) {
                if (state.isSaving || state.isFormSubmitting) {
                    return;
                }

                if (!isManual && !hasContentToSave()) {
                    return;
                }

                state.isSaving = true;
                // Only show saving status when there is content to save
                if (hasContentToSave()) {
                    updateStatus('Saving draft...', 'saving');
                } else {
                    state.isSaving = false;
                    return;
                }

                try {
                    if (window.updateHiddenFields) {
                        window.updateHiddenFields();
                    }

                    const formData = new FormData(elements.form);
                    formData.append('_token', csrfToken);

                    if (state.draftId) {
                        formData.set('draft_id', state.draftId);
                    }

                    const response = await fetch(config.autoSaveUrl, {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': csrfToken
                        }
                    });

                    const result = await response.json();

                    if (result.success) {
                        state.lastSaved = Date.now();
                        state.draftId = result.draft_id;

                        if (elements.draftIdField) {
                            elements.draftIdField.value = state.draftId;
                        }

                        if (isManual) {
                            updateStatus('Draft saved! Redirecting...', 'success');
                            setTimeout(() => {
                                window.location.href = '{{ route("purchases.index") }}';
                            }, 1200);
                        } else {
                            updateStatus('Draft saved', 'success');
                            setTimeout(() => {
                                updateStatus('Auto-saving...', 'info');
                            }, 3000);
                        }
                    } else {
                        if (result.message === 'Insufficient data to save draft') {
                            return;
                        } else {
                            throw new Error(result.message || 'Save failed');
                        }
                    }

                } catch (error) {
                    console.error('Auto-save error:', error);
                    updateStatus('Save failed', 'error');
                    setTimeout(() => {
                        updateStatus('Auto-saving...', 'info');
                    }, 3000);
                } finally {
                    state.isSaving = false;
                }
            }

            // Debounced save
            function debouncedSave() {
                clearTimeout(state.debounceTimer);
                state.debounceTimer = setTimeout(() => saveDraft(), config.debounceDelay);
            }

            // Activity handler
            function handleActivity() {
                state.lastActivity = Date.now();
                state.hasUnsavedChanges = true;

                if (!state.autoSaveTimer) {
                    state.autoSaveTimer = setInterval(() => {
                        if (state.hasUnsavedChanges && hasContentToSave()) {
                            saveDraft();
                        }
                    }, config.autoSaveInterval);
                }

                clearTimeout(state.idleTimer);
                state.idleTimer = setTimeout(() => {
                    if (state.hasUnsavedChanges && hasContentToSave()) {
                        saveDraft();
                    }
                }, config.idleTimeout);
            }

            // Setup event listeners
            function setupEventListeners() {
                // Form changes
                elements.form.addEventListener('input', debouncedSave);
                elements.form.addEventListener('change', debouncedSave);

                // Manual save button
                if (elements.draftBtn) {
                    elements.draftBtn.addEventListener('click', () => saveDraft(true));
                }

                // Activity tracking
                ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart'].forEach(event => {
                    document.addEventListener(event, handleActivity, { passive: true });
                });

                // Page visibility
                document.addEventListener('visibilitychange', () => {
                    if (document.visibilityState === 'hidden' && state.hasUnsavedChanges && hasContentToSave()) {
                        saveDraft();
                    } else if (document.visibilityState === 'visible') {
                        handleActivity();
                    }
                });

                // Browser navigation
                window.addEventListener('popstate', () => {
                    if (state.hasUnsavedChanges && hasContentToSave()) {
                        saveDraftSync();
                    }
                });

                // Form submit handler
                elements.form.addEventListener('submit', () => {
                    state.isFormSubmitting = true;
                    clearTimeout(state.debounceTimer);
                    clearInterval(state.autoSaveTimer);
                });

                // Back button handler
                const backBtn = document.getElementById('back-btn');
                if (backBtn) {
                    backBtn.addEventListener('click', (e) => {
                        if (state.hasUnsavedChanges && hasContentToSave()) {
                            e.preventDefault();
                            if (confirm('You have unsaved changes. Do you want to save as draft before leaving?')) {
                                saveDraft().then(() => {
                                    window.location.href = backBtn.href;
                                });
                            } else {
                                window.location.href = backBtn.href;
                            }
                        }
                    });
                }

                // Livewire cart change detection
                if (typeof Livewire !== 'undefined') {
                    Livewire.hook('commit', ({ succeed }) => {
                        succeed(() => {
                            state.hasUnsavedChanges = true;
                            debouncedSave();
                        });
                    });
                }

                // Custom cart events
                ['cart-updated', 'product-added', 'product-removed'].forEach(eventName => {
                    window.addEventListener(eventName, () => {
                        state.hasUnsavedChanges = true;
                        debouncedSave();
                    });
                });

                // MutationObserver for cart table changes
                const cartTable = document.querySelector('.table-responsive .table tbody');
                if (cartTable) {
                    const observer = new MutationObserver((mutations) => {
                        let hasCartChanges = false;
                        mutations.forEach((mutation) => {
                            if (mutation.type === 'childList' && (mutation.addedNodes.length > 0 || mutation.removedNodes.length > 0)) {
                                const hasRowChanges = Array.from(mutation.addedNodes).some(node =>
                                    node.nodeType === Node.ELEMENT_NODE && node.tagName === 'TR'
                                ) || Array.from(mutation.removedNodes).some(node =>
                                    node.nodeType === Node.ELEMENT_NODE && node.tagName === 'TR'
                                );
                                if (hasRowChanges) {
                                    hasCartChanges = true;
                                }
                            }
                        });
                        if (hasCartChanges) {
                            state.hasUnsavedChanges = true;
                            debouncedSave();
                        }
                    });

                    observer.observe(cartTable, {
                        childList: true,
                        subtree: false
                    });

                    state.cartObserver = observer;
                }

                // Before unload
                window.addEventListener('beforeunload', (e) => {
                    if (!state.lastSaved || Date.now() - state.lastSaved > 5000) {
                        const saved = saveDraftSync();
                        if (saved) {
                            console.log('Draft saved before page unload');
                        }
                    }
                });
            }

            // Start auto-save
            function startAutoSave() {
                if (!csrfToken) {
                    console.error('CSRF token not found. Auto-save will not work.');
                    updateStatus('Auto-save disabled', 'error');
                    return;
                }

                updateStatus('Add product to save the Draft', 'info');
                setupEventListeners();

                // Action availability: disable Create + Save Draft when no products
                function updatePurchaseActionAvailability() {
                    var has = hasContentToSave();
                    var $createBtns = $('#purchase-form').find('button[type=submit]');
                    var $draftBtn = $('#save-draft-btn');
                    var submitDisabled = $createBtns.length ? $createBtns.first().prop('disabled') : false;
                    var shouldDisable = !has || submitDisabled;

                    $createBtns.prop('disabled', shouldDisable);
                    if ($draftBtn.length) $draftBtn.prop('disabled', shouldDisable);

                    // No helper text — just keep buttons enabled/disabled based on cart and validation
                }

                window.updatePurchaseActionAvailability = updatePurchaseActionAvailability;
                updatePurchaseActionAvailability();
                // Broad listeners for Livewire and custom cart events
                document.addEventListener('livewire:updated', updatePurchaseActionAvailability);
                document.addEventListener('livewire:update', updatePurchaseActionAvailability);
                document.addEventListener('livewire:load', updatePurchaseActionAvailability);
                ['cart-updated', 'product-added', 'product-removed'].forEach(function(ev) { window.addEventListener(ev, updatePurchaseActionAvailability); });
                if (window.Livewire && Livewire.hook) {
                    try { Livewire.hook('message.processed', updatePurchaseActionAvailability); } catch(e){}
                }
                // Targeted MutationObserver on cart tbody
                var cartTbodyP = document.querySelector('.product-cart-table tbody');
                if (cartTbodyP && window.MutationObserver) {
                    var moP = new MutationObserver(function(){ updatePurchaseActionAvailability(); });
                    moP.observe(cartTbodyP, { childList: true, subtree: false, attributes: false });
                }

                @include('partials.cart_submit_sync', ['formId' => 'purchase-form', 'actionAvailabilityFn' => 'updatePurchaseActionAvailability'])
            }

            // Initialize when DOM is ready
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', startAutoSave);
            } else {
                startAutoSave();
            }

            // Expose for debugging
            window.purchaseAutoSave = {
                saveDraft,
                state,
                config
            };

        })();
    </script>
@endpush
