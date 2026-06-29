@extends('layouts.app')

@section('title', 'Edit Quotation')

@section('breadcrumb')
    @php $isReadOnly = !empty($readonly); @endphp
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item"><a href="{{ route('quotations.index') }}">Quotations</a></li>
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



                        @php $isReadOnly = !empty($readonly); @endphp
                        {{-- Show validation errors so user knows why form didn't save --}}
                        @if ($errors->any())
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                        <form id="quotation-form" action="{{ $isReadOnly ? '#' : route('quotations.update', $quotation) }}" method="POST">
                            @csrf
                            @method('patch')
                            <div class="form-row">
                                <div class="col-lg-4">
                                    <div class="form-group">
                                        <label for="reference">Reference <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="reference" required value="{{ $quotation->reference }}" readonly>
                                    </div>
                                </div>
                                <div class="col-lg-4">
                                    <div class="form-group">
                                        <label for="customer_type">Customer Type <span class="text-danger">*</span></label>
                                        <select class="form-control" name="customer_type" id="customer_type" {{ $isReadOnly ? 'disabled' : 'required' }}>
                                            <option value="existing" {{ ($quotation->customer_type ?? 'existing') == 'existing' ? 'selected' : '' }}>Existing</option>
                                            <option value="new" {{ ($quotation->customer_type ?? 'existing') == 'new' ? 'selected' : '' }}>New</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="col-lg-4">
                                    <div class="form-group">
                                        <label for="date">Date <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control" name="date" {{ $isReadOnly ? 'disabled' : 'required' }} value="{{ $quotation->getAttributes()['date'] }}">
                                    </div>
                                </div>
                            </div>

                            <div class="form-row mt-3">
                                <div class="col-lg-3">
                                    <div class="form-group">
                                        <label for="customer_id">Customer Name <span class="text-danger">*</span></label>
                                        <div id="existing-customer-block" style="{{ ($quotation->customer_type ?? 'existing') == 'existing' ? '' : 'display:none;' }}">
                                            <select class="form-control select2-customer" name="customer_id" id="customer_id" {{ $isReadOnly ? 'disabled' : '' }}>
                                                <option value="">-- Select customer --</option>
                                                @foreach(\Modules\People\Entities\Customer::where(function($q) use ($quotation){ $q->where('is_active', true)->orWhere('id', $quotation->customer_id); })->orderBy('customer_name','asc')->get() as $customer)
                                                    <option value="{{ $customer->id }}" {{ $quotation->customer_id == $customer->id ? 'selected' : '' }}>{{ $customer->customer_name }}{{ !$customer->is_active ? ' (Inactive)' : '' }}</option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div id="new-customer-block" style="{{ ($quotation->customer_type ?? 'existing') == 'new' ? '' : 'display:none;' }}">
                                            <input type="text" class="form-control" placeholder="New Customer Name" name="customer_name" id="customer_name" value="{{ old('customer_name', $quotation->customer_name) }}" {{ $isReadOnly ? 'disabled' : '' }}>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-lg-3">
                                    <div class="form-group">
                                        <div class="new-customer-field" style="display: {{ ($quotation->customer_type ?? 'existing') == 'new' ? 'block' : 'none' }};">
                                            <label for="contact_phone">Phone</label>
                                            <input type="text" class="form-control" name="contact_phone" id="contact_phone" maxlength="15" value="{{ old('contact_phone', $quotation->contact_phone) }}" placeholder="Contact Phone" oninput="validatePhone(this)" {{ $isReadOnly ? 'disabled' : '' }}>
                                            <small id="phone-error" class="text-danger" style="display: none;"></small>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-lg-3">
                                    <div class="form-group">
                                        <div class="new-customer-field" style="display: {{ ($quotation->customer_type ?? 'existing') == 'new' ? 'block' : 'none' }};">
                                            <label for="contact_email">Email</label>
                                            <input type="email" class="form-control" name="contact_email" id="contact_email" value="{{ old('contact_email', $quotation->contact_email) }}" placeholder="Contact Email" maxlength="50" oninput="this.value = this.value.replace(/[^a-zA-Z0-9@._]/g, '').slice(0,50); validateEmail(this);" {{ $isReadOnly ? 'disabled' : '' }}>
                                            <small id="email-error" class="text-danger" style="display: none;"></small>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-lg-3">
                                    <div class="form-group">
                                        <div class="new-customer-field" style="display: {{ ($quotation->customer_type ?? 'existing') == 'new' ? 'block' : 'none' }};">
                                            <label for="contact_address">Area</label>
                                            <input type="text" class="form-control" name="contact_address" id="contact_address" value="{{ old('contact_address', $quotation->contact_address) }}" placeholder="Contact Address / Area" {{ $isReadOnly ? 'disabled' : '' }}>
                                        </div>
                                    </div>
                                </div>

                            </div>

                            <div class="form-row mt-2 mb-3">
                                <div class="col-md-4 pr-1">
                                    <label for="vat_id_display" class="mb-1">VAT ID / TRN</label>
                                    <input type="text" class="form-control" id="vat_id_display" readonly
                                        value="{{ $quotation->customer_id ? ($quotation->customer->vat_id ?? '') : '' }}" placeholder="—">
                                </div>
                            </div>

                            {{-- Product search area styled like Sale module (placed below customer details) --}}
                            <!-- <div class="border p-3 mb-3"> -->
                                @unless($isReadOnly)
                                    <livewire:search-product/>
                                @endunless
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

                            <livewire:product-cart :cartInstance="$cartInstance ?? 'quotation'" :data="$quotation" :readonly="$isReadOnly"/>

                            {{-- Status removed from UI per requirement; replaced with Reduce Stock checkbox --}}
                            <div class="form-row">
                                <div class="col-lg-4">
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="note">Note (If Needed)</label>
                                <textarea name="note" id="note" rows="5" class="form-control" {{ $isReadOnly ? 'disabled' : '' }}>{{ $quotation->note }}</textarea>
                            </div>

                            {{-- Reduce stock checkbox placed after note --}}
                            <div class="form-group mt-2">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="reduce_stock" id="reduce_stock" value="1" style="transform: scale(1.3); margin-right: .5rem;" {{ ($quotation->reduce_stock ?? false) ? 'checked' : '' }} {{ $isReadOnly ? 'disabled' : '' }}>
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
                            {{-- No draft_id for edit page since we're editing existing quotation --}}

                            <div class="mt-3 d-flex justify-content-end">
                                <a href="{{ route('quotations.index') }}" class="btn btn-secondary mr-2">Back</a>
                                @unless($isReadOnly)
                                    <button type="submit" class="btn btn-primary">
                                        Update Quotation <i class="bi bi-check"></i>
                                    </button>
                                @endunless
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('page_scripts')
    <script src="{{ asset_v('js/validation.js') }}"></script>
    <script src="{{ asset_v('js/currency-input.js') }}"></script>
    <script>
        $(document).ready(function () {
            // Update hidden fields on page load
            updateHiddenFields();

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

        // Capture per-row Purchase Rate from DOM before submit (sale group has purchase_rate_{rowId} input).
        function collectSubmittedPurchaseRates(formId) {
            try {
                document.querySelectorAll('input[name^="submitted_purchase_rates"]').forEach(function(el) { el.remove(); });
                document.querySelectorAll('tr[id^="cart-row-"]').forEach(function(row) {
                    var rowId = row.dataset.rowId;
                    if (!rowId) return;
                    var hiddenInput = document.getElementById('purchase_rate_' + rowId + '_raw');
                    if (hiddenInput && hiddenInput.value !== '') {
                        var inp = document.createElement('input');
                        inp.type = 'hidden';
                        inp.name = 'submitted_purchase_rates[' + rowId + ']';
                        inp.value = hiddenInput.value;
                        document.getElementById(formId).appendChild(inp);
                    }
                });
            } catch (e) { /* ignore */ }
        }

        // Make function globally accessible
        window.updateHiddenFields = updateHiddenFields;
        window.collectSubmittedPurchaseRates = collectSubmittedPurchaseRates;
        // Ensure hidden fields are synced just before form submit
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('quotation-form');
            if (form) {
                form.addEventListener('submit', function () {
                    updateHiddenFields();
                    collectSubmittedPurchaseRates('quotation-form');
                });
            }

            // Initialize Select2 for customer dropdown in edit view
            $('.select2-customer').select2({
                placeholder: 'Search and select customer...',
                allowClear: true,
                width: '100%'
            });

            // On customer change, fetch defaults and set hidden discount + product-cart additional discount
            $('#customer_id').on('change', function () {
                var id = $(this).val();
                if (!id) return;
                var url = '/customers/' + id + '/json';
                $.get(url).done(function (res) {
                    $('#hidden_discount_percentage').val(res.cash_discount || 0);
                    // Show the selected customer's VAT ID / TRN (read-only, like Sale)
                    $('#vat_id_display').val(res.vat_id || '');
                    /* Disabled: do not auto-apply additional discount when selecting a customer.
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
                    if (res.shipping_amount !== undefined) {
                        $('#hidden_shipping_amount').val(res.shipping_amount);
                    }
                }).fail(function () {
                    $('#hidden_discount_percentage, #hidden_shipping_amount').val('0');
                    $('#vat_id_display').val('');
                });
            });
            // Toggle existing/new customer blocks in edit view (show/hide inline fields like create)
            function toggleCustomerBlocks() {
                var type = $('#customer_type').val();
                if (type === 'new') {
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

            // Ensure hidden fields are synced when form is submitted and ask confirmation before updating
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
        });
    </script>
@endpush

