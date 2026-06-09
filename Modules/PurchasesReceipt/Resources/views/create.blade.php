@extends('layouts.app')

@section('content')
<div class="container-fluid mb-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    @include('utils.alerts')
                    <h3>Create Purchases Receipt</h3>

                    <form method="POST" action="{{ route('purchases-receipts.store') }}" id="receipt-form">
                        @csrf

                        @if($errors->any())
                            <div class="alert alert-danger">
                                <strong>There were some problems with your input:</strong>
                                <ul class="mb-0">
                                    @foreach($errors->all() as $err)
                                        <li>{{ $err }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <div class="border p-3 mb-3">
                                                    <div class="row mb-3">
                                                        <div class="col-md-3">
                                                            <label>Reference No</label>
                                                            <input type="text" class="form-control" id="reference" name="reference" readonly placeholder="Auto-generated">
                                                        </div>
                                                        <div class="col-md-3">
                                                            <label>Receipt Date</label>
                                                            <input type="date" class="form-control" name="date" value="{{ old('date', date('Y-m-d')) }}" required>
    <script>function formatDateDmy(d){if(!d)return '';var p=d.split('-');return (p.length===3&&p[0].length===4)?(p[2]+'-'+p[1]+'-'+p[0]):d;}</script>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label>Supplier</label>
                                                            <select id="supplier-select" name="supplier_id" class="form-control" required>
                                                                @if(old('supplier_id'))
                                                                    {{-- preserve old selection until select2 loads --}}
                                                                    <option value="{{ old('supplier_id') }}" selected>Selected Supplier</option>
                                                                @endif
                                                            </select>
                                                        </div>
                                                    </div>

                                                    <div class="row mb-3">
                                                        <div class="col-md-3">
                                                            <label>Area</label>
                                                            <input type="text" id="area" class="form-control" readonly>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <label>Balance</label>
                                                            <input type="text" id="opening_balance" name="opening_balance" class="form-control" readonly>
                                                        </div>
                                                        <div class="col-md-2">
                                                            <label>Excess</label>
                                                            <input type="text" id="excess_amount" class="form-control" readonly>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label>Particular</label>
                                                            <input type="text" name="particular" class="form-control" maxlength="100"  value="{{ old('particular') }}">
                                                        </div>
                                                    </div>

                                                    <div class="row mb-3">
                                                        <div class="col-md-3">
                                                            <label>Receipt Amount <span class="text-danger">*</span></label>
                                                            <input type="text" id="receipt_amount" class="form-control currency-input" maxlength="15" required placeholder="0.00" value="{{ old('amount') }}" inputmode="decimal" data-target="#receipt_amount_raw">
                                                            <input type="hidden" name="amount" id="receipt_amount_raw" value="{{ old('amount') }}">
                                                        </div>
                                                        <div class="col-md-3">
                                                            <label>Receipt Balance <span class="text-danger">*</span></label>
                                                            <input type="text" id="receipt_balance" class="form-control" maxlength="15" readonly placeholder="0.00" inputmode="decimal">
                                                        </div>
                                                        <div class="col-md-3">
                                                            <label>Payment Mode <span class="text-danger">*</span></label>
                                                            <select name="payment_mode" id="payment_mode" class="form-control" required>
                                                                <option value="">-- Select Payment Mode --</option>
                                                                <option value="Cash" {{ old('payment_mode') == 'Cash' ? 'selected' : '' }}>Cash</option>
                                                                <option value="Cheque" {{ old('payment_mode') == 'Cheque' ? 'selected' : '' }}>Cheque</option>
                                                                <option value="Cards" {{ old('payment_mode') == 'Cards' ? 'selected' : '' }}>Cards</option>
                                                                <option value="Bank Transfer" {{ old('payment_mode') == 'Bank Transfer' ? 'selected' : '' }}>Bank Transfer</option>
                                                                <option value="UPI Payments" {{ old('payment_mode') == 'UPI Payments' ? 'selected' : '' }}>UPI Payments</option>
                                                                <option value="Product return" {{ old('payment_mode') == 'Product return' ? 'selected' : '' }}>Product return</option>
                                                            </select>
                                                        </div>
                                                        <div class="col-md-3"></div>
                                                    </div>

                                                    <div class="row mb-3">
                                                        <div class="col-md-12">
                                                            <div id="apply_to_opening_container" style="display:none">
                                                                <div class="form-check">
                                                                    <input class="form-check-input" type="checkbox" name="apply_to_opening" id="apply_to_opening" value="1" {{ old('apply_to_opening') ? 'checked' : '' }}>
                                                                    <label class="form-check-label" for="apply_to_opening">Apply to Opening Balance (use when no bills)</label>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    {{-- Bills will be listed here when a supplier is selected; user can add rows to the receipt table --}}
                                                    <div class="mb-3">
                                                        <label>Available Bills</label>
                                                           <div id="available-bills">
                                                               <div class="available-bills-scroll" style="max-height:350px; overflow:auto;">
                                                                   <div class="text-center loading-spinner" style="display:none;padding:8px 0">
                                                                       <div class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></div>
                                                                       <small class="ml-2">Loading bills...</small>
                                                                   </div>
                                                                   <table class="table table-sm table-striped mb-0" id="available-bills-table">
                                                                    <thead style="position:sticky;top:0;z-index:2;background:#fff">
                                                                        <tr>
                                                                            <th>Bill Ref No</th>
                                                                            <th>Bill Date</th>
                                                                                <th>Bill Amount</th>
                                                                                <th>Paid Amount</th>
                                                                                <th>Balance Amount</th>
                                                                            <th></th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody></tbody>
                                                                   </table>
                                                               </div>
                                                           </div>
                                                    </div>
                                                </div>

        <table class="table table-bordered" id="bills-table">
            <thead>
                <tr>
                    <th>Bill Ref No</th>
                    <th>Bill Date</th>
                    <th>Bill Amount</th>
                    <th>Paid Amount</th>
                    <th>Balance Amount</th>
                    <th>Payment Amount</th>
                    <th>Discount Amount</th>
                    <th>Final Balance</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @if(old('lines'))
                    @foreach(old('lines') as $i => $oline)
                        <tr>
                            <td>{{ $oline['bill_ref'] ?? '' }}</td>
                            <td>{{ !empty($oline['bill_date']) ? \Carbon\Carbon::parse($oline['bill_date'])->format('d-m-Y') : '' }}</td>
                            <td class="bill-amount">{{ number_format($oline['bill_amount'] ?? 0, 2, '.', '') }}</td>
                            <td class="paid-before">{{ number_format($oline['paid_before'] ?? 0, 2, '.', '') }}</td>
                            <td class="balance-before">{{ number_format($oline['balance_before'] ?? 0, 2, '.', '') }}</td>
                                <td>
                                <input type="text" id="line_{{ $i }}_payment_display" class="form-control currency-input payment-amount-display" data-target="#line_{{ $i }}_payment_raw" value="{{ number_format($oline['payment_amount'] ?? 0, 2, '.', '') }}" placeholder="0.00" maxlength="15" aria-label="Payment amount">
                                <input type="hidden" name="lines[{{ $i }}][payment_amount]" id="line_{{ $i }}_payment_raw" class="payment-amount" value="{{ number_format($oline['payment_amount'] ?? 0, 2, '.', '') }}">
                                @if($errors->has("lines.$i.payment_amount"))
                                    <small class="text-danger">{{ $errors->first("lines.$i.payment_amount") }}</small>
                                @endif
                            </td>
                                <td>
                                <input type="text" id="line_{{ $i }}_discount_display" class="form-control currency-input discount-amount-display" data-target="#line_{{ $i }}_discount_raw" value="{{ number_format($oline['discount_amount'] ?? 0, 2, '.', '') }}" placeholder="0.00" maxlength="15" aria-label="Discount amount">
                                <input type="hidden" name="lines[{{ $i }}][discount_amount]" id="line_{{ $i }}_discount_raw" class="discount-amount" value="{{ number_format($oline['discount_amount'] ?? 0, 2, '.', '') }}">
                                @if($errors->has("lines.$i.discount_amount"))
                                    <small class="text-danger">{{ $errors->first("lines.$i.discount_amount") }}</small>
                                @endif
                            </td>
                            <td class="final-balance">{{ number_format($oline['final_balance'] ?? 0, 2, '.', '') }}</td>
                            <td><button type="button" class="btn btn-sm btn-danger remove-row">Remove</button></td>
                            <input type="hidden" name="lines[{{ $i }}][purchase_id]" value="{{ $oline['purchase_id'] ?? '' }}">
                            <input type="hidden" name="lines[{{ $i }}][is_settled]" value="{{ (isset($oline['is_settled']) && $oline['is_settled']) ? '1' : '0' }}" class="settled-hidden">
                        </tr>
                    @endforeach
                @endif
            </tbody>
        </table>

                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="global_settled" readonly>
                                                    <label class="form-check-label" for="global_settled">Settlement Status (Auto-calculated)</label>
                                                </div>
                                            </div>
                                            <div>
                                                <a href="{{ route('purchases-receipts.index') }}" class="btn btn-secondary mr-2">Back</a>
                                                <button class="btn btn-primary" type="submit">Save Receipt</button>
                                            </div>
                                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

@push('page_scripts')
    <script src="{{ asset('js/currency-input.js') }}"></script>
<script>
$(function(){
    // populate supplier select via AJAX (simple select2 if available)
    console.debug('init: supplier-select script running');

    // in-memory map of available bills returned for current supplier
    var availableBillsMap = {};

    // small helper to parse numeric values robustly
    function parseNumber(v) {
        if (v === null || v === undefined) return 0;
        var s = (typeof v === 'string') ? v : String(v);
        s = s.replace(/[^0-9\.\-]/g, '');
        if (s === '') return 0;
        var n = parseFloat(s);
        return (isNaN(n) ? 0 : n);
    }

    // Format for visible display: remove unnecessary trailing .00 when whole number
    function formatDisplayNoTrailing(v) {
        if (v === null || v === undefined || v === '') return '';
        var num = parseFloat(v);
        if (isNaN(num)) return v;
        if (Math.abs(num - Math.round(num)) < 0.005) return String(Math.round(num));
        return num.toFixed(2).replace(/\.00$/, '').replace(/(\.\d)0$/, '$1');
    }

    // quick debug ping to see if API is reachable from the page (use safe endpoint)
    $.getJSON('{{ route('api.suppliers.search') }}', { q: '' })
        .done(function(resp) { console.debug('debug: /api/suppliers/search ok', resp); })
        .fail(function(xhr) { console.warn('debug: /api/suppliers/search failed', xhr.status, xhr.responseText); });

    // initialize currency inputs on page load
    try { if (window.currencyInputInit) window.currencyInputInit(); } catch(e) {}

    $('#supplier-select').select2({
        ajax: {
            url: '{{ route('api.suppliers.search') }}',
            dataType: 'json',
            delay: 250,
            data: function(params) {
                return { q: params.term || '' };
            },
            processResults: function(data) {
                return { results: data.results || [] };
            },
            cache: true
        },
        minimumInputLength: 0, // allow opening without typing
        placeholder: 'Search and select supplier...',
        allowClear: true,
        width: '100%'
    });

    // currency-input.js already included above

    // log when Select2 opens (helps confirm the widget initialized)
    $('#supplier-select').on('select2:opening select2:open', function(){ console.debug('select2: opening for #supplier-select'); });

    $('#supplier-select').on('select2:select', function(e){
        var id = e.params.data.id;

        // clear previously listed available bills and show spinner
        $('#available-bills-table tbody').empty();
        availableBillsMap = {};
        $('#available-bills .loading-spinner').show();

        var supReq = $.get('{{ url('api/suppliers') }}/' + id);
        var billsReq = $.get('{{ route('purchasesreceipts.purchases.search') }}', { supplier_id: id });

        $.when(supReq, billsReq).done(function(supResp, billsResp){
            var resp = supResp[0];
            var res = billsResp[0];

            $('#area').val(resp.area || '');
            $('#opening_balance').val(resp.open_balance_formatted || resp.open_balance || 0);
            $('#excess_amount').val(resp.excess_amount_formatted || resp.excess_amount || 0);

            var results = res.results || [];
            if (results.length) {
                results.forEach(function(p){
                    p.due_amount = p.due_amount !== undefined ? p.due_amount : p.balance;
                    p.paid_amount = (p.paid_amount !== undefined) ? p.paid_amount : (p.paid !== undefined ? p.paid : '');
                    availableBillsMap[p.id] = p;
                    var $tr = $('<tr>').attr('data-id', p.id);
                    $tr.append($('<td>').text(p.reference || p.ref || ''));
                    $tr.append($('<td>').text(formatDateDmy(p.date) || ''));
                    $tr.append($('<td>').text(p.total_amount || p.bill_amount || ''));
                    $tr.append($('<td>').text(p.paid_amount || ''));
                    $tr.append($('<td>').text(p.due_amount || ''));
                    var $btn = $('<button>', { type: 'button', 'class': 'btn btn-sm btn-primary add-bill', 'data-id': p.id }).text('Add');
                    $tr.append($('<td>').append($btn));
                    $('#available-bills-table tbody').append($tr);
                });
            } else {
                var $tr = $('<tr>');
                $tr.append($('<td>', { colspan: 6, 'class': 'text-center text-muted py-3' }).text('No bills available for this supplier'));
                $('#available-bills-table tbody').append($tr);
            }

            if ((results || []).length === 0 && parseFloat($('#opening_balance').val() || '0') > 0) {
                $('#apply_to_opening_container').show();
            } else {
                $('#apply_to_opening_container').hide();
                $('#apply_to_opening').prop('checked', false);
                $('#bills-table tbody tr.opening-row').remove();
                updateSettlementStatus();
            }
        }).fail(function(){
            console.warn('Failed to load supplier details or bills for supplier', id);
        }).always(function(){
            $('#available-bills .loading-spinner').hide();
        });
    });

    // hide apply-to-opening until supplier selected and bills loaded
    $('#apply_to_opening_container').hide();

    // when supplier cleared, hide the apply-to-opening control and remove opening rows
    $('#supplier-select').on('select2:clear', function(){
        $('#apply_to_opening_container').hide();
        $('#apply_to_opening').prop('checked', false);
        $('#bills-table tbody tr.opening-row').remove();
        updateSettlementStatus();
    });

    // purchases will be loaded automatically on supplier selection

    function addBillRow(p) {
        // prevent duplicate purchase rows (check by purchase id value in hidden inputs)
        if ($('#bills-table tbody input[type=hidden][value="' + p.id + '"]').length) {
            // already added
            return;
        }

        var tr = $('<tr>');
        var idx = $('#bills-table tbody tr').length; // use current count as index to create ordered names
        tr.append($('<td>').text(p.reference || p.ref || ''));
        tr.append($('<td>').text(formatDateDmy(p.date)));
        tr.append($('<td class="bill-amount">').text(p.total_amount));
        tr.append($('<td class="paid-before">').text(p.paid_amount));
        tr.append($('<td class="balance-before">').text(p.due_amount));

        // payment amount: visible formatted display + hidden raw numeric field (preserve server name)
        var payDisplayId = 'lines_' + idx + '_payment_display';
        var payRawId = 'lines_' + idx + '_payment_raw';
        var $payDisplay = $('<input>', { type: 'text', id: payDisplayId, 'class': 'form-control currency-input payment-amount-display', inputmode: 'decimal', maxlength: 15, placeholder: '0.00', 'data-target': '#' + payRawId, value: '' });
        var $payRaw = $('<input>', { type: 'hidden', name: 'lines[' + idx + '][payment_amount]', id: payRawId, 'class': 'payment-amount', value: '' });
        tr.append($('<td>').append($payDisplay).append($payRaw));

        // discount amount: visible formatted display + hidden raw numeric field
        var discDisplayId = 'lines_' + idx + '_discount_display';
        var discRawId = 'lines_' + idx + '_discount_raw';
        var $discDisplay = $('<input>', { type: 'text', id: discDisplayId, 'class': 'form-control currency-input discount-amount-display', inputmode: 'decimal', maxlength: 15, placeholder: '0.00', 'data-target': '#' + discRawId, value: (p.discount_amount !== undefined ? parseFloat(p.discount_amount).toFixed(2) : '0.00') });
        var $discRaw = $('<input>', { type: 'hidden', name: 'lines[' + idx + '][discount_amount]', id: discRawId, 'class': 'discount-amount', value: (p.discount_amount !== undefined ? parseFloat(p.discount_amount).toFixed(2) : '0.00') });
        tr.append($('<td>').append($discDisplay).append($discRaw));

        tr.append($('<td class="final-balance">').text(p.due_amount));

        // settled hidden input - will be set automatically based on amount calculation
        var hiddenSettled = $('<input>', { type: 'hidden', name: 'lines[' + idx + '][is_settled]', value: '0', 'class': 'settled-hidden' });
        tr.append(hiddenSettled);

        tr.append($('<td>').html('<button type="button" class="btn btn-sm btn-danger remove-row">Remove</button>'));

        // store hidden purchase_id with indexed name
        var hidden = $('<input>', { type: 'hidden', name: 'lines[' + idx + '][purchase_id]' }).val(p.id);
        tr.append(hidden);

        $('#bills-table tbody').append(tr);

        // if available bills table has a button for this id, disable it / mark as Added
        var $availBtn = $('#available-bills-table').find('button.add-bill[data-id="' + p.id + '"]');
        if ($availBtn.length) {
            $availBtn.prop('disabled', true).text('Added').removeClass('btn-primary').addClass('btn-secondary');
        }

        // initialize currency bindings for newly appended displays, trigger updates and focus display
        try { if (window.currencyInputInit) window.currencyInputInit(); } catch(e) {}
        var $newRow = $('#bills-table tbody tr').last();
        $newRow.find('.payment-amount-display').trigger('input');
        $newRow.find('.discount-amount-display').trigger('input');
        try { $('#' + payDisplayId).focus(); } catch(e) {}
        updateSettlementStatus();
    }

    // Ensure an opening-balance synthetic row is present when the checkbox is checked and there are no bill rows
    function ensureOpeningRow() {
        var rows = $('#bills-table tbody tr').length || 0;
            if ($('#apply_to_opening').is(':checked') && rows === 0) {
            var idx = 0;
            var openingBal = parseNumber($('#opening_balance').val() || '0') || 0;
            var receiptAmt = parseNumber($('#receipt_amount_raw').length ? $('#receipt_amount_raw').val() : $('#receipt_amount').val() || '0') || 0;
            var tr = $('<tr>').addClass('opening-row');
            tr.append($('<td>').text('Opening Balance'));
            tr.append($('<td>').text('-'));
            // For opening balance synthetic row, bill amount should be zero
            tr.append($('<td class="bill-amount">').text((0).toFixed(2)));
            tr.append($('<td class="paid-before">').text('0.00'));
            tr.append($('<td class="balance-before">').text(openingBal.toFixed(2)));
            // payment amount: currency display + hidden raw
            var payDispId = 'lines_'+idx+'_payment_display';
            var payRawId = 'lines_'+idx+'_payment_raw';
            // Do not auto-fill payment amount — leave blank for manual allocation
            var paymentDisplay = $('<input>', { type: 'text', id: payDispId, 'class': 'form-control currency-input payment-amount-display', 'data-target': '#'+payRawId, inputmode: 'decimal', maxlength: 15, value: '', placeholder: '0.00' });
            var paymentRaw = $('<input>', { type: 'hidden', id: payRawId, name: 'lines['+idx+'][payment_amount]', 'class': 'payment-amount' }).val('');
            tr.append($('<td>').append(paymentDisplay).append(paymentRaw));
            // discount amount: currency display + hidden raw
            var discDispId = 'lines_'+idx+'_discount_display';
            var discRawId = 'lines_'+idx+'_discount_raw';
            var discountDisplay = $('<input>', { type: 'text', id: discDispId, 'class': 'form-control currency-input discount-amount-display', 'data-target': '#'+discRawId, inputmode: 'decimal', maxlength: 15, value: '0.00', placeholder: '0.00' });
            var discountRaw = $('<input>', { type: 'hidden', id: discRawId, name: 'lines['+idx+'][discount_amount]', 'class': 'discount-amount' }).val('0.00');
            tr.append($('<td>').append(discountDisplay).append(discountRaw));
            // Final balance for opening-row is displayed as zero (it's not a bill)
            tr.append($('<td class="final-balance">').text((0).toFixed(2)));
            tr.append($('<td>').html('<button type="button" class="btn btn-sm btn-danger remove-row">Remove</button>'));
            tr.append($('<input>', { type: 'hidden', name: 'lines['+idx+'][purchase_id]' }).val(''));
            $('#bills-table tbody').append(tr);
            try { if (window.currencyInputInit) window.currencyInputInit(); } catch(e) {}
            updateSettlementStatus();
        } else {
            // remove synthetic opening row if present and checkbox unchecked or bills exist
            if (!$('#apply_to_opening').is(':checked') || ($('#bills-table tbody tr').length || 0) > 0) {
                $('#bills-table tbody tr.opening-row').remove();
                updateSettlementStatus();
            }
        }
    }

    // react to checkbox and receipt amount changes
    $('#apply_to_opening').on('change', function(){
        ensureOpeningRow();
    });
    // react to hidden raw receipt value changes (currency-input will update this)
    $('#receipt_amount_raw').on('input change blur', function(){
        // When receipt amount changes, recalculate settlement only.
        updateSettlementStatus();
    });

    // delegate events for visible payment/discount displays so final-balance uses balance-before
    $('#bills-table').on('input', '.payment-amount-display, .discount-amount-display', function(){
        var row = $(this).closest('tr');
        var balanceBefore = parseFloat(row.find('.balance-before').text()) || 0;
        var pay = parseFloat(row.find('.payment-amount').val()) || 0;
        var disc = parseFloat(row.find('.discount-amount').val()) || 0;
        var finalBal = balanceBefore - (pay + disc);
        row.find('.final-balance').text(finalBal.toFixed(2));
        updateSettlementStatus();
    });

    $('#bills-table').on('click', '.remove-row', function(){
        var $tr = $(this).closest('tr');
        // re-enable Add button in available list if present
        var purchaseId = $tr.find('input[type=hidden][name$="[purchase_id]"]').val();
        $tr.remove();
        if (purchaseId) {
            var $btn = $('#available-bills-table').find('button.add-bill[data-id="' + purchaseId + '"]');
            if ($btn.length) {
                $btn.prop('disabled', false).text('Add').removeClass('btn-secondary').addClass('btn-primary');
            }
        }
        
        // Update settlement status after removing row
        updateSettlementStatus();
    });

    // Input constraints:
    // - Particular: allow only alphanumeric, spaces and hyphen
    // - Amount / Payment Amount / Discount Amount: integers or decimals
    $('#receipt-form').on('input', 'input[name="particular"]', function(){
        var v = $(this).val() || '';
        var clean = v.replace(/[^A-Za-z0-9 \-]/g, '');
        if (v !== clean) $(this).val(clean);
    });

    // sanitize numeric inputs on-the-fly (operate on hidden raw fields and per-line hidden inputs)
    $('#receipt-form').on('input', '#receipt_amount_raw, .payment-amount, .discount-amount', function(){
        var v = $(this).val() || '';
        var clean = v.replace(/[^0-9\.\-]/g, '');
        var parts = clean.split('.');
        if (parts.length > 2) clean = parts.shift() + '.' + parts.join('');
        // enforce maxlength if present (character count including dot)
        var max = parseInt($(this).attr('maxlength') || '15', 10) || 15;
        if (clean.length > max) clean = clean.substring(0, max);
        $(this).val(clean);
    });

    // format hidden numeric fields to 2 decimals on blur (payment/discount and receipt raw)
    $('#receipt-form').on('blur', '#receipt_amount_raw, .payment-amount, .discount-amount', function(){
        var v = ($(this).val() || '').toString();
        v = v.replace(/[^0-9\.\-]/g, '');
        if (v === '') return;
        var n = parseFloat(v);
        if (isNaN(n)) n = 0;
        var s = n.toFixed(2);
        var max = parseInt($(this).attr('maxlength') || '15', 10) || 15;
        if (s.length > max) s = s.substring(0, max);
        $(this).val(s);
    });
        // allow submitting without bill rows when apply_to_opening is checked
        $('#receipt-form').off('submit.apply_to_opening').on('submit.apply_to_opening', function(e){
            var rows = $('#bills-table tbody tr').length || 0;
            if (rows === 0 && !$('#apply_to_opening').is(':checked')) {
                e.preventDefault();
                alert('Select at least one bill or check "Apply to Opening Balance" before saving.');
                return false;
            }
        });

    // client-side validation + numeric sanitizers before submit
    $('#receipt-form').on('submit', function(e){
        e.preventDefault();
        // remove any previous client-side/server-side alert
        $('#receipt-form').find('.client-validation-alert, .server-validation-alert').remove();

        var firstInvalid = null;

        // validate receipt amount and payment mode first
        var amtRaw = ($('#receipt_amount_raw').length ? $('#receipt_amount_raw').val() : $('#receipt_amount').val()) || '';
        var amtNum = parseNumber(amtRaw || '0');
        if (!amtNum || amtNum <= 0) {
            firstInvalid = firstInvalid || $('#receipt_amount');
            $('#receipt_amount').addClass('is-invalid');
        } else {
            $('#receipt_amount').removeClass('is-invalid');
        }

        var paymode = ($('#payment_mode').val() || '').toString();
        if (!paymode) {
            firstInvalid = firstInvalid || $('#payment_mode');
            $('#payment_mode').addClass('is-invalid');
        } else {
            $('#payment_mode').removeClass('is-invalid');
        }

        if (firstInvalid) {
            var $alert = $('<div class="alert alert-danger client-validation-alert">Please fill required fields: Amount and Payment Mode.</div>');
            $('#receipt-form').prepend($alert);
            $('html,body').animate({ scrollTop: firstInvalid.offset().top - 100 }, 200);
            firstInvalid.focus();
            return false;
        }

        // Validate each payment-amount in the bills table
        $('#bills-table tbody tr').each(function(){
            var $row = $(this);
            var $payment = $row.find('.payment-amount');
            if ($payment.length) {
                var raw = ($payment.val() || '').toString();
                var num = parseNumber(raw || '0');
                if (!num || num <= 0) {
                    $row.addClass('table-warning');
                    $payment.addClass('is-invalid');
                    if (!firstInvalid) firstInvalid = $payment;
                } else {
                    $row.removeClass('table-warning');
                    $payment.removeClass('is-invalid');
                }
            }
        });

        if (firstInvalid) {
            var $alert = $('<div class="alert alert-danger client-validation-alert">Please provide a payment amount for each selected bill before submitting.</div>');
            $('#receipt-form').prepend($alert);
            $('html,body').animate({ scrollTop: firstInvalid.offset().top - 100 }, 200);
            firstInvalid.focus();
            return false;
        }

        // sanitize numeric inputs (format to 2 decimals) on hidden raw fields
        $('.payment-amount, .discount-amount, #receipt_amount_raw').each(function(){
            var v = $(this).val();
            v = v.replace(/[^0-9\.\-]/g, '');
            var num = parseFloat(v || 0);
            if (isNaN(num)) num = 0;
            $(this).val(num.toFixed(2));
        });

        // perform AJAX submit to avoid full page refresh on server validation errors
        var $form = $(this);
        var url = $form.attr('action');
        // ensure per-line settled flags reflect the global checkbox before serializing
        $('.settled-hidden').val($('#global_settled').is(':checked') ? '1' : '0');
        var data = $form.serialize();

        $.ajax({
            url: url,
            method: 'POST',
            data: data,
            dataType: 'json',
            headers: { 'Accept': 'application/json' }
        }).done(function(resp){
            // success — redirect to index (controller normally redirects)
            window.location = '{{ route('purchases-receipts.index') }}';
        }).fail(function(xhr){
            if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                // validation errors from server — render them inline without reloading
                var errors = xhr.responseJSON.errors;
                var $list = $('<ul class="mb-0"></ul>');
                Object.keys(errors).forEach(function(key){
                    errors[key].forEach(function(msg){
                        $list.append($('<li>').text(msg));
                        // try to highlight the offending field
                        try {
                            var sel = key.replace(/\./g, '][').replace(/\*/g, '');
                            var $input = $form.find('[name="' + key + '"]').length ? $form.find('[name="' + key + '"]') : $form.find('[name="' + sel + '"]');
                            if ($input && $input.length) {
                                $input.addClass('is-invalid');
                                if (!firstInvalid) firstInvalid = $input.first();
                            }
                        } catch (e) {
                            // ignore selector errors
                        }
                    });
                });

                var $alert = $('<div class="alert alert-danger server-validation-alert"><strong>There were some problems with your input:</strong></div>');
                $alert.append($list);
                $('#receipt-form').prepend($alert);
                if (firstInvalid) {
                    $('html,body').animate({ scrollTop: firstInvalid.offset().top - 100 }, 200);
                    firstInvalid.focus();
                }
            } else {
                // unexpected error — show generic message
                var msg = 'An unexpected error occurred. Please try again.';
                if (xhr.responseText) {
                    try { msg = JSON.parse(xhr.responseText).message || msg; } catch(e) {}
                }
                var $alert = $('<div class="alert alert-danger server-validation-alert"></div>').text(msg);
                $('#receipt-form').prepend($alert);
            }
        });
    });

    // handler for Add button on available bills
    $('#available-bills-table').on('click', 'button.add-bill', function(){
        var id = $(this).data('id');
        var p = availableBillsMap[id];
        if (!p) return;
        addBillRow(p);
    });

    // Function to calculate and update settlement status based on amount allocation
    function updateSettlementStatus() {
        var receiptAmt = parseNumber($('#receipt_amount_raw').length ? $('#receipt_amount_raw').val() : $('#receipt_amount').val());
        var totalAllocated = 0;
        var invalidAllocation = false;
        var $rows = $('#bills-table tbody tr');

        $rows.each(function(){
            var $row = $(this);
            var p = parseFloat($row.find('.payment-amount').val()) || 0;
            var d = parseFloat($row.find('.discount-amount').val()) || 0;
            var balanceBefore = parseFloat($row.find('.balance-before').text()) || 0;
            var finalBal = balanceBefore - (p + d);
            $row.find('.final-balance').text(finalBal.toFixed(2));
            // Both payment and discount reduce the receipt's available amount; discounts are part of allocation.
            totalAllocated += (p + d);
            if ((p + d) > receiptAmt + 0.0001) { invalidAllocation = true; $row.addClass('table-danger'); } else { $row.removeClass('table-danger'); }
        });

        var receiptBalance = receiptAmt - totalAllocated;
        $('#receipt_balance').val((isNaN(receiptBalance) ? 0 : receiptBalance).toFixed(2));

        var isSettled = (receiptAmt > 0) && (Math.abs(receiptAmt - totalAllocated) < 0.01);
        $('#global_settled').prop('checked', isSettled);
        $('.settled-hidden').val(isSettled ? '1' : '0');

        var settledText = isSettled ? 'Settled (Amount properly distributed)' : 'Not Settled (Amount mismatch)';
        var settledClass = isSettled ? 'text-success' : 'text-warning';
        if (!$('#settlement-status').length) { $('#global_settled').parent().after('<div id="settlement-status" class="small"></div>'); }
        $('#settlement-status').text(settledText).removeClass('text-success text-warning').addClass(settledClass);

        var $submit = $('#receipt-form').find('button[type=submit]').first();
        if (invalidAllocation || totalAllocated > receiptAmt + 0.0001) {
            if (!$('#payment-warning').length) {
                $('#receipt-form').prepend('<div id="payment-warning" class="alert alert-warning">Allocated amounts exceed available receipt amount. Adjust Receipt Amount or Reduce Payment Amounts.</div>');
            }
            try { $submit.prop('disabled', true); } catch(e) {}
        } else {
            $('#payment-warning').remove();
            try { $submit.prop('disabled', false); } catch(e) {}
        }

        return isSettled;
    }

    // Update settlement status when receipt amount changes
    $('#receipt_amount').on('input blur', function(){
        updateSettlementStatus();
    });

    // Disable manual checkbox toggle - settlement is now calculated automatically
    $('#global_settled').on('click', function(e){
        e.preventDefault();
        // Show message explaining the new behavior
        if (!$('#auto-settlement-notice').length) {
            $(this).parent().after('<div id="auto-settlement-notice" class="small text-info mt-1">Settlement is automatically calculated based on amount distribution</div>');
            setTimeout(function() {
                $('#auto-settlement-notice').fadeOut();
            }, 3000);
        }
        return false;
    });

    // Prevent double submission
    $('#receipt-form').on('submit', function() {
        $(this).find('button[type="submit"]').prop('disabled', true).text('Saving...');
    });
});
</script>
@endpush

@endsection