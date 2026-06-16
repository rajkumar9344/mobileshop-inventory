                          
@extends('layouts.app')

@section('title', 'Create Sale')

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item"><a href="{{ route('sales.index') }}">Sales</a></li>
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
                        @include('utils.alerts')
                        <div id="credit-limit-warning" class="alert alert-danger d-none">
                            <strong>Warning:</strong> Credit limit exceeded for this customer. Please settle outstanding dues before proceeding.
                        </div>
                        <form id="sale-form" action="{{ route('sales.store') }}" method="POST">
                            @csrf
                            <input type="hidden" wire:model.live="customer_discount_percent" id="customer_discount_percent">

                            <div class="border p-3 mb-3">
                            <div class="form-row">
                                <div class="col-md-2 pr-1">
                                    <label for="reference" class="mb-1">Bill Reference No <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="reference" id="reference" readonly maxlength="15" pattern="[A-Za-z0-9\-\/]+" value="" placeholder="Auto-generated">
                                </div>
                                <div class="col-md-2 pr-1">
                                    <label for="bill_date" class="mb-1">Bill Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" name="date" id="bill_date" required value="{{ \Carbon\Carbon::now()->format('Y-m-d') }}">
                                </div>
                                <div class="col-md-2 pr-1">
                                    <label for="phone" class="mb-1">Phone No</label>
                                    <input type="tel" class="form-control" name="phone" id="phone" maxlength="15" pattern="\+?[0-9]{7,15}" title="Only numbers and + (UAE contact number, max 15)" oninput="validatePhone(this)" placeholder="+971501234567">
                                    <small id="phone-error" class="text-danger" style="display: none;"></small>
                                </div>
                                <div class="col-md-3 pr-1">
                                    <label for="customer_id" class="mb-1">Customer Name <span class="text-danger">*</span></label>
                                    <select class="form-control select2-customer" name="customer_id" id="customer_id" required>
                                        <option value="">-- Select customer --</option>
                                        @foreach(\Modules\People\Entities\Customer::where('is_active', true)->orderBy('customer_name', 'asc')->get() as $customer)
                                            <option value="{{ $customer->id }}">{{ $customer->customer_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2 pr-1">
                                    <label for="area" class="mb-1">Area</label>
                                    <input type="text" class="form-control" name="area" id="area" maxlength="30" placeholder="Area">
                                </div>
                            </div>
                            <div class="form-row mt-2 mb-3">
                                {{-- Bill Type removed from UI; kept as hidden 'Cash' so payment logic stays intact --}}
                                <input type="hidden" name="bill_type" id="bill_type" value="{{ \Modules\Sale\Entities\Sale::BILL_CASH }}">

                                <div class="col-md-2 pr-1">
                                    <label for="opening_balance" class="mb-1">Open Balance <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="opening_balance" id="opening_balance" maxlength="15" pattern="^-?\d+(?:\.\d{1,2})?$|^-?\d+(?:,\d{1,2})?$" placeholder="0.00" required readonly oninput="this.value = this.value.replace(/[^0-9.\-]/g,'').replace(/(?!^)-/g,'').slice(0,15)">
                                </div>
                                <div class="col-md-2 pr-1">
                                    <label for="bill_balance_display" class="mb-1">Bill Balance</label>
                                    <input type="text" class="form-control" id="bill_balance_display" readonly placeholder="0.00">
                                </div>
                                <div class="col-md-2 pr-1">
                                    <label for="total_balance_display" class="mb-1">Total Balance</label>
                                    <input type="text" class="form-control" id="total_balance_display" readonly placeholder="0.00">
                                </div>
                                <div class="col-md-2 pr-1">
                                    <label for="excess_amount_display" class="mb-1">Excess Amount</label>
                                    <input type="text" class="form-control" id="excess_amount_display" readonly value="0.00">
                                    <input type="hidden" name="excess_amount" id="excess_amount" value="0.00">
                                </div>
                                <div class="col-md-2 pr-1">
                                    <label for="vat_id_display" class="mb-1">VAT ID / TRN</label>
                                    <input type="text" class="form-control" id="vat_id_display" readonly placeholder="—">
                                </div>
                            </div>
                            </div>
                            <livewire:search-product/>

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

                            <livewire:product-cart :cartInstance="'sale'"/>

                            <div class="form-row">
                                {{-- <div class="col-lg-4">
                                    <div class="form-group">
                                        <label for="status">Status <span class="text-danger">*</span></label>
                                        <select class="form-control" name="status" id="status" required>
                                            <option value="Pending">Pending</option>
                                            <option value="Shipped">Shipped</option>
                                            <option value="Completed">Completed</option>
                                        </select>
                                    </div>
                                </div> --}}
                                <div class="col-lg-3">
                                    <div class="from-group">
                                        <div class="form-group">
                                            <label for="payment_method">Payment Method</label>
                                            <select class="form-control" name="payment_method" id="payment_method">
                                                <option value="">-- Select payment method --</option>
                                                <option value="Cash">Cash</option>
                                                <option value="Cheque">Cheque</option>
                                                <option value="Cards">Cards</option>
                                                <option value="Bank Transfer">Bank Transfer</option>
                                                <option value="UPI Payment">UPI Payment</option>
                                            </select>
                                            <div id="payment_method_error" class="invalid-feedback d-none">Please select the payment method.</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-3">
                                    <div class="form-group">
                                        <label for="paid_amount">Amount Received <span class="text-danger">*</span></label>
                                        <x-currency-input
                                            id="paid_amount"
                                            name="paid_amount_display"
                                            hiddenName="paid_amount"
                                            hiddenId="paid_amount_hidden"
                                            value="0"
                                            required
                                            symbol="{{ settings()->currency->symbol }}"
                                            position="{{ settings()->default_currency_position }}"
                                        />
                                        <div id="paid_amount_error" class="text-danger small" style="display:none;">Amount Received cannot be more than Net Rate.</div>
                                    </div>
                                </div>
                                <div class="col-lg-3">
                                    <div class="form-group">
                                        <label for="balance">Balance</label>
                                        <x-currency-input
                                            id="balance"
                                            name="balance_display"
                                            hiddenName="balance"
                                            hiddenId="balance_hidden"
                                            readonly
                                            symbol="{{ settings()->currency->symbol }}"
                                            position="{{ settings()->default_currency_position }}"
                                        />
                                    </div>
                                </div>
                                <!-- Settled checkbox removed: settled logic handled in receipts module -->
                            </div>

                            <input type="hidden" name="total_amount" id="hidden_total_amount" value="0">
                            <input type="hidden" name="status" value="Pending">

                            <div class="form-group">
                                <label for="note">Note (If Needed)</label>
                                <textarea name="note" id="note" rows="5" class="form-control"></textarea>
                            </div>

                            <!-- Hidden fields for Overall Calculations (backup - actual values come from Livewire component) -->
                            <!-- Removed duplicate name attributes to avoid conflicts with Livewire component inputs -->
                            <input type="hidden" id="hidden_overall_nos" name="overall_nos">
                            <input type="hidden" id="hidden_overall_quantity" name="overall_quantity">
                            <input type="hidden" id="hidden_overall_gross_amount" name="overall_gross_amount">
                            <input type="hidden" id="hidden_overall_taxable_amount" name="overall_taxable_amount">
                            <input type="hidden" id="hidden_overall_tax_amount" name="overall_tax_amount">
                            <input type="hidden" id="hidden_overall_amount" name="overall_amount">
                            <input type="hidden" name="is_draft" id="is_draft" value="0">
                            <input type="hidden" name="draft_id" id="draft_id" value="">

                            <div class="row mt-3 mb-3">
                                <div class="col-12 d-flex justify-content-end">
                                    <a href="{{ route('sales.index') }}" class="btn btn-secondary mr-2" id="back-btn">Back</a>
                                    <button type="button" id="save-draft-btn" class="btn btn-warning mr-2" onclick="handleDraftSave()">
                                        Save as Draft <i class="bi bi-save"></i>
                                    </button>
                                    <button type="submit" class="btn btn-primary" onclick="prepareCompleteSubmission()">
                                        Create Sale <i class="bi bi-check"></i>
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
    <!-- <script src="{{ asset('js/jquery-mask-money.js') }}"></script> -->
    <script src="{{ asset_v('js/validation.js') }}"></script>
    <script src="{{ asset_v('js/currency-input.js') }}"></script>
    <script>
        $(document).ready(function () {
            // Generate reference number on page load
            generateReferenceNumber();
            $('#bill_date').on('change', function () {
                generateReferenceNumber();
            });
            
            // Initialize Select2 for customer dropdown
            $('.select2-customer').select2({
                placeholder: 'Search and select customer...',
                allowClear: true,
                width: '100%'
            });

            // Initialize currency fields if needed
            if (window.currencyInputInit) {
                window.currencyInputInit();
            }

            // Update balance while typing; check credit limit only after leaving the field
            $('#paid_amount').on('keyup input', function() {
                updateBalance();
                validateAmounts();
            });
            $('#paid_amount').on('blur', function() {
                scheduleCreditLimitCheck();
            });

            // --- Shared validation helpers ---
            function getNetRate() {
                if (typeof updateHiddenFields === 'function') updateHiddenFields();
                var overallEl = document.getElementById('overall_amount');
                if (overallEl && overallEl.dataset && overallEl.dataset.raw) {
                    return parseFloat(String(overallEl.dataset.raw).replace(/[^0-9.\-]/g, '')) || 0;
                }
                var visible = $('#overall_amount').val() || $('#overall_amount').text() || '0';
                return parseFloat(String(visible).replace(/[^0-9.\-]/g, '')) || 0;
            }

            function validateAmounts() {
                var paidVal = parseFloat($('#paid_amount_hidden').val()) || 0;
                var netRate = getNetRate();
                var $submitBtns = $('#sale-form').find('button[type=submit]');
                var $draftBtn = $('#save-draft-btn');
                var hasIssue = false;

                // Validate paid amount
                if (paidVal > netRate + 0.01) {
                    $('#paid_amount').addClass('is-invalid');
                    $('#paid_amount_error').text('Amount Received cannot be more than Net Rate.').show();
                    hasIssue = true;
                } else {
                    $('#paid_amount').removeClass('is-invalid');
                    $('#paid_amount_error').hide();
                }

                $submitBtns.prop('disabled', hasIssue);
                if ($draftBtn.length) $draftBtn.prop('disabled', hasIssue);
                return !hasIssue;
            }

            // Real-time validation on keyup
            $('#paid_amount').on('keyup input blur', validateAmounts);

            $('#paid_amount').on('keyup input blur', function () {
                var paidVal = parseFloat($('#paid_amount_hidden').val()) || 0;
                var pm = $('#payment_method').val();
                if (paidVal > 0 && (!pm || pm.trim() === '')) {
                    $('#payment_method').addClass('is-invalid');
                    $('#payment_method_error').removeClass('d-none').addClass('d-block');
                } else {
                    $('#payment_method').removeClass('is-invalid');
                    $('#payment_method_error').addClass('d-none').removeClass('d-block');
                }
                validateAmounts();
            });

            // Debounced credit-limit check helper
            var _creditLimitTimer = null;
            function scheduleCreditLimitCheck() {
                clearTimeout(_creditLimitTimer);
                _creditLimitTimer = setTimeout(checkCreditLimit, 400);
            }

            // Credit limit check on customer change
            $('#customer_id').on('change', function() {
                scheduleCreditLimitCheck();
            });

            // Credit limit check on paid amount change
            $('#paid_amount').on('keyup input blur', function() {
                scheduleCreditLimitCheck();
            });

            // Function to check credit limit via AJAX
            function checkCreditLimit() {
                var customerId = $('#customer_id').val();
                if (!customerId) {
                    $('#credit-limit-warning').addClass('d-none');
                    return;
                }
                
                // Update hidden fields first to get current cart total
                if (typeof updateHiddenFields === 'function') {
                    updateHiddenFields();
                }
                
                // Get total from cart display or hidden field
                var totalAmount = parseFloat($('#hidden_total_amount').val()) || 0;
                if (totalAmount === 0) {
                    var netRateVal = $('#overall_amount').val() || '';
                    var amountVal = $('#overall_amount').val() || '';
                    totalAmount = parseFloat(netRateVal.replace(/,/g, '')) || parseFloat(amountVal.replace(/,/g, '')) || 0;
                }
                var paidAmount = parseFloat($('#paid_amount_hidden').val()) || 0;
                
                $.ajax({
                    url: '{{ route("sales.check-credit-limit") }}',
                    method: 'GET',
                    data: {
                        customer_id: customerId,
                        total_amount: totalAmount,
                        paid_amount: paidAmount
                    },
                    success: function(response) {
                        if (response.credit_limit_reached) {
                            $('#credit-limit-warning').removeClass('d-none');
                        } else {
                            $('#credit-limit-warning').addClass('d-none');
                        }
                    },
                    error: function() {
                        $('#credit-limit-warning').addClass('d-none');
                    }
                });
            }

            // Helper: async credit-limit check (returns Promise resolving to server response)
            function isCreditLimitCheck(customerId, totalAmount, paidAmount) {
                return $.ajax({
                    url: '{{ route("sales.check-credit-limit") }}',
                    method: 'GET',
                    data: {
                        customer_id: customerId,
                        total_amount: totalAmount,
                        paid_amount: paidAmount
                    }
                }).then(function(response) {
                    return response || { credit_limit_reached: false, credit_limit_blocked: false };
                }).catch(function() {
                    return { credit_limit_reached: false, credit_limit_blocked: false };
                });
            }

            // Quick-cart search: find product in the cart by id, code or name
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
                    if (!q) { showMessage('<div class="alert alert-info">Please enter product id, code or name to search the cart.</div>'); return; }
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
                            var codeText = ($(this).find('td.product-code').text() || '').trim().toLowerCase();
                            return codeText.indexOf(cq) !== -1;
                        });
                        if (byCode.length) found = byCode;
                    }

                    // 3. Product-name column contains
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
                        showMessage('<div class="alert alert-success"><strong>' + productName + '</strong> (' + codeText + ') — Quantity: <strong>' + qty + '</strong></div>');
                    } else {
                        showMessage('<div class="alert alert-warning">"' + q + '" was not found in the current sale cart.</div>');
                    }
                }

                $(document).on('click', '#quick-cart-search-btn', doSearch);
                $(document).on('keydown', '#quick-cart-search', function(e){ if (e.key === 'Enter') { e.preventDefault(); doSearch(); } });
            })();

            $('#sale-form').submit(function (e) {
                e.preventDefault();
                var $form = $(this);
                var $submitButtons = $form.find('button[type=submit], input[type=submit]');
                $submitButtons.prop('disabled', true);

                // Values are in hidden inputs automatically updated by x-currency-input
                var paidNumeric = parseFloat($('#paid_amount_hidden').val()) || 0;

                // Ensure hidden fields are updated before submission
                updateHiddenFields();

                // Validate paid amount and payment method
                var hasError = false;
                var netRate = getNetRate();
                if (paidNumeric > netRate + 0.01) {
                    $('#paid_amount').addClass('is-invalid');
                    $('#paid_amount_error').text('Amount Received cannot be more than Net Rate.').show();
                    hasError = true;
                } else {
                    $('#paid_amount').removeClass('is-invalid');
                    $('#paid_amount_error').hide();
                }

                var pm = $('#payment_method').val();
                if (paidNumeric > 0 && (!pm || pm.trim() === '')) {
                    $('#payment_method').addClass('is-invalid');
                    $('#payment_method_error').removeClass('d-none').addClass('d-block');
                    hasError = true;
                } else {
                    $('#payment_method').removeClass('is-invalid');
                    $('#payment_method_error').addClass('d-none').removeClass('d-block');
                }

                if (hasError) {
                    if ($('#paid_amount').hasClass('is-invalid')) {
                        $('#paid_amount').focus();
                    } else {
                        $('#payment_method').focus();
                    }
                    $submitButtons.prop('disabled', false);
                    return false;
                }

                // Skip credit limit check for draft submissions
                var isDraft = $('#is_draft').val() === '1';
                if (isDraft) {
                    $submitButtons.prop('disabled', false);
                    // submit using native submit to avoid re-entering this handler
                    $form[0].submit();
                    return;
                }

                // Async credit limit check before submission
                var customerId = $('#customer_id').val();
                if (customerId) {
                    // Get total from hidden field or livewire fields
                    var totalAmount = parseFloat($('#hidden_total_amount').val()) || 0;
                    if (totalAmount === 0) {
                        totalAmount = parseFloat($('#overall_amount').val()) || parseFloat($('#overall_amount').val()) || 0;
                    }

                    isCreditLimitCheck(customerId, totalAmount, paidNumeric).then(function(resp) {
                        if (resp.credit_limit_blocked) {
                            $('#credit-limit-warning').removeClass('d-none');
                            alert('Credit Limit reached for this Customer. Please settle outstanding dues before proceeding.');
                            $submitButtons.prop('disabled', false);
                            return;
                        }
                        if (resp.credit_limit_reached) {
                            $('#credit-limit-warning').removeClass('d-none');
                            // populate modal
                            $('#creditLimitModalCustomer').text($('#customer_id option:selected').text() || 'Customer');
                            $('#creditLimitModalPotential').text((resp.potential_balance || 0).toFixed(2));
                            $('#creditLimitModalLimit').text((resp.credit_limit || 0).toFixed(2));
                            $('#creditLimitModalGrace').text((resp.grace || 1000).toFixed(2));
                            $('#creditLimitModalMessage').text('Warning: Customer will exceed credit limit. You can continue, save as draft, or cancel.');
                            // show modal
                            $('#creditLimitModal').modal('show');

                            // wire modal buttons (unbind first to avoid duplicates)
                            $('#creditLimitContinue').off('click').on('click', function(){
                                $('#creditLimitModal').modal('hide');
                                $form[0].submit();
                            });
                            $('#creditLimitSaveDraft').off('click').on('click', function(){
                                // prepare draft and submit
                                prepareDraftSubmission();
                                $('#creditLimitModal').modal('hide');
                                $form[0].submit();
                            });
                            $('#creditLimitCancel').off('click').on('click', function(){
                                $('#creditLimitModal').modal('hide');
                                $submitButtons.prop('disabled', false);
                            });
                            return;
                        }
                        // proceed with native submit
                        $form[0].submit();
                    }).catch(function() {
                        $submitButtons.prop('disabled', false);
                        alert('Unable to verify credit limit. Please try again.');
                    });
                } else {
                    // No customer selected, just submit
                    $form[0].submit();
                }
            });

            // Hide validation when user selects a payment method
            $('#payment_method').on('change', function () {
                if ($(this).val()) {
                    $(this).removeClass('is-invalid');
                    $('#payment_method_error').addClass('d-none').removeClass('d-block');
                }
            });

            // Bill type change: adjust paid_amount requirement (settled logic removed)
            $('#bill_type').on('change', function () {
                var val = $(this).val();
                if (val === 'Cash') {
                    // Paid amount required for cash
                    $('#paid_amount').prop('required', true);
                    $('#paid_amount').prop('readonly', false);
                } else {
                    // Credit
                    // Paid amount optional for credit
                    $('#paid_amount').prop('required', false);
                    $('#paid_amount').prop('readonly', false);
                }
                updateBalance();
            });

            // Initialize bill_type behaviour on page load
            if ($('#bill_type').length) {
                $('#bill_type').trigger('change');
            }

            // Settled checkbox removed; receipts module manages unsettled receipts

            // Update hidden fields on page load
            updateHiddenFields();
        });

        // Function to update hidden fields with current values
        function updateHiddenFields() {
            // Get current cart total dynamically. Prefer dataset.raw from the
            // visible overall_amount (used by the central currency widget),
            // then fall back to hidden_overall_amount, then table text.
            var cartTotal = 0;
            var overallEl = document.getElementById('overall_amount');
            if (overallEl) {
                var rawFromDataset = overallEl.dataset && overallEl.dataset.raw ? overallEl.dataset.raw : null;
                if (rawFromDataset) {
                    cartTotal = parseFloat(String(rawFromDataset).replace(/[^0-9.\-]/g, '')) || 0;
                } else {
                    var val = overallEl.value || '';
                    cartTotal = parseFloat(String(val).replace(/[^0-9.\-]/g, '')) || 0;
                }
            } else {
                var cartTotalElement = document.querySelector('.table-responsive .table .table-striped tr:last-child th:last-child');
                var text = cartTotalElement ? cartTotalElement.textContent : '';
                cartTotal = parseFloat(String(text).replace(/[^0-9.\-]/g, '')) || 0;
            }
            document.getElementById('hidden_total_amount').value = cartTotal;

            // Update Overall Calculations hidden fields from display values
            const overallNos = document.getElementById('overall_nos')?.value || '0';
            const overallQuantity = document.getElementById('overall_quantity')?.value || '0';
            const overallGrossAmount = document.getElementById('overall_gross_amount')?.value || '0';
            const overallTaxableAmount = document.getElementById('overall_taxable_amount')?.value || '0';
            const overallTaxAmount = document.getElementById('overall_tax_amount')?.value || '0';
            const overallAmount = String(document.getElementById('overall_amount')?.value || '').replace(/,/g, '') || '0';

            document.getElementById('hidden_overall_nos').value = overallNos;
            document.getElementById('hidden_overall_quantity').value = overallQuantity;
            document.getElementById('hidden_overall_gross_amount').value = overallGrossAmount;
            document.getElementById('hidden_overall_taxable_amount').value = overallTaxableAmount;
            document.getElementById('hidden_overall_tax_amount').value = overallTaxAmount;
            document.getElementById('hidden_overall_amount').value = overallAmount;

            // Calculate and update balance
            updateBalance();
            // Re-validate amounts after overall values change
            try { if (typeof validateAmounts === 'function') validateAmounts(); } catch (e) { }
        }

        // Function to calculate and update balance
        function updateBalance() {
            var netRateVal = document.getElementById('overall_amount')?.value || '0';
            const netRate = parseFloat(netRateVal.replace(/,/g, '')) || 0;
            const paidAmount = parseFloat(document.getElementById('paid_amount_hidden')?.value || '0');
            const balance = netRate - paidAmount;
            
            // Format and update balance field
            const balInput = $('#balance');
            // format according to currency settings (without symbol, to match other fields)
            const formatted = balance.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
            balInput.val(formatted);
            balInput.attr('data-raw', balance.toFixed(2));
            $('#balance_hidden').val(balance.toFixed(2));
             
             // If window.bindCurrencyInput is available, let it re-format to ensure consistency with other fields
             if (window.bindCurrencyInput && balInput.length) {
                 window.bindCurrencyInput(balInput[0]);
             }
        }

        // Make function globally accessible
        window.updateHiddenFields = updateHiddenFields;
        window.updateBalance = updateBalance;

        // Observe changes to overall_amount to update balance and re-validate
        if (document.getElementById('overall_amount')) {
            const observer = new MutationObserver(function(){
                try { updateBalance(); } catch(e){}
                try { if (typeof validateAmounts === 'function') validateAmounts(); } catch(e){}
            });
            observer.observe(document.getElementById('overall_amount'), { attributes: true, attributeFilter: ['value'] });
        }
        @include('partials.cart_submit_sync', ['formId' => 'sale-form', 'actionAvailabilityFn' => 'updateSaleActionAvailability'])
    </script>

        <!-- Credit limit confirmation modal -->
        <div class="modal fade" id="creditLimitModal" tabindex="-1" role="dialog" aria-labelledby="creditLimitModalLabel" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="creditLimitModalLabel">Credit Limit Warning</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <p id="creditLimitModalMessage"></p>
                        <ul class="list-unstyled">
                            <li><strong>Customer:</strong> <span id="creditLimitModalCustomer"></span></li>
                            <li><strong>Potential Balance:</strong> <span id="creditLimitModalPotential"></span></li>
                            <li><strong>Credit Limit:</strong> <span id="creditLimitModalLimit"></span></li>
                            <li><strong>Grace:</strong> <span id="creditLimitModalGrace"></span></li>
                        </ul>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" id="creditLimitCancel" data-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-warning" id="creditLimitSaveDraft">Save as Draft</button>
                        <button type="button" class="btn btn-primary" id="creditLimitContinue">Continue</button>
                    </div>
                </div>
            </div>
        </div>

    <script>
        // Basic input sanitation to prevent special characters where not allowed.
        (function () {
            function sanitize(selector, pattern) {
                $(document).on('input', selector, function () {
                    var val = $(this).val();
                    var cleaned = val.replace(pattern, '');
                    if (val !== cleaned) $(this).val(cleaned);
                });
            }

            // Area: allow letters, numbers, spaces, hyphen, dot and apostrophe
            sanitize('#area', /[^A-Za-z0-9\s\-\.\']/g);

            // Phone: digits only
            sanitize('#phone', /[^0-9]/g);

            // Discount type removed; no input to sanitize

            // Opening balance: allow digits and dot only (commas will be removed)
            sanitize('#opening_balance', /[^0-9\.]/g);
        })();
    </script>

    <script>
        $(function () {

            // ── Phone-number lookup ──────────────────────────────────────────────
            // When exactly 10 digits are typed in the phone field, look up the
            // matching customer by phone and auto-fill the customer dropdown and
            // all related fields (area, balance, days, bill type, etc.).
            var _phoneSearchTimer = null;
            $('#phone').on('input', function () {
                clearTimeout(_phoneSearchTimer);
                var rawPhone = $(this).val().replace(/\D/g, '');
                if (rawPhone.length !== 10) return;

                _phoneSearchTimer = setTimeout(function () {
                    $.get('/api/customers/by-phone/' + rawPhone)
                        .done(function (res) {
                            if (!res || !res.id) return;

                            // Programmatically set the Select2 customer dropdown.
                            // The option already exists because the list is server-rendered,
                            // so we just set the value and fire the change event which
                            // handles populating every other field.
                            $('#customer_id').val(res.id).trigger('change');

                            // Restore the phone field – the customer change handler
                            // overwrites it with customer_phone from the JSON response,
                            // which should be the same number but we set it explicitly.
                            setTimeout(function () {
                                $('#phone').val(rawPhone);
                                validatePhone(document.getElementById('phone'));
                            }, 650);
                        })
                        .fail(function () {
                            // Phone not matched – leave the dropdown unchanged.
                        });
                }, 500);
            });
        });

        // Functions to handle draft vs complete submission
        function prepareDraftSubmission() {
            // Set draft flag
            document.getElementById('is_draft').value = '1';
            
            // Remove required attributes for draft submission (keep date required)
            $('#customer_id').prop('required', false);
            $('#bill_type').prop('required', false);
            $('#opening_balance').prop('required', false);
            $('#paid_amount').prop('required', false);
        }

        /**
         * Called by the "Save as Draft" button (type="button").
         * If an auto-save fetch is currently in-flight we wait for it to complete so
         * that #draft_id is populated before we submit the form.  This prevents the
         * race condition where clicking Save Draft quickly after adding a product
         * submits with an empty draft_id and creates a duplicate draft record.
         */
        function handleDraftSave() {
            prepareDraftSubmission(); // set is_draft=1 and relax required fields

            var $form = $('#sale-form');
            var autoSaveState = window._saleAutoSaveState;

            if (autoSaveState && autoSaveState.isSaving) {
                // Wait for in-flight auto-save to finish (max 5 s safety timeout)
                var waited = 0;
                var waitInterval = setInterval(function () {
                    waited += 50;
                    if (!autoSaveState.isSaving || waited >= 5000) {
                        clearInterval(waitInterval);
                        if (!autoSaveState.isFormSubmitting) {
                            $form.submit();
                        }
                    }
                }, 50);
            } else {
                $form.submit();
            }
        }

        function prepareCompleteSubmission() {
            // Set complete flag
            document.getElementById('is_draft').value = '0';
            
            // Ensure required attributes are set for complete submission
            $('#customer_id').prop('required', true);
            $('#bill_type').prop('required', true);
            $('#opening_balance').prop('required', true);
            
            // Handle paid_amount requirement based on bill_type
            if ($('#bill_type').val() === 'Cash') {
                $('#paid_amount').prop('required', true);
            } else {
                $('#paid_amount').prop('required', false);
            }
            
            // Submit the form
            return true;
        }
    </script>

    <script>
        // Fetch customer details when selection changes and populate fields
        $(function () {
            $('#customer_id').on('change', function () {
                var id = $(this).val();
                if (!id) return;

                var url = '/customers/' + id + '/json';

                    $.get(url).done(function (res) {
                        // Only set Balance field from open balance and preserve bill type default
                        $('#area').val(res.area || '');
                        $('#opening_balance').val(res.opening_balance !== undefined ? res.opening_balance : '0.00');
                        $('#bill_balance_display').val(res.bill_balance_formatted !== undefined ? res.bill_balance_formatted : '0.00');
                        $('#total_balance_display').val(res.total_balance_formatted !== undefined ? res.total_balance_formatted : '0.00');
                        $('#vat_id_display').val(res.vat_id || '');
                    // Populate excess amount
                    if (res.excess_amount !== undefined) {
                        $('#excess_amount_display').val(res.excess_amount);
                        $('#excess_amount').val(res.excess_amount);
                    } else {
                        $('#excess_amount_display').val('0.00');
                        $('#excess_amount').val('0.00');
                    }
                    // Preserve the current bill type (e.g., Cash & Carry) unless the server
                    // explicitly provides a default for this customer.
                    if (res.default_bill_type) {
                        $('#bill_type').val(res.default_bill_type);
                    }
                    $('#phone').val(res.customer_phone || '');
                    validatePhone(document.getElementById('phone'));
                    $('#customer_discount_percent').val(res.cash_discount || 0);
                    setTimeout(function() {
                        $('#bill_type').trigger('change');
                        updateBalance();
                    }, 100);
                }).fail(function () {
                    // reset if fail (preserve bill_type instead of clearing it)
                    $('#area,#opening_balance,#phone,#customer_discount_percent').val('');
                    $('#excess_amount_display,#excess_amount').val('0.00');
                    $('#bill_balance_display,#total_balance_display').val('0.00');
                    $('#vat_id_display').val('');
                });
            });
        });
    </script>

    <script>
        // Generate reference number by calling server
        function generateReferenceNumber() {
            var billDate = $('#bill_date').val();

            function financialYearFromDate(dateText) {
                var d = new Date(dateText + 'T00:00:00');
                if (isNaN(d.getTime())) {
                    return null;
                }

                var year = d.getFullYear();
                var month = d.getMonth() + 1;
                if (month >= 4) {
                    return year + '-' + (year + 1);
                }

                return (year - 1) + '-' + year;
            }

            function financialYearFromReference(referenceText) {
                var match = String(referenceText || '').match(/\/(\d{4}-\d{4})$/);
                return match ? match[1] : null;
            }

            var targetFy = financialYearFromDate(billDate);
            var currentReferenceFy = financialYearFromReference($('#reference').val());
            if (targetFy && currentReferenceFy && targetFy === currentReferenceFy) {
                return;
            }

            $.get('{{ route("sales.next-reference") }}', { date: billDate })
                .done(function(response) {
                    $('#reference').val(response.reference);
                })
                .fail(function() {
                    // Fallback: generate locally if server call fails
                    var currentDate = billDate ? new Date(billDate + 'T00:00:00') : new Date();
                    var currentYear = currentDate.getFullYear();
                    var currentMonth = currentDate.getMonth() + 1;
                    
                    var financialYear;
                    if (currentMonth >= 4) {
                        financialYear = currentYear + '-' + (currentYear + 1);
                    } else {
                        financialYear = (currentYear - 1) + '-' + currentYear;
                    }
                    
                    var randomNum = Math.floor(Math.random() * 90000) + 10000;
                    var formattedNumber = randomNum.toString().padStart(5, '0');
                    
                    var reference = 'SSA/' + formattedNumber + '/' + financialYear;
                    $('#reference').val(reference);
                });
        }
    </script>

    <script>
        // Auto-save draft functionality
        (function() {
            // Configuration
            var config = {
                autoSaveUrl: '{{ route("sales.auto-save-draft") }}',
                csrfToken: '{{ csrf_token() }}',
                autoSaveIntervalMs: 45000,  // Auto-save every 45 seconds (optimized balance)
                idleTimeoutMs: 5000,        // Save 5 seconds after user stops
                debounceMs: 1000            // Minimum time between saves
            };
            
            // State
            var state = {
                isFormSubmitting: false,
                isSaving: false,
                autoSaveInterval: null,
                lastSaveTime: 0,
                hasUnsavedChanges: false
            };

            // Expose state so the Save Draft button handler can wait for in-flight saves
            window._saleAutoSaveState = state;

            // Track form changes with debounced flag
            function trackChanges() {
                // Track regular form inputs
                $('#sale-form').on('change input', 'input, select, textarea', function() {
                    state.hasUnsavedChanges = true;
                });
                
                // Track Livewire events for cart updates (Livewire 3 syntax)
                if (typeof Livewire !== 'undefined') {
                    Livewire.hook('commit', ({ succeed }) => {
                        succeed(() => { 
                            state.hasUnsavedChanges = true;
                            // Trigger immediate save if content exists
                            if (hasContentToSave()) {
                                performAutoSave(false);
                            }
                        });
                    });
                    Livewire.on('refreshCart', () => { 
                        state.hasUnsavedChanges = true;
                        // Trigger immediate save if content exists
                        if (hasContentToSave()) {
                            performAutoSave(false);
                        }
                    });
                }
                
                // Track custom events
                ['cart-updated', 'product-added'].forEach(function(eventName) {
                    window.addEventListener(eventName, function() {
                        state.hasUnsavedChanges = true;
                        // Trigger immediate save if content exists
                        if (hasContentToSave()) {
                            performAutoSave(false);
                        }
                    });
                });
            }

            // Collect form data for auto-save
            function collectFormData() {
                var formData = new FormData(document.getElementById('sale-form'));
                
                // Ensure hidden fields are updated
                if (typeof updateHiddenFields === 'function') {
                    updateHiddenFields();
                }

                // Explicitly collect Overall Calculations from Livewire component inputs
                var overallFields = [
                    'overall_nos', 'overall_quantity', 'overall_gross_amount',
                    'overall_taxable_amount', 'overall_tax_amount', 'overall_amount'
                ];
                overallFields.forEach(function(fieldName) {
                    var element = document.getElementById(fieldName);
                    if (element) {
                        formData.set(fieldName, element.value || '0');
                    }
                });

                // Add draft_id if we have one saved
                var draftId = $('#draft_id').val();
                if (draftId) {
                    formData.set('draft_id', draftId);
                }

                // Force draft mode
                formData.set('is_draft', '1');

                return formData;
            }

            // Check if there's something worth saving (only consider the product-cart table)
            function hasContentToSave() {
                // Only count rows inside the product cart table to avoid matching product-search results
                var cartItems = document.querySelectorAll('.product-cart-table tbody tr[data-product-id]');
                return cartItems.length > 0;
            }

            // Perform auto-save with debounce protection
            function performAutoSave(isBeforeUnload) {
                // Skip if form is submitting or already saving
                if (state.isFormSubmitting || state.isSaving) {
                    return Promise.resolve(false);
                }

                // Skip if nothing to save
                if (!hasContentToSave()) {
                    return Promise.resolve(false);
                }
                
                // Debounce: skip if saved recently (unless beforeunload)
                var now = Date.now();
                if (!isBeforeUnload && (now - state.lastSaveTime) < config.debounceMs) {
                    return Promise.resolve(false);
                }
                
                state.isSaving = true;
                var formData = collectFormData();

                // For beforeunload, use synchronous XMLHttpRequest (necessary for page unload)
                if (isBeforeUnload) {
                    var xhr = new XMLHttpRequest();
                    xhr.open('POST', config.autoSaveUrl, false);
                    xhr.setRequestHeader('X-CSRF-TOKEN', config.csrfToken);
                    xhr.setRequestHeader('Accept', 'application/json');
                    
                    try {
                        xhr.send(formData);
                        if (xhr.status === 200) {
                            var response = JSON.parse(xhr.responseText);
                            if (response.success && response.draft_id) {
                                $('#draft_id').val(response.draft_id);
                                state.lastSaveTime = Date.now();
                            } else if (response.message && /nothing to save/i.test(response.message)) {
                                // Nothing to save on server; treat as non-error for beforeunload
                            }
                        }
                    } catch (e) {
                        // Silent fail for beforeunload
                    } finally {
                        state.isSaving = false;
                    }
                    return true;
                }

                // Show saving indicator
                showAutoSaveStatus('saving');

                // For regular saves, use async fetch
                return fetch(config.autoSaveUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': config.csrfToken,
                        'Accept': 'application/json'
                    },
                    body: formData
                })
                .then(function(response) {
                    if (!response.ok) throw new Error('Network response was not ok');
                    return response.json();
                })
                .then(function(data) {
                    if (data.success && data.draft_id) {
                        $('#draft_id').val(data.draft_id);
                        state.hasUnsavedChanges = false;
                        state.lastSaveTime = Date.now();
                        showAutoSaveStatus('saved');
                    } else if (data.message && /nothing to save/i.test(data.message)) {
                        // Server indicates there was nothing to save — treat as non-error
                        state.hasUnsavedChanges = false;
                        showAutoSaveStatus('saved');
                    } else if (data.message === 'Insufficient data to save draft') {
                        // Keep saving status until product is selected
                    } else {
                        showAutoSaveStatus('error');
                    }
                    return data.success === true;
                })
                .catch(function(error) {
                    showAutoSaveStatus('error');
                    return false;
                })
                .finally(function() {
                    state.isSaving = false;
                });
            }

            // Show auto-save status indicator
            function showAutoSaveStatus(status) {
                var $statusDiv = $('#auto-save-status');
                var $message = $('#auto-save-message');
                
                if (status === 'ready') {
                    $message.html('<i class="bi bi-info-circle"></i> Add product to save the Draft');
                    $statusDiv.find('.alert').removeClass('alert-success alert-danger').addClass('alert-info');
                    $statusDiv.fadeIn(200);
                } else if (status === 'saving') {
                    // Only show saving status when there is content to save
                    if (!hasContentToSave()) {
                        showAutoSaveStatus('ready');
                        return;
                    }
                    $message.html('<i class="bi bi-cloud-arrow-up"></i> Saving draft...');
                    $statusDiv.find('.alert').removeClass('alert-success alert-danger').addClass('alert-info');
                    $statusDiv.fadeIn(200);
                } else if (status === 'saved') {
                    $message.html('<i class="bi bi-check-circle"></i> Draft saved');
                    $statusDiv.find('.alert').removeClass('alert-info alert-danger').addClass('alert-success');
                    setTimeout(function() {
                        $statusDiv.fadeOut(300);
                    }, 2000);
                } else if (status === 'error') {
                    $message.html('<i class="bi bi-exclamation-triangle"></i> Save failed');
                    $statusDiv.find('.alert').removeClass('alert-info alert-success').addClass('alert-danger');
                    setTimeout(function() {
                        $statusDiv.fadeOut(300);
                    }, 3000);
                }
            }

            // Handle page visibility change (user switches tabs)
            function handleVisibilityChange() {
                if (document.visibilityState === 'hidden' && state.hasUnsavedChanges && hasContentToSave()) {
                    performAutoSave(false);
                }
            }

            // Handle beforeunload event (closing tab, navigating away)
            function handleBeforeUnload(e) {
                if (state.isFormSubmitting) return;

                if (state.hasUnsavedChanges && hasContentToSave()) {
                    performAutoSave(true);
                    // Modern browsers ignore custom messages, but we still need to set returnValue
                    e.preventDefault();
                    e.returnValue = '';
                }
            }

            // Handle back button click
            function handleBackButton(e) {
                if (state.hasUnsavedChanges && hasContentToSave()) {
                    e.preventDefault();
                    
                    if (confirm('You have unsaved changes. Do you want to save as draft before leaving?')) {
                        performAutoSave(false).then(function(saved) {
                            showAutoSaveStatus(saved ? 'saved' : 'error');
                            setTimeout(function() {
                                window.location.href = '{{ route("sales.index") }}';
                            }, saved ? 500 : 0);
                        });
                    } else {
                        window.location.href = '{{ route("sales.index") }}';
                    }
                }
            }

            // Handle browser back/forward navigation
            function handlePopState(e) {
                if (state.hasUnsavedChanges && hasContentToSave()) {
                    performAutoSave(true);
                }
            }

            // Start periodic auto-save
            function startAutoSaveInterval() {
                state.autoSaveInterval = setInterval(function() {
                    if (state.hasUnsavedChanges && hasContentToSave()) {
                        performAutoSave(false);
                    }
                }, config.autoSaveIntervalMs);
            }

            // Mark form as submitting to prevent auto-save interference
            function markFormSubmitting() {
                state.isFormSubmitting = true;
                if (state.autoSaveInterval) {
                    clearInterval(state.autoSaveInterval);
                    state.autoSaveInterval = null;
                }
            }

            // Initialize auto-save functionality
            function initAutoSave() {
                showAutoSaveStatus('ready');
                trackChanges();

                // Event listeners
                window.addEventListener('beforeunload', handleBeforeUnload);
                document.addEventListener('visibilitychange', handleVisibilityChange);
                window.addEventListener('popstate', handlePopState);
                
                $('#back-btn').on('click', handleBackButton);
                $('#sale-form').on('submit', markFormSubmitting);

                // Start periodic auto-save
                startAutoSaveInterval();

                // Idle save with debounce
                var idleTimeout;
                $('#sale-form').on('blur', 'input, select, textarea', function() {
                    clearTimeout(idleTimeout);
                    idleTimeout = setTimeout(function() {
                        if (state.hasUnsavedChanges && hasContentToSave()) {
                            performAutoSave(false);
                        }
                    }, config.idleTimeoutMs);
                });

                // Action availability: disable Create + Save Draft when no products
                function updateSaleActionAvailability() {
                    var has = hasContentToSave();
                    var $createBtns = $('#sale-form').find('button[type=submit]');
                    var $draftBtn = $('#save-draft-btn');
                    // If any validation has already disabled submit, respect that as well
                    var submitDisabled = $createBtns.length ? $createBtns.first().prop('disabled') : false;
                    var shouldDisable = !has || submitDisabled;

                    $createBtns.prop('disabled', shouldDisable);
                    if ($draftBtn.length) $draftBtn.prop('disabled', shouldDisable);

                    // No helper text — just keep buttons enabled/disabled based on cart and validation
                }

                // Expose for other code and call initially
                window.updateSaleActionAvailability = updateSaleActionAvailability;
                updateSaleActionAvailability();

                // React to Livewire and product events
                document.addEventListener('livewire:updated', updateSaleActionAvailability);
                ['cart-updated', 'product-added', 'product-removed'].forEach(function(ev) { window.addEventListener(ev, updateSaleActionAvailability); });
                var cartContainerElBtns = document.querySelector('.table-responsive');
                if (cartContainerElBtns && window.MutationObserver) {
                    var moBtns = new MutationObserver(function(){ updateSaleActionAvailability(); });
                    moBtns.observe(cartContainerElBtns, { childList: true, subtree: true, attributes: true });
                }
            }

            // Initialize when document is ready
            $(document).ready(function() {
                initAutoSave();
            });
        })();
    </script>
@endpush
{{-- @endpush --}}
