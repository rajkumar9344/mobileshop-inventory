@extends('layouts.app')

@section('title', 'Edit Sale')

@section('breadcrumb')
    @php $isReadOnly = !empty($readonly); @endphp
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item"><a href="{{ route('sales.index') }}">Sales</a></li>
        <li class="breadcrumb-item active">{{ $isReadOnly ? 'View' : 'Edit' }}</li>
    </ol>
@endsection

@section('content')
    <div class="container-fluid mb-4">
        

        <div class="row mt-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body">
                        @include('utils.alerts')
                        <div id="credit-limit-warning" class="alert alert-danger d-none">
                            <strong>Warning:</strong> Credit limit exceeded for this customer. Please settle outstanding dues before proceeding.
                        </div>
                        @php $isReadOnly = !empty($readonly); @endphp
                        <form id="sale-form" action="{{ $isReadOnly ? '#' : route('sales.update', $sale) }}" method="POST">
                            @csrf
                            @method('patch')
                            <input type="hidden" wire:model.live="customer_discount_percent" id="customer_discount_percent" value="{{ old('customer_discount_percent', 0) }}">

                            <div class="border p-3 mb-3">
                                <div class="form-row">
                                    <div class="col-md-2 pr-1">
                                        <label for="reference" class="mb-1">Bill Reference No <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="reference" id="reference" readonly maxlength="15" pattern="[A-Za-z0-9\-\/]+" value="{{ $sale->reference }}">
                                    </div>
                                    <div class="col-md-2 pr-1">
                                        <label for="bill_date" class="mb-1">Bill Date <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control" name="date" id="bill_date" {{ $isReadOnly ? 'disabled' : 'required' }} value="{{ $sale->date ? \Carbon\Carbon::parse($sale->date)->format('Y-m-d') : now()->format('Y-m-d') }}">
                                    </div>
                                    <div class="col-md-2 pr-1">
                                        <label for="phone" class="mb-1">Phone No</label>
                                        <input type="tel" class="form-control" name="phone" id="phone" maxlength="15" pattern="\+?[0-9]{7,15}" title="Only numbers and + (UAE contact number, max 15)" value="{{ old('phone', $sale->phone_no) }}" oninput="validatePhone(this)" placeholder="+971501234567" {{ $isReadOnly ? 'disabled' : '' }}>
                                        <small id="phone-error" class="text-danger" style="display: none;"></small>
                                    </div>
                                    <div class="col-md-3 pr-1">
                                        <label for="customer_id" class="mb-1">Customer Name @if($sale->status !== 'Draft') <span class="text-danger">*</span> @endif</label>
                                        <select class="form-control select2-customer" name="customer_id" id="customer_id" {{ $isReadOnly ? 'disabled' : ($sale->status === 'Draft' ? '' : 'required') }}>
                                            <option value="">-- Select customer --</option>
                                            @foreach(\Modules\People\Entities\Customer::where(function($q) use ($sale){ $q->where('is_active', true)->orWhere('id', $sale->customer_id); })->orderBy('customer_name','asc')->get() as $customer)
                                                <option value="{{ $customer->id }}" {{ $sale->customer_id == $customer->id ? 'selected' : '' }}>{{ $customer->customer_name }}{{ !$customer->is_active ? ' (Inactive)' : '' }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-2 pr-1">
                                        <label for="area" class="mb-1">Area</label>
                                        <input type="text" class="form-control" name="area" id="area" maxlength="30" value="{{ old('area', $sale->area) }}" placeholder="Area" {{ $isReadOnly ? 'disabled' : '' }}>
                                    </div>
                                </div>
                                <div class="form-row mt-2 mb-3">
                                    {{-- Bill Type removed from UI; kept as hidden value so payment logic stays intact --}}
                                    <input type="hidden" name="bill_type" id="bill_type" value="{{ old('bill_type', $sale->bill_type ?: \Modules\Sale\Entities\Sale::BILL_CASH) }}">

                                    <div class="col-md-2 pr-1">
                                        <label for="opening_balance" class="mb-1">Open Balance @if($sale->status !== 'Draft') <span class="text-danger">*</span> @endif</label>
                                            <input type="text" class="form-control" name="opening_balance" id="opening_balance" maxlength="15" pattern="^-?\d+(?:\.\d{1,2})?$|^-?\d+(?:,\d{1,2})?$" value="{{ number_format($sale->balance ?? 0, 2, '.', '') }}" placeholder="0.00" {{ $isReadOnly ? 'disabled' : ($sale->status === 'Draft' ? '' : 'required') }} readonly oninput="this.value = this.value.replace(/[^0-9.\-]/g,'').replace(/(?!^)-/g,'').slice(0,15)">
                                        </div>
                                        <div class="col-md-2 pr-1">
                                            <label for="bill_balance_display" class="mb-1">Bill Balance</label>
                                            <input type="text" class="form-control" id="bill_balance_display" readonly value="{{ $sale->customer_id ? number_format($sale->bill_balance_before ?? (optional($sale->customer)->bill_balance ?? 0), 2, '.', '') : '0.00' }}">
                                        </div>
                                        <div class="col-md-2 pr-1">
                                            <label for="total_balance_display" class="mb-1">Total Balance</label>
                                            <input type="text" class="form-control" id="total_balance_display" readonly value="{{ $sale->customer_id ? number_format(((float)($sale->balance ?? 0)) + ((float)($sale->bill_balance_before ?? (optional($sale->customer)->bill_balance ?? 0))), 2, '.', '') : '0.00' }}">
                                        </div>
                                        <div class="col-md-2 pr-1">
                                            <label for="excess_amount_display" class="mb-1">Excess Amount</label>
                                            <input type="text" class="form-control" id="excess_amount_display" readonly value="{{ $sale->customer_id ? number_format($sale->customer->excess_amount ?? 0, 2, '.', '') : '0.00' }}">
                                            <input type="hidden" name="excess_amount" id="excess_amount" value="{{ $sale->customer_id ? $sale->customer->excess_amount ?? 0 : 0 }}">
                                        </div>
                                        <div class="col-md-2 pr-1">
                                            <label for="vat_id_display" class="mb-1">VAT ID / TRN</label>
                                            <input type="text" class="form-control" id="vat_id_display" readonly
                                                value="{{ $sale->customer_id ? ($sale->customer->vat_id ?? '') : '' }}" placeholder="—">
                                        </div>
                                        <!-- Discount Type removed per request -->
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

                            <livewire:product-cart :cartInstance="$cartInstance ?? 'sale'" :data="$sale" :readonly="$isReadOnly" />

                            <div class="form-row">
                                <div class="col-lg-3">
                                    <div class="form-group">
                                        <label for="payment_method">Payment Method</label>
                                        <select class="form-control" name="payment_method" id="payment_method">
                                            <option value="">-- Select payment method --</option>
                                            <option value="Cash" {{ $sale->payment_method == 'Cash' ? 'selected' : '' }}>Cash</option>
                                            <option value="Cheque" {{ $sale->payment_method == 'Cheque' ? 'selected' : '' }}>Cheque</option>
                                            <option value="Cards" {{ $sale->payment_method == 'Cards' ? 'selected' : '' }}>Cards</option>
                                            <option value="Bank Transfer" {{ $sale->payment_method == 'Bank Transfer' ? 'selected' : '' }}>Bank Transfer</option>
                                            <option value="UPI Payment" {{ $sale->payment_method == 'UPI Payment' ? 'selected' : '' }}>UPI Payment</option>
                                        </select>
                                        <div id="payment_method_error" class="invalid-feedback d-none">Please select the payment method.</div>
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
                                            value="{{ $sale->paid_amount }}"
                                            required
                                            :disabled="$isReadOnly"
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
                                            value="{{ $sale->due_amount ?? 0 }}"
                                            readonly
                                            symbol="{{ settings()->currency->symbol }}"
                                            position="{{ settings()->default_currency_position }}"
                                        />
                                    </div>
                                </div>
                                <!-- Settled checkbox removed: settled logic handled in receipts module -->
                            </div>

                            <input type="hidden" name="total_amount" id="hidden_total_amount" value="{{ old('total_amount', $sale->total_amount ?? 0) }}">

                            <!-- Hidden fields for Overall Calculations (backup - actual values come from Livewire component) -->
                            <!-- Removed duplicate name attributes to avoid conflicts with Livewire component inputs -->
                            <input type="hidden" id="hidden_overall_nos" name="overall_nos" value="{{ old('overall_nos', $sale->overall_nos ?? 0) }}">
                            <input type="hidden" id="hidden_overall_quantity" name="overall_quantity" value="{{ old('overall_quantity', $sale->overall_quantity ?? 0) }}">
                            <input type="hidden" id="hidden_overall_gross_amount" name="overall_gross_amount" value="{{ old('overall_gross_amount', $sale->overall_gross_amount ?? 0) }}">
                            <input type="hidden" id="hidden_overall_taxable_amount" name="overall_taxable_amount" value="{{ old('overall_taxable_amount', $sale->overall_taxable_amount ?? 0) }}">
                            <input type="hidden" id="hidden_overall_tax_amount" name="overall_tax_amount" value="{{ old('overall_tax_amount', $sale->overall_tax_amount ?? 0) }}">
                            <input type="hidden" id="hidden_overall_amount" name="overall_amount" value="{{ old('overall_amount', $sale->overall_amount ?? 0) }}">
                            <input type="hidden" name="is_draft" id="is_draft" value="0">

                            <div class="form-group">
                                <label for="note">Note (If Needed)</label>
                                <textarea name="note" id="note" rows="5" class="form-control" {{ $isReadOnly ? 'disabled' : '' }}>{{ $sale->note }}</textarea>
                            </div>

                            <div class="row mt-3 mb-3">
                                <div class="col-12 d-flex justify-content-end">
                                    <a href="{{ route('sales.index') }}" class="btn btn-secondary mr-2">Back</a>
                                    @unless($isReadOnly)
                                        <button type="submit" class="btn btn-primary" onclick="prepareCompleteUpdate()">
                                            Update Sale <i class="bi bi-check"></i>
                                        </button>
                                    @endunless
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
        (function($){
            // Helper: async credit-limit check (returns Promise resolving to server response)
            var currentSaleId = '{{ $sale->id }}';
            function isCreditLimitCheck(customerId, totalAmount, paidAmount) {
                return $.ajax({
                    url: '{{ route("sales.check-credit-limit") }}',
                    method: 'GET',
                    data: {
                        customer_id: customerId,
                        total_amount: totalAmount,
                        paid_amount: paidAmount,
                        sale_id: currentSaleId
                    }
                }).then(function(response) {
                    return response || { credit_limit_reached: false, credit_limit_blocked: false };
                }).catch(function() {
                    return { credit_limit_reached: false, credit_limit_blocked: false };
                });
            }

            function updateBalance(){
                var netRateVal = document.getElementById('overall_amount')?.value || '0';
                var netRate = parseFloat(netRateVal.replace(/,/g, '')) || 0;
                var paidAmount = parseFloat(document.getElementById('paid_amount_hidden')?.value || '0');
                var balance = netRate - paidAmount;
                
                // Format and update balance field
                const balInput = $('#balance');
                const formatted = balance.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
                balInput.val(formatted);
                balInput.attr('data-raw', balance.toFixed(2));
                $('#balance_hidden').val(balance.toFixed(2));
                
                if (window.bindCurrencyInput && balInput.length) {
                    window.bindCurrencyInput(balInput[0]);
                }
            }

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
                        showMessage('<div class="alert alert-warning">"' + q + '" was not found in the current sale cart.</div>');
                    }
                }

                $(document).on('click', '#quick-cart-search-btn', doSearch);
                $(document).on('keydown', '#quick-cart-search', function(e){ if (e.key === 'Enter') { e.preventDefault(); doSearch(); } });
            })();

            function updateHiddenFields(){
                // Get current cart total dynamically (support masked / formatted inputs)
                var cartTotal = 0;
                var $overall = $('#overall_amount');
                if ($overall.length) {
                    // Prefer maskMoney if present (legacy)
                    if ($overall.data('maskMoney')) {
                        cartTotal = $overall.maskMoney('unmasked')[0] || 0;
                    } else {
                        // currency-input writes the numeric value in dataset.raw on the display element
                        var el = $overall.get(0);
                        if (el && el.dataset && el.dataset.raw) {
                            cartTotal = parseFloat(String(el.dataset.raw).replace(/[^0-9.\-]/g, '')) || 0;
                        } else if (el && el.value) {
                            cartTotal = parseFloat(String(el.value).replace(/[^0-9.\-]/g, '')) || 0;
                        } else {
                            var cartTotalElement = document.querySelector('.table-responsive .table .table-striped tr:last-child th:last-child');
                            var text = cartTotalElement ? cartTotalElement.textContent : '';
                            cartTotal = parseFloat(String(text).replace(/[^0-9.\-]/g, '')) || 0;
                        }
                    }
                }
                document.getElementById('hidden_total_amount').value = cartTotal;

                var overallNos = document.getElementById('overall_nos')?.value || document.getElementById('hidden_overall_nos').value || '0';
                var overallQuantity = document.getElementById('overall_quantity')?.value || document.getElementById('hidden_overall_quantity').value || '0';
                var overallGrossAmount = document.getElementById('overall_gross_amount')?.value || document.getElementById('hidden_overall_gross_amount').value || '0';
                var overallTaxableAmount = document.getElementById('overall_taxable_amount')?.value || document.getElementById('hidden_overall_taxable_amount').value || '0';
                var overallTaxAmount = document.getElementById('overall_tax_amount')?.value || document.getElementById('hidden_overall_tax_amount').value || '0';
                var overallAmount = String(document.getElementById('overall_amount')?.value || '').replace(/,/g, '') || document.getElementById('hidden_overall_amount').value || '0';

                document.getElementById('hidden_overall_nos').value = overallNos;
                document.getElementById('hidden_overall_quantity').value = overallQuantity;
                document.getElementById('hidden_overall_gross_amount').value = overallGrossAmount;
                document.getElementById('hidden_overall_taxable_amount').value = overallTaxableAmount;
                document.getElementById('hidden_overall_tax_amount').value = overallTaxAmount;
                document.getElementById('hidden_overall_amount').value = overallAmount;

                updateBalance();
                // Re-validate amounts whenever hidden overall values change
                try { if (typeof validateAmounts === 'function') validateAmounts(); } catch(e) { }
            }

            function generateReferenceNumberForDate(){
                if ({{ !empty($readonly) ? 'true' : 'false' }}) {
                    return;
                }

                var billDate = $('#bill_date').val();
                if (!billDate) {
                    return;
                }

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
                var originalReference = @json($sale->reference);
                var originalReferenceFy = financialYearFromReference(originalReference);
                if (targetFy && originalReferenceFy && targetFy === originalReferenceFy) {
                    $('#reference').val(originalReference);
                    return;
                }

                var currentReferenceFy = financialYearFromReference($('#reference').val());
                if (targetFy && currentReferenceFy && targetFy === currentReferenceFy) {
                    return;
                }

                $.get('{{ route("sales.next-reference") }}', { date: billDate })
                    .done(function(response) {
                        if (response && response.reference) {
                            $('#reference').val(response.reference);
                        }
                    });
            }

            function bindCustomerLookup(){
                $('#customer_id').on('change', function(){
                    var id = $(this).val();
                    if (!id) {
                        $('#area,#opening_balance,#phone').val('');
                        $('#customer_discount_percent').val(0);
                        $('#vat_id_display').val('');
                        return;
                    }
                    var url = '/customers/' + id + '/json';
                    $.get(url).done(function(res){
                        if (res.area !== undefined) {
                            $('#area').val(res.area);
                        }
                        if (res.opening_balance !== undefined) {
                            $('#opening_balance').val(res.opening_balance);
                        }
                        $('#bill_balance_display').val(res.bill_balance_formatted !== undefined ? res.bill_balance_formatted : '0.00');
                        $('#total_balance_display').val(res.total_balance_formatted !== undefined ? res.total_balance_formatted : '0.00');
                        if (res.excess_amount !== undefined) {
                            $('#excess_amount').val(res.excess_amount);
                            $('#excess_amount_display').val(res.excess_amount);
                        }
                        if (res.customer_phone !== undefined) {
                            $('#phone').val(res.customer_phone);
                            validatePhone(document.getElementById('phone'));
                        }
                        if (res.cash_discount !== undefined) {
                            $('#customer_discount_percent').val(res.cash_discount);
                        }
                        $('#vat_id_display').val(res.vat_id || '');
                        setTimeout(function(){ updateBalance(); }, 100);
                    }).fail(function(){
                        $('#area,#opening_balance,#phone').val('');
                        $('#customer_discount_percent').val(0);
                        $('#excess_amount,#excess_amount_display').val('0.00');
                        $('#vat_id_display').val('');
                    });
                });
            }

            // ── Phone-number lookup ───────────────────────────────────────────
            var _phoneSearchTimer = null;
            $('#phone').on('input', function () {
                clearTimeout(_phoneSearchTimer);
                var rawPhone = $(this).val().replace(/\D/g, '');
                if (rawPhone.length !== 10) return;
                _phoneSearchTimer = setTimeout(function () {
                    $.get('/api/customers/by-phone/' + rawPhone)
                        .done(function (res) {
                            if (!res || !res.id) return;
                            $('#customer_id').val(res.id).trigger('change');
                            setTimeout(function () {
                                $('#phone').val(rawPhone);
                                validatePhone(document.getElementById('phone'));
                            }, 650);
                        })
                        .fail(function () {
                            // Phone not matched – leave dropdown unchanged.
                        });
                }, 500);
            });

            $(document).ready(function(){
                $('.select2-customer').select2({
                    placeholder: 'Search and select customer...',
                    allowClear: true,
                    width: '100%'
                });

                if (window.currencyInputInit) {
                    window.currencyInputInit();
                }

                $('#paid_amount').on('keyup input', function() {
                    updateBalance();
                });
                $('#paid_amount').on('blur', function() {
                    scheduleCreditLimitCheck();
                });

                // --- Shared validation helpers ---
                function getNetRate() {
                    if (typeof updateHiddenFields === 'function') {
                        updateHiddenFields();
                    }
                    var el = document.getElementById('overall_amount');
                    if (el && el.dataset && el.dataset.raw) {
                        return parseFloat(String(el.dataset.raw).replace(/[^0-9.\-]/g, '')) || 0;
                    }
                    var visible = $('#overall_amount').val() || $('#overall_amount').text() || '0';
                    return parseFloat(String(visible).replace(/[^0-9.\-]/g, '')) || 0;
                }

                function getPaidNumeric() {
                    return parseFloat($('#paid_amount_hidden').val()) || 0;
                }

                function validateAmounts() {
                    var paidVal = getPaidNumeric();
                    var netRate = getNetRate();
                    var $submitBtns = $('#sale-form').find('button[type=submit]');
                    var hasIssue = false;

                    if (paidVal > netRate + 0.01) {
                        $('#paid_amount').addClass('is-invalid');
                        $('#paid_amount_error').text('Amount Received cannot be more than Net Rate.').show();
                        hasIssue = true;
                    } else {
                        $('#paid_amount').removeClass('is-invalid');
                        $('#paid_amount_error').hide();
                    }

                    $submitBtns.prop('disabled', hasIssue);
                    return !hasIssue;
                }

                $('#paid_amount').on('keyup input blur', function () {
                    var paidVal = getPaidNumeric();
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

                // Credit limit check on customer change
                $('#customer_id').on('change', function() {
                    checkCreditLimit();
                });

                // Credit limit check on paid amount blur (debounced via scheduleCreditLimitCheck)
                $('#paid_amount').on('blur', function() {
                    scheduleCreditLimitCheck();
                });

                // Debounced credit-limit check helper
                var _creditLimitTimer = null;
                function scheduleCreditLimitCheck() {
                    clearTimeout(_creditLimitTimer);
                    _creditLimitTimer = setTimeout(checkCreditLimit, 400);
                }

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
                    
                    // Get total from hidden field or livewire fields
                    var totalAmount = parseFloat($('#hidden_total_amount').val()) || 0;
                    // If still 0, try to get from livewire overall_amount
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

                $('#bill_date').on('change', generateReferenceNumberForDate);
                bindCustomerLookup();

                // Settled checkbox removed; receipts module manages unsettled receipts

                $('#sale-form').on('submit', function(e){
                    e.preventDefault();
                    var $form = $(this);
                    var $submitButtons = $form.find('button[type=submit], input[type=submit]');
                    $submitButtons.prop('disabled', true);

                    var paidNumeric = parseFloat($('#paid_amount_hidden').val()) || 0;

                    updateHiddenFields();

                    // Collect purchase rate overrides from DOM (bypasses Livewire race condition)
                    $('#sale-form input[name^="submitted_purchase_rates"]').remove();
                    document.querySelectorAll('.product-cart-table tr[data-row-id]').forEach(function(row) {
                        var rowId = row.getAttribute('data-row-id');
                        if (!rowId) return;
                        var hiddenInput = document.getElementById('purchase_rate_' + rowId + '_raw');
                        if (hiddenInput && hiddenInput.value !== '') {
                            $('<input>').attr({ type: 'hidden', name: 'submitted_purchase_rates[' + rowId + ']', value: hiddenInput.value }).appendTo('#sale-form');
                        }
                    });

                    var hasError = false;
                    var netRate = getNetRate();
                    if (paidNumeric > netRate) {
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
                                $('#creditLimitModal').modal('show');

                                $('#creditLimitContinue').off('click').on('click', function(){
                                    $('#creditLimitModal').modal('hide');
                                    $form[0].submit();
                                });
                                $('#creditLimitSaveDraft').off('click').on('click', function(){
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
                            $form[0].submit();
                        }).catch(function() {
                            $submitButtons.prop('disabled', false);
                            alert('Unable to verify credit limit. Please try again.');
                        });
                    } else {
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
                        $('#paid_amount').prop('required', true);
                        $('#paid_amount').prop('readonly', false);
                    } else {
                        $('#paid_amount').prop('required', false);
                        $('#paid_amount').prop('readonly', false);
                    }
                    updateBalance();
                });

                // Initialize bill_type behaviour
                if ($('#bill_type').length) {
                    $('#bill_type').trigger('change');
                }

                updateHiddenFields();
            });

            window.updateHiddenFields = updateHiddenFields;
            window.updateBalance = updateBalance;

            // Observe changes to overall_amount to update balance
            if (document.getElementById('overall_amount')) {
                const observer = new MutationObserver(function(){
                    try { updateBalance(); } catch(e){}
                    try { if (typeof validateAmounts === 'function') validateAmounts(); } catch(e){}
                });
                observer.observe(document.getElementById('overall_amount'), { attributes: true, attributeFilter: ['value'] });
            }
                // Disable submit when cart invalid — listen for Livewire event, browser event, and fallback to DOM
                (function(){
                    function setSubmitDisabled(disabled) {
                        const form = document.getElementById('sale-form');
                        if (!form) return;
                        const btns = form.querySelectorAll('button[type="submit"]');
                        btns.forEach(b => b.disabled = disabled);
                    }

                    setSubmitDisabled(false);

                    if (window.Livewire) {
                        Livewire.on('cart-validity', function(payload) {
                            if (payload && typeof payload.valid !== 'undefined') {
                                setSubmitDisabled(!payload.valid);
                            }
                        });
                    } else {
                        document.addEventListener('livewire:load', function () {
                            Livewire.on('cart-validity', function(payload) {
                                if (payload && typeof payload.valid !== 'undefined') {
                                    setSubmitDisabled(!payload.valid);
                                }
                            });
                        });
                    }

                    window.addEventListener('cart-validity', function(e) {
                        var payload = (e && e.detail) ? e.detail : {};
                        if (payload && typeof payload.valid !== 'undefined') {
                            setSubmitDisabled(!payload.valid);
                        }
                    });

                    var _submitTimer = null;
                    function updateSubmitFromDOM() {
                        if (_submitTimer) clearTimeout(_submitTimer);
                        _submitTimer = setTimeout(function(){
                            var invalidRow = document.querySelector('tr.invalid-row, tr[style*="#f8d7da"]');
                            setSubmitDisabled(!!invalidRow);
                        }, 60);
                    }

                    var cartContainerEl = document.querySelector('.table-responsive');
                    if (cartContainerEl && window.MutationObserver) {
                        var mo = new MutationObserver(function(){ updateSubmitFromDOM(); });
                        mo.observe(cartContainerEl, { childList: true, subtree: true, attributes: true });
                    }

                    document.addEventListener('livewire:load', updateSubmitFromDOM);
                    document.addEventListener('livewire:update', updateSubmitFromDOM);
                    if (window.Livewire && Livewire.hook) {
                        try { Livewire.hook('message.processed', updateSubmitFromDOM); } catch(e){}
                    }

                    updateSubmitFromDOM();
                })();
        })(jQuery);
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

    @if(!empty($readonly))
    <script>
        // Ensure Livewire components and any interactive elements are effectively read-only
        document.addEventListener('DOMContentLoaded', function(){
            try {
                // Disable any buttons inside Livewire components to prevent cart edits
                document.querySelectorAll('#sale-form button, #sale-form input, #sale-form select, #sale-form textarea').forEach(function(el){
                    // Keep the Back button enabled
                    if (el.tagName === 'A' || (el.classList && el.classList.contains('btn-secondary'))) return;
                    el.setAttribute('disabled', 'disabled');
                });

                // Re-enable hidden CSRF inputs if needed
                document.querySelectorAll('#sale-form input[type=hidden]').forEach(function(h){ h.removeAttribute('disabled'); });
            } catch (e) { console.error(e); }
        });
    </script>
    @endif

    <script>
        // Input sanitation to prevent special characters in edit form.
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

            // Opening balance: allow digits and dot only
            sanitize('#opening_balance', /[^0-9\.]/g);
        })();

        // Functions to handle complete update
        function prepareCompleteUpdate() {
            // Set complete flag
            document.getElementById('is_draft').value = '0';
            
            // Ensure required attributes are set for complete update
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
@endpush
