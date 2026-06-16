@extends('layouts.app')

@section('title', 'Create Quotation')

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item"><a href="{{ route('quotations.index') }}">Quotations</a></li>
        <li class="breadcrumb-item active">Add</li>
    </ol>
@endsection

@section('content')
    <div class="container-fluid mb-4">
        <!-- Floating auto-save status (matches Sale/Purchase modules) -->
        <div id="auto-save-status" class="position-fixed" style="bottom: 20px; right: 20px; z-index: 1050; display: none;">
            <div class="alert alert-info alert-dismissible mb-0 py-2 px-3" style="min-width: 200px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                <span id="auto-save-message"><i class="bi bi-cloud-arrow-up"></i> Add product to save the Draft</span>
            </div>
        </div>
        <div class="row mt-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body">
                        @include('utils.alerts')


                        <form id="quotation-form" action="{{ route('quotations.store') }}" method="POST">
                            @csrf

                            <div class="form-row">
                                <div class="col-lg-4">
                                    <div class="form-group">
                                        <label for="reference">Reference <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="reference" required readonly value="QT">
                                    </div>
                                </div>

                                <div class="col-lg-4">
                                    <div class="form-group">
                                        <label for="customer_type">Customer Type <span class="text-danger">*</span></label>
                                        <select class="form-control" name="customer_type" id="customer_type" required>
                                            <option value="existing">Existing</option>
                                            <option value="new">New</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="col-lg-4">
                                    <div class="form-group">
                                        <label for="date">Date <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control" name="date" required value="{{ now()->format('Y-m-d') }}">
                                    </div>
                                </div>
                            </div>

                            <div class="form-row mt-3">
                                <div class="col-lg-3">
                                    <div class="form-group">
                                        <label for="customer_id">Customer Name <span class="text-danger">*</span></label>
                                        <div id="existing-customer-block">
                                            <select class="form-control select2-customer" name="customer_id" id="customer_id">
                                                <option value="">-- Select customer --</option>
                                                @foreach(\Modules\People\Entities\Customer::where('is_active', true)->orderBy('customer_name', 'asc')->get() as $customer)
                                                    <option value="{{ $customer->id }}">{{ $customer->customer_name }}</option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div id="new-customer-block" style="display:none; margin-top:6px;">
                                            <input type="text" class="form-control" placeholder="New Customer Name" name="customer_name" id="customer_name">
                                        </div>
                                    </div>
                                </div>

                                <div class="col-lg-3">
                                    <div class="form-group">
                                        <div class="new-customer-field" style="display:none;">
                                            <label for="contact_phone">Phone</label>
                                            <input type="text" class="form-control" name="contact_phone" id="contact_phone" maxlength="15" placeholder="Contact Phone" oninput="validatePhone(this)">
                                            <small id="phone-error" class="text-danger" style="display: none;"></small>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-lg-3">
                                    <div class="form-group">
                                        <div class="new-customer-field" style="display:none;">
                                            <label for="contact_email">Email</label>
                                            <input type="email" class="form-control" name="contact_email" id="contact_email" placeholder="Contact Email" maxlength="50" oninput="this.value = this.value.replace(/[^a-zA-Z0-9@._]/g, '').slice(0,50); validateEmail(this);">
                                            <small id="email-error" class="text-danger" style="display: none;"></small>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-lg-3">
                                    <div class="form-group">
                                        <div class="new-customer-field" style="display:none;">
                                            <label for="contact_address">Area</label>
                                            <input type="text" class="form-control" name="contact_address" id="contact_address" placeholder="Contact Address / Area">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-row mt-2 mb-3">
                                <div class="col-md-2 pr-1">
                                    <label for="vat_id_display" class="mb-1">VAT ID / TRN</label>
                                    <input type="text" class="form-control" id="vat_id_display" readonly placeholder="—">
                                </div>
                            </div>

                            {{-- Product search area styled like Sale module (placed below customer details) --}}
                            <!-- <div class="border p-3 mb-3"> -->
                                <livewire:search-product/>
                            <!-- </div> -->

                            <div class="mb-2">
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                                    </div>
                                    <input id="quick-cart-search" type="text" class="form-control" placeholder="Search cart: product name, code or id...">
                                    <button id="quick-cart-search-btn" class="btn btn-outline-secondary" type="button">Find in Cart</button>
                                </div>
                                <small class="text-muted">Search within products already added. Press Enter or click Find.</small>
                                <div id="quick-cart-search-msg" style="display:none;"></div>
                            </div>

                            <livewire:product-cart :cartInstance="'quotation'"/>

                            {{-- Status removed from UI per requirement; replaced with Reduce Stock checkbox --}}
                            <div class="form-row">
                                <div class="col-lg-4">
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="note">Note (If Needed)</label>
                                <textarea name="note" id="note" rows="5" class="form-control"></textarea>
                            </div>

                            {{-- Reduce stock checkbox placed after note --}}
                            <div class="form-group mt-2">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="reduce_stock" id="reduce_stock" value="1" style="transform: scale(1.3); margin-right: .5rem;">
                                    <label class="form-check-label font-weight-bold" for="reduce_stock">Reduce stock when saved</label>
                                </div>
                            </div>

                            <!-- Hidden required fields -->
                            <input type="hidden" name="tax_percentage" id="hidden_tax_percentage" value="0">
                            <input type="hidden" name="discount_percentage" id="hidden_discount_percentage" value="0">
                            <input type="hidden" name="shipping_amount" id="hidden_shipping_amount" value="0">
                            <input type="hidden" name="total_amount" id="hidden_total_amount" value="0">

                            <!-- Hidden fields for Overall Calculations -->
                            <input type="hidden" name="overall_nos" id="hidden_overall_nos">
                            <input type="hidden" name="overall_quantity" id="hidden_overall_quantity">
                            <input type="hidden" name="overall_gross_amount" id="hidden_overall_gross_amount">
                            <input type="hidden" name="overall_taxable_amount" id="hidden_overall_taxable_amount">
                            <input type="hidden" name="overall_cgst" id="hidden_overall_cgst">
                            <input type="hidden" name="overall_sgst" id="hidden_overall_sgst">
                            <input type="hidden" name="overall_igst" id="hidden_overall_igst">
                            <input type="hidden" name="overall_tax_amount" id="hidden_overall_tax_amount">
                            <input type="hidden" name="overall_tcs_percent" id="hidden_overall_tcs_percent">
                            <input type="hidden" name="overall_amount" id="hidden_overall_amount">
                            <input type="hidden" name="overall_other" id="hidden_overall_other">
                            <input type="hidden" name="overall_adj" id="hidden_overall_adj">
                            <input type="hidden" name="overall_net_rate" id="hidden_overall_net_rate">
                            <input type="hidden" name="draft_id" id="draft_id">

                            <div class="row mt-3 mb-3">
                                <div class="col-12 quotation-action-buttons">
                                    <a href="{{ route('quotations.index') }}" class="btn btn-secondary" id="back-btn">
                                        <i class="bi bi-arrow-left"></i> Back
                                    </a>
                                    <button type="button" id="save-draft-btn" class="btn btn-outline-secondary">
                                        <i class="bi bi-save"></i> Save as Draft
                                    </button>
                                    <button type="submit" class="btn btn-primary">
                                        Create Quotation <i class="bi bi-check"></i>
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
    <style>
        .quotation-action-buttons {
            display: flex;
            justify-content: flex-end;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
    </style>
    <script src="{{ asset_v('js/validation.js') }}"></script>
    <script src="{{ asset_v('js/currency-input.js') }}"></script>
    <script>
        $(document).ready(function () {
            // Initialize Select2 for customer dropdown
            $('.select2-customer').select2({
                placeholder: 'Search and select customer...',
                allowClear: true,
                width: '100%'
            });

            // Quick-cart search: find product in the cart by id, code or name
            (function(){
                function clearHighlights() { $('.product-cart-table tbody tr').removeClass('table-success'); }
                var _msgTimer = null;
                function showMessage(html) {
                    var $m = $('#quick-cart-search-msg');
                    clearTimeout(_msgTimer);
                    $m.stop(true, true).html(html).show();
                    _msgTimer = setTimeout(function(){ $m.fadeOut(); }, 3000);
                }

                function doSearch() {
                    var q = ($('#quick-cart-search').val() || '').trim();
                    if (!q) { showMessage('<div class="alert alert-info">Please enter a product id, code or name to search the cart.</div>'); return; }
                    var found = null;
                    var cq = q.toLowerCase();

                    // 1. Exact numeric product-id match
                    if (/^\d+$/.test(q)) {
                        var byId = $('.product-cart-table tbody tr[data-product-id="' + q + '"]');
                        if (byId.length) found = byId;
                    }

                    // 2. Product-code column contains
                    if (!found || !found.length) {
                        var byCode = $('.product-cart-table tbody tr').filter(function() {
                            return ($(this).find('td.product-code').text() || '').trim().toLowerCase().indexOf(cq) !== -1;
                        });
                        if (byCode.length) found = byCode;
                    }

                    // 3. Product-name column contains
                    if (!found || !found.length) {
                        $('.product-cart-table tbody tr').each(function() {
                            if (found && found.length) return false;
                            if ($(this).find('td.product-name').text().trim().toLowerCase().indexOf(cq) !== -1) { found = $(this); }
                        });
                    }

                    clearHighlights();
                    if (found && found.length) {
                        var $row = found.first();
                        $row.addClass('table-success');
                        $('html, body').animate({ scrollTop: $row.offset().top - 120 }, 300);
                        var productName = $row.find('td.product-name').text().trim();
                        var codeText   = $row.find('td.product-code').text().trim();
                            var qtyInput   = $row.find('input[data-quick-qty]').first();
                        var qty        = qtyInput.length ? (qtyInput.val() || '0') : 'N/A';
                        showMessage('<div class="alert alert-success"><strong>' + productName + '</strong> (' + codeText + ') — Quantity: <strong>' + qty + '</strong></div>');
                    } else {
                        showMessage('<div class="alert alert-warning">"' + q + '" was not found in the current quotation cart.</div>');
                    }
                }

                $(document).on('click', '#quick-cart-search-btn', doSearch);
                $(document).on('keydown', '#quick-cart-search', function(e){ if (e.key === 'Enter') { e.preventDefault(); doSearch(); } });
            })();

            // Intercept form submit to confirm action before saving
            $('#quotation-form').on('submit', function (e) {
                updateHiddenFields();
                var isUpdate = $('input[name="_method"]').val() === 'patch';
                var message = isUpdate ? 'Are you sure you want to update this quotation?' : 'Are you sure you want to save this quotation?';
                if (!confirm(message)) {
                    e.preventDefault();
                    return false;
                }
                return true;
            });

            // Function to toggle customer blocks based on customer type
            function toggleCustomerBlocks() {
                if ($('#customer_type').val() === 'new') {
                    $('#existing-customer-block').hide();
                    $('#new-customer-block').show();
                    $('.new-customer-field').show();
                    $('#customer_id').prop('required', false);
                    $('#customer_name').prop('required', true);
                } else {
                    $('#existing-customer-block').show();
                    $('#new-customer-block').hide();
                    $('.new-customer-field').hide();
                    $('#customer_id').prop('required', true);
                    $('#customer_name').prop('required', false);
                }
            }

            $('#customer_type').on('change', function () { toggleCustomerBlocks(); });
            // initialize on load
            toggleCustomerBlocks();

            // When customer is selected, fetch customer defaults (discount, additional_discount, shipping, terms)
            $('#customer_id').on('change', function () {
                var id = $(this).val();
                if (!id) return;
                var url = '/customers/' + id + '/json';
                $.get(url).done(function (res) {
                    // set hidden discount percentage used by Quotation controller
                    $('#hidden_discount_percentage').val(res.cash_discount || 0);

                    // Show the selected customer's VAT ID / TRN (read-only, like Sale)
                    $('#vat_id_display').val(res.vat_id || '');

                    /* Disabled: do not auto-apply additional discount when selecting a customer.
                    // Apply additional discount to ProductCart if present
                    if (res.additional_discount && res.additional_discount > 0) {
                        setTimeout(function() {
                            var hiddenInput = document.getElementById('product-cart-additional-discount');
                            if (hiddenInput) {
                                hiddenInput.value = res.additional_discount;
                                hiddenInput.dispatchEvent(new Event('input', { bubbles: true }));
                            }
                        }, 100);
                    }
                    */

                    // Optionally populate shipping_amount if you want
                    if (res.shipping_amount !== undefined) {
                        $('#hidden_shipping_amount').val(res.shipping_amount);
                    }
                }).fail(function () {
                    // reset on fail
                    $('#hidden_discount_percentage, #hidden_shipping_amount').val('0');
                    $('#vat_id_display').val('');
                });
            });

            // Update hidden fields on page load
            updateHiddenFields();
        });

        // Function to update hidden fields with current values
        function updateHiddenFields() {
            // Set required form fields with default values
            document.getElementById('hidden_tax_percentage').value = '0';
            document.getElementById('hidden_discount_percentage').value = '0';
            document.getElementById('hidden_shipping_amount').value = '0';
            // Get current cart total dynamically
            const cartTotalElement = document.querySelector('.table-responsive .table .table-striped tr:last-child th:last-child');
            const cartTotal = cartTotalElement ? cartTotalElement.textContent.replace(/[^\d.]/g, '') : '0';
            document.getElementById('hidden_total_amount').value = cartTotal;

            // Update Overall Calculations hidden fields from display values
            const overallNos = document.getElementById('overall_nos')?.value || '0';
            const overallQuantity = document.getElementById('overall_quantity')?.value || '0';
            const overallGrossAmount = document.getElementById('overall_gross_amount')?.value || '0';
            const overallTaxableAmount = document.getElementById('overall_taxable_amount')?.value || '0';
            const overallCgst = document.getElementById('overall_cgst')?.value || '0';
            const overallSgst = document.getElementById('overall_sgst')?.value || '0';
            const overallIgst = document.getElementById('overall_igst')?.value || '0';
            const overallTaxAmount = document.getElementById('overall_tax_amount')?.value || '0';
            const overallTcsPercent = document.getElementById('overall_tcs_percent')?.value || '0';
            const overallAmount = String(document.getElementById('overall_amount')?.value || '').replace(/,/g, '') || '0';
            const overallOther = document.getElementById('overall_other')?.value || '0';
            const overallAdj = document.getElementById('overall_adj')?.value || '0';
            const overallNetRate = document.getElementById('overall_amount')?.value || '0';

            document.getElementById('hidden_overall_nos').value = overallNos;
            document.getElementById('hidden_overall_quantity').value = overallQuantity;
            document.getElementById('hidden_overall_gross_amount').value = overallGrossAmount;
            document.getElementById('hidden_overall_taxable_amount').value = overallTaxableAmount;
            document.getElementById('hidden_overall_cgst').value = overallCgst;
            document.getElementById('hidden_overall_sgst').value = overallSgst;
            document.getElementById('hidden_overall_igst').value = overallIgst;
            document.getElementById('hidden_overall_tax_amount').value = overallTaxAmount;
            document.getElementById('hidden_overall_tcs_percent').value = overallTcsPercent;
            document.getElementById('hidden_overall_amount').value = overallAmount;
            document.getElementById('hidden_overall_other').value = overallOther;
            document.getElementById('hidden_overall_adj').value = overallAdj;
            document.getElementById('hidden_overall_net_rate').value = overallNetRate;
        }

        // Make function globally accessible
        window.updateHiddenFields = updateHiddenFields;
        // Ensure hidden fields are synced just before form submit
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('quotation-form');
            if (form) {
                form.addEventListener('submit', function () {
                    updateHiddenFields();
                });
            }
        });
    </script>

    <script>
        // Auto-save draft functionality for Quotation module
        (function() {
            // Configuration
            const config = {
                autoSaveInterval: 45000, // 45 seconds
                idleTimeout: 5000, // 5 seconds
                debounceDelay: 1000 // 1 second
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
                form: document.getElementById('quotation-form'),
                status: document.getElementById('auto-save-status'),
                draftBtn: document.getElementById('save-draft-btn'),
                draftIdField: document.getElementById('draft_id')
            };

            // CSRF token
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

            // Update status display (use floating alert element)
            function updateStatus(message, type = 'info') {
                if (!elements.status) return;

                const icons = { info: 'bi-info-circle', success: 'bi-check-circle', error: 'bi-exclamation-triangle', saving: 'bi-cloud-arrow-up' };

                const alertEl = elements.status.querySelector('.alert');
                const messageEl = elements.status.querySelector('#auto-save-message');
                if (!alertEl || !messageEl) return;

                messageEl.innerHTML = `<i class="bi ${icons[type]}"></i> ${message}`;

                alertEl.classList.remove('alert-info', 'alert-success', 'alert-danger');
                if (type === 'success') alertEl.classList.add('alert-success');
                else if (type === 'error') alertEl.classList.add('alert-danger');
                else alertEl.classList.add('alert-info');

                elements.status.style.display = 'block';

                if (type === 'success') {
                    setTimeout(() => { elements.status.style.display = 'none'; }, 2000);
                } else if (type === 'error') {
                    setTimeout(() => { elements.status.style.display = 'none'; }, 3000);
                }
            }

            // Check if form has meaningful data
            function hasFormData() {
                // For auto-save, be more permissive - let server decide
                // This allows auto-save to attempt saving when user makes changes
                return true;
            }

            // Check if there's something worth saving (only consider the product-cart table)
            function hasContentToSave() {
                // Only count rows inside the product cart table to avoid matching product-search results
                var cartItems = document.querySelectorAll('.product-cart-table tbody tr[data-product-id]');
                return cartItems.length > 0;
            }

            // Save draft
            async function saveDraft(isManual = false) {
                if (state.isSaving || state.isFormSubmitting) {
                    return; // Don't save if already saving or form is submitting
                }

                // Skip if nothing to save (unless manual save)
                if (!isManual && !hasContentToSave()) {
                    return;
                }

                state.isSaving = true;
                // Only show saving status when there is content to save
                if (hasContentToSave()) {
                    updateStatus('Saving draft...', 'saving');
                } else {
                    // Nothing to save — revert saving flag and exit
                    state.isSaving = false;
                    return;
                }

                try {
                    // Update hidden fields before saving
                    if (window.updateHiddenFields) {
                        window.updateHiddenFields();
                    }

                    const formData = new FormData(elements.form);
                    formData.append('_token', csrfToken);

                    if (state.draftId) {
                        formData.set('draft_id', state.draftId);
                    }

                    const response = await fetch('{{ route("quotations.auto-save-draft") }}', {
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

                        updateStatus(isManual ? 'Draft saved manually' : 'Draft saved', 'success');

                        // Clear success message after 3 seconds
                        setTimeout(() => {
                            updateStatus('Auto-saving...', 'info');
                        }, 3000);
                    } else {
                        // Handle validation errors
                        if (result.message === 'Insufficient data to save draft' || result.message === 'Customer information is required to save draft') {
                            // Don't show error for insufficient data or missing customer, just silently skip
                            return;
                        } else {
                            throw new Error(result.message || 'Save failed');
                        }
                    }

                } catch (error) {
                    console.error('Auto-save error:', error);
                    updateStatus('Failed to save draft', 'error');

                    // Clear error message after 5 seconds
                    setTimeout(() => {
                        updateStatus('Auto-saving...', 'info');
                    }, 5000);
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

                // Start auto-save timer if not already started
                if (!state.autoSaveTimer) {
                    state.autoSaveTimer = setInterval(() => {
                        saveDraft();
                    }, config.autoSaveInterval);
                }

                // Reset idle timer
                clearTimeout(state.idleTimer);
                state.idleTimer = setTimeout(() => {
                    saveDraft();
                }, config.idleTimeout);
            }

            // Synchronous save for beforeunload events
            function saveDraftSync() {
                if (state.isSaving || state.isFormSubmitting) return false;

                // Skip if nothing to save
                if (!hasContentToSave()) return false;

                try {
                    // Update hidden fields before saving
                    if (window.updateHiddenFields) {
                        window.updateHiddenFields();
                    }

                    const formData = new FormData(elements.form);
                    formData.append('_token', csrfToken);

                    if (state.draftId) {
                        formData.set('draft_id', state.draftId);
                    }

                    const xhr = new XMLHttpRequest();
                    xhr.open('POST', '{{ route("quotations.auto-save-draft") }}', false); // Synchronous
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

            // Setup event listeners
            function setupEventListeners() {
                // Form changes
                elements.form.addEventListener('input', debouncedSave);
                elements.form.addEventListener('change', debouncedSave);

                // Livewire cart updates (like Sale module)
                if (typeof Livewire !== 'undefined') {
                    Livewire.hook('commit', ({ succeed }) => {
                        succeed(() => {
                            state.hasUnsavedChanges = true;
                            if (hasContentToSave()) {
                                debouncedSave();
                            }
                        });
                    });
                    Livewire.on('refreshCart', () => {
                        state.hasUnsavedChanges = true;
                        if (hasContentToSave()) {
                            debouncedSave();
                        }
                    });
                }

                // Custom events for cart updates
                ['cart-updated', 'product-added'].forEach(eventName => {
                    window.addEventListener(eventName, () => {
                        state.hasUnsavedChanges = true;
                        if (hasContentToSave()) {
                            debouncedSave();
                        }
                    });
                });

                // Manual save button
                if (elements.draftBtn) {
                    elements.draftBtn.addEventListener('click', () => saveDraft(true));
                }

                // Activity tracking
                ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart'].forEach(event => {
                    document.addEventListener(event, handleActivity, { passive: true });
                });

                // Page visibility (improved version like Sale module)
                document.addEventListener('visibilitychange', () => {
                    if (document.visibilityState === 'hidden' && state.hasUnsavedChanges && hasContentToSave()) {
                        saveDraft();
                    } else if (document.visibilityState === 'visible') {
                        handleActivity();
                    }
                });

                // Browser navigation (back/forward buttons)
                window.addEventListener('popstate', () => {
                    if (state.hasUnsavedChanges && hasContentToSave()) {
                        saveDraftSync();
                    }
                });

                // Form submit handler to prevent auto-save interference
                elements.form.addEventListener('submit', () => {
                    state.isFormSubmitting = true;
                    // Clear any pending auto-save timers
                    clearTimeout(state.debounceTimer);
                    clearInterval(state.autoSaveTimer);
                });

                // Back button handler (like Sale module)
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

                // Livewire cart change detection (similar to Sale module)
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

                // MutationObserver for cart table changes (DOM-based detection)
                const cartTable = document.querySelector('.table-responsive .table tbody');
                if (cartTable) {
                    const observer = new MutationObserver((mutations) => {
                        let hasCartChanges = false;
                        mutations.forEach((mutation) => {
                            if (mutation.type === 'childList' && (mutation.addedNodes.length > 0 || mutation.removedNodes.length > 0)) {
                                // Check if added/removed nodes are table rows (products)
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
                        childList: true,  // Watch for added/removed child elements
                        subtree: false    // Don't watch deeply nested changes
                    });

                    // Store observer reference for cleanup if needed
                    state.cartObserver = observer;
                }

                // Before unload - use synchronous save
                window.addEventListener('beforeunload', (e) => {
                    // Try to save synchronously if we have unsaved changes
                    if (!state.lastSaved || Date.now() - state.lastSaved > 5000) {
                        const saved = saveDraftSync();
                        if (saved) {
                            // Give user feedback that draft was saved
                            console.log('Draft saved before page unload');
                        }
                    }
                });
            }

            // Start auto-save
            function startAutoSave() {
                // Check CSRF token
                if (!csrfToken) {
                    console.error('CSRF token not found. Auto-save will not work.');
                    updateStatus('Auto-save disabled', 'error');
                    return;
                }

                // Initial status
                updateStatus('Add product to save the Draft', 'info');

                // Setup listeners
                setupEventListeners();

                // Action availability: disable Create + Save Draft when no products
                function updateQuotationActionAvailability() {
                    var has = hasContentToSave();
                    var $createBtns = $('#quotation-form').find('button[type=submit]');
                    var $draftBtn = $('#save-draft-btn');
                    var submitDisabled = $createBtns.length ? $createBtns.first().prop('disabled') : false;
                    var shouldDisable = !has || submitDisabled;

                    $createBtns.prop('disabled', shouldDisable);
                    if ($draftBtn.length) $draftBtn.prop('disabled', shouldDisable);

                    // No helper text — just keep buttons enabled/disabled based on cart and validation
                }

                window.updateQuotationActionAvailability = updateQuotationActionAvailability;
                updateQuotationActionAvailability();
                // Broadly listen for Livewire/DOM updates and cart events
                document.addEventListener('livewire:updated', updateQuotationActionAvailability);
                document.addEventListener('livewire:update', updateQuotationActionAvailability);
                document.addEventListener('livewire:load', updateQuotationActionAvailability);
                ['cart-updated', 'product-added', 'product-removed'].forEach(function(ev) { window.addEventListener(ev, updateQuotationActionAvailability); });
                // Livewire message processed hook (if available)
                if (window.Livewire && Livewire.hook) {
                    try { Livewire.hook('message.processed', updateQuotationActionAvailability); } catch(e){}
                }
                // Observe the cart tbody for row changes (more targeted than .table-responsive)
                var cartTbody = document.querySelector('.product-cart-table tbody');
                if (cartTbody && window.MutationObserver) {
                    var moQ = new MutationObserver(function(){ updateQuotationActionAvailability(); });
                    moQ.observe(cartTbody, { childList: true, subtree: false, attributes: false });
                }

                @include('partials.cart_submit_sync', ['formId' => 'quotation-form', 'actionAvailabilityFn' => 'updateQuotationActionAvailability'])

                // Don't start auto-save timer immediately - wait for user activity
                // The periodic save will be triggered by user actions
            }

            // Initialize when DOM is ready
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', startAutoSave);
            } else {
                startAutoSave();
            }

            // Expose for debugging
            window.quotationAutoSave = {
                saveDraft,
                state,
                config
            };

        })();
    </script>
@endpush
