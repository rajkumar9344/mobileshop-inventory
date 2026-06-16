@extends('layouts.app')

@section('content')
<div class="container-fluid mb-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    @include('utils.alerts')
                    @php
                        $isReadOnly = !empty($readonly);
                        $totalAllocated = 0;
                        // Use only payment amounts to compute how much of the receipt is allocated.
                        // Discounts do not reduce the receipt allocation (they affect per-line final balance only).
                        foreach($receipt->lines as $line) { $totalAllocated += (float)($line->payment_amount ?? 0); }
                        $receiptBalance = (float)($receipt->total_amount ?? 0) - $totalAllocated;
                        // determine opening balance display value – prefer stored balance_before when available
                        $displayOpeningBalance = old('opening_balance');
                        if ($displayOpeningBalance === null) {
                            if (isset($receipt->customer_balance_before) && $receipt->customer_balance_before !== null) {
                                $displayOpeningBalance = number_format($receipt->customer_balance_before/100, 2, '.', '');
                            } else {
                                $displayOpeningBalance = optional($receipt->customer)->opening_balance_formatted ?? optional($receipt->customer)->opening_balance ?? 0;
                            }
                        }
                        // Bill / Total balance: prefer the snapshot frozen at creation; fall back to
                        // the customer's live balance for older receipts saved before snapshots existed.
                        $openNum = (float) str_replace(',', '', (string) $displayOpeningBalance);
                        $billNum = (isset($receipt->bill_balance_before) && $receipt->bill_balance_before !== null)
                            ? ($receipt->bill_balance_before / 100)
                            : (float) (optional($receipt->customer)->bill_balance ?? 0);
                        $displayBillBalance = number_format($billNum, 2, '.', '');
                        $displayTotalBalance = number_format($openNum + $billNum, 2, '.', '');
                    @endphp
                    <h3>{{ $isReadOnly ? 'View Sales Receipt' : 'Edit Sales Receipt' }}</h3>

                    <form method="POST" action="{{ route('sales-receipts.update', $receipt->id) }}" id="receipt-form">
                        @csrf
                        @method('PATCH')

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
                                    <input type="text" class="form-control" id="reference" name="reference" readonly placeholder="Auto-generated" value="{{ $receipt->reference }}">
                                </div>
                                <div class="col-md-3">
                                    <label>Receipt Date</label>
                                    <input type="date" class="form-control" name="date" value="{{ old('date', $receipt->date) }}" {{ $isReadOnly ? 'disabled' : 'required' }}>
    <script>function formatDateDmy(d){if(!d)return '';var p=d.split('-');return (p.length===3&&p[0].length===4)?(p[2]+'-'+p[1]+'-'+p[0]):d;}</script>
                                </div>
                                <div class="col-md-6">
                                    <label>Customer</label>
                                    @if($isReadOnly)
                                        <input type="text" class="form-control" readonly value="{{ optional($receipt->customer)->customer_name ?? 'Selected Customer' }}">
                                    @else
                                        <select id="customer-select" name="customer_id" class="form-control" required>
                                            {{-- preserve existing selection until select2 loads --}}
                                            <option value="{{ old('customer_id', $receipt->customer_id) }}" selected>{{ old('customer_name', optional($receipt->customer)->customer_name ?? 'Selected Customer') }}</option>
                                        </select>
                                    @endif
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-3">
                                    <label>Area</label>
                                    <input type="text" id="area" class="form-control" readonly value="{{ optional($receipt->customer)->area ?? '' }}">
                                </div>
                                <div class="col-md-3">
                                    <label>Open Balance</label>
                                    <input type="text" id="opening_balance" name="opening_balance" class="form-control" readonly value="{{ $displayOpeningBalance }}">
                                </div>
                                <div class="col-md-3">
                                    <label>Bill Balance</label>
                                    <input type="text" id="bill_balance_display" class="form-control" readonly value="{{ $receipt->customer_id ? $displayBillBalance : '0.00' }}">
                                </div>
                                <div class="col-md-3">
                                    <label>Total Balance</label>
                                    <input type="text" id="total_balance_display" class="form-control" readonly value="{{ $receipt->customer_id ? $displayTotalBalance : '0.00' }}">
                                </div>
                                <div class="col-md-3">
                                    <label>Excess Amount</label>
                                    <input type="text" id="excess_amount_display" class="form-control" readonly value="{{ optional($receipt->customer)->excess_amount !== null ? number_format(optional($receipt->customer)->excess_amount, 2, '.', '') : '0.00' }}">
                                    <input type="hidden" id="excess_amount" name="excess_amount" value="{{ optional($receipt->customer)->excess_amount ?? 0 }}">
                                </div>
                                <div class="col-md-3">
                                    <label>Particular</label>
                                    <input type="text" name="particular" class="form-control" maxlength="100" value="{{ old('particular', $receipt->particular) }}" {{ $isReadOnly ? 'disabled' : '' }}>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-3">
                                    <label>Receipt Amount <span class="text-danger">*</span></label>
                                    {{-- visible formatted input (not submitted) --}}
                                    <input type="text" id="receipt_amount" class="form-control currency-input" maxlength="15" {{ $isReadOnly ? 'disabled' : 'required' }} placeholder="0.00" value="{{ old('amount', number_format($receipt->total_amount ?? 0, 2, '.', '')) }}" inputmode="decimal" data-target="#receipt_amount_raw">
                                    {{-- hidden raw value preserves server name --}}
                                    <input type="hidden" name="amount" id="receipt_amount_raw" value="{{ old('amount', number_format($receipt->total_amount ?? 0, 2, '.', '')) }}">
                                </div>
                                <div class="col-md-3">
                                    <label>Receipt Balance <span class="text-danger">*</span></label>
                                    <input type="text" id="receipt_balance" class="form-control" maxlength="15" readonly placeholder="0.00" value="{{ $isReadOnly ? number_format($receiptBalance, 2, '.', '') : '' }}" inputmode="decimal">
                                </div>
                                <div class="col-md-3">
                                    <label>Payment Mode <span class="text-danger">*</span></label>
                                    <select name="payment_mode" id="payment_mode" class="form-control" {{ $isReadOnly ? 'disabled' : 'required' }}>
                                        <option value="">-- Select Payment Mode --</option>
                                        <option value="Cash" {{ (old('payment_mode', $receipt->payment_mode) == 'Cash') ? 'selected' : '' }}>Cash</option>
                                        <option value="Cheque" {{ (old('payment_mode', $receipt->payment_mode) == 'Cheque') ? 'selected' : '' }}>Cheque</option>
                                        <option value="Cards" {{ (old('payment_mode', $receipt->payment_mode) == 'Cards') ? 'selected' : '' }}>Cards</option>
                                        <option value="Bank Transfer" {{ (old('payment_mode', $receipt->payment_mode) == 'Bank Transfer') ? 'selected' : '' }}>Bank Transfer</option>
                                        <option value="UPI Payment" {{ (old('payment_mode', $receipt->payment_mode) == 'UPI Payment') ? 'selected' : '' }}>UPI Payment</option>
                                        <option value="Product return" {{ (old('payment_mode', $receipt->payment_mode) == 'Product return') ? 'selected' : '' }}>Product return</option>
                                    </select>
                                </div>
                                <div class="col-md-3"></div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-12">
                                    @if($isReadOnly)
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="apply_to_opening" id="apply_to_opening" value="1" {{ old('apply_to_opening') ? 'checked' : ((isset($receipt) && ($receipt->applied_to_customer ?? 0) > 0) ? 'checked' : '') }} disabled>
                                            <label class="form-check-label" for="apply_to_opening">Apply to Open Balance</label>
                                        </div>
                                    @else
                                        <div id="apply_to_opening_container" style="display:none">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="apply_to_opening" id="apply_to_opening" value="1" {{ old('apply_to_opening') ? 'checked' : ((isset($receipt) && ($receipt->applied_to_customer ?? 0) > 0) ? 'checked' : '') }}>
                                                <label class="form-check-label" for="apply_to_opening">Apply to Open Balance</label>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            @unless($isReadOnly)
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
                                                    <th>Received Amount</th>
                                                    <th>Balance Amount</th>
                                                    <th></th>
                                                </tr>
                                            </thead>
                                               <tbody></tbody>
                                           </table>
                                       </div>
                                   </div>
                            </div>
                            @endunless
                        </div>

                        <div class="table-responsive">
                        <table class="table table-bordered" id="bills-table">
                            <thead>
                                <tr>
                                    <th>Bill Ref No</th>
                                    <th>Bill Date</th>
                                    <th>Bill Amount</th>
                                    <th>Received Amount</th>
                                    <th>Balance Amount</th>
                                    <th>Payment Amount</th>
                                    <th>Final Balance</th>
                                            <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @if(old('lines'))
                                    @foreach(old('lines') as $i => $oline)
                                        <tr @if(empty($oline['sale_id'] ?? null)) class="opening-row" @endif>
                                            <td>{{ $oline['bill_ref'] ?? '' }}</td>
                                            <td>{{ (!empty($oline['bill_date']) && $oline['bill_date'] !== '-' && $oline['bill_date'] !== '0000-00-00') ? \Carbon\Carbon::parse($oline['bill_date'])->format('d-m-Y') : '' }}</td>
                                            <td class="bill-amount">{{ number_format($oline['bill_amount'] ?? 0, 2, '.', '') }}</td>
                                            <td class="received-before">{{ number_format($oline['received_before'] ?? 0, 2, '.', '') }}</td>
                                            <td class="balance-before">{{ number_format($oline['balance_before'] ?? 0, 2, '.', '') }}</td>
                                            <td>
                                                    {{-- visible formatted payment display --}}
                                                    <input type="text" id="lines_{{ $i }}_payment_display" class="form-control currency-input payment-amount-display" inputmode="decimal" maxlength="15" value="{{ number_format($oline['payment_amount'] ?? 0, 2, '.', '') }}" data-target="#lines_{{ $i }}_payment_raw">
                                                    {{-- hidden raw payment keeps server name --}}
                                                    <input type="hidden" name="lines[{{ $i }}][payment_amount]" id="lines_{{ $i }}_payment_raw" class="payment-amount" value="{{ number_format($oline['payment_amount'] ?? 0, 2, '.', '') }}">
                                                @if($errors->has("lines.$i.payment_amount"))
                                                    <small class="text-danger">{{ $errors->first("lines.$i.payment_amount") }}</small>
                                                @endif
                                            </td>
                                            <td class="final-balance">{{ number_format($oline['final_balance'] ?? 0, 2, '.', '') }}</td>
                                            <td><button type="button" class="btn btn-sm btn-danger remove-row">Remove</button></td>
                                            <input type="hidden" name="lines[{{ $i }}][sale_id]" value="{{ $oline['sale_id'] ?? '' }}">
                                        </tr>
                                    @endforeach
                                @else
                                    @foreach($receipt->lines as $i => $line)
                                        <tr @if(empty($line->sale_id)) class="opening-row" @endif>
                                            <td>{{ $line->bill_ref }}</td>
                                            <td>{{ (!empty($line->bill_date) && $line->bill_date !== '-' && $line->bill_date !== '0000-00-00') ? \Carbon\Carbon::parse($line->bill_date)->format('d-m-Y') : '' }}</td>
                                            <td class="bill-amount">{{ number_format($line->bill_amount, 2, '.', '') }}</td>
                                            <td class="received-before">{{ number_format($line->received_before, 2, '.', '') }}</td>
                                            <td class="balance-before">{{ number_format($line->balance_before, 2, '.', '') }}</td>
                                            <td>
                                                {{-- visible formatted payment display --}}
                                                <input type="text" id="lines_{{ $i }}_payment_display" class="form-control currency-input payment-amount-display" inputmode="decimal" maxlength="15" value="{{ number_format($line->payment_amount, 2, '.', '') }}" data-target="#lines_{{ $i }}_payment_raw" {{ $isReadOnly ? 'disabled' : '' }}>
                                                {{-- hidden raw payment keeps server name --}}
                                                <input type="hidden" name="lines[{{ $i }}][payment_amount]" id="lines_{{ $i }}_payment_raw" class="payment-amount" value="{{ number_format($line->payment_amount, 2, '.', '') }}">
                                            </td>
                                            <td class="final-balance">{{ number_format($line->final_balance, 2, '.', '') }}</td>
                                            <td>@unless($isReadOnly)<button type="button" class="btn btn-sm btn-danger remove-row">Remove</button>@endunless</td>
                                            <input type="hidden" name="lines[{{ $i }}][sale_id]" value="{{ $line->sale_id }}">
                                        </tr>
                                    @endforeach
                                @endif
                            </tbody>
                        </table>
                        </div>

                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                @php
                                    $serverTotalAllocated = 0;
                                    // Match client-side settlement rule: consider only payments when deciding allocation
                                    foreach($receipt->lines as $line) { $serverTotalAllocated += (float)($line->payment_amount ?? 0); }
                                    $serverReceiptAmt = (float)($receipt->total_amount ?? 0);
                                    $serverIsSettled = ($serverReceiptAmt > 0) && (abs($serverReceiptAmt - $serverTotalAllocated) < 0.01);
                                @endphp
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="global_settled" readonly {{ $serverIsSettled ? 'checked' : '' }} {{ $isReadOnly ? 'disabled' : '' }}>
                                    <label class="form-check-label" for="global_settled">Settlement Status (Auto-calculated)</label>
                                    <div id="settlement-status" class="small">{{ $serverIsSettled ? 'Settled' : 'Not Settled (Amount mismatch)' }}</div>
                                </div>
                            </div>
                            <div>
                                <a href="{{ route('sales-receipts.index') }}" class="btn btn-secondary mr-2">Back</a>
                                @unless($isReadOnly)
                                    <button class="btn btn-primary" type="submit">Update Receipt</button>
                                @endunless
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

@push('page_scripts')
@unless($isReadOnly)
    <script src="{{ asset_v('js/currency-input.js') }}"></script>
<script>
$(function(){
    // populate customer select via AJAX (simple select2 if available)
    console.debug('init: customer-select script running (edit page)');

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
    // (no readNumericFromRow here) SalesReceipt uses direct access to hidden '.payment-amount' and '.discount-amount' inputs.

    // Re-index line input names/ids after add/remove so names remain contiguous.
    // Reason: keeps `lines[0]..lines[n]` sequential so Laravel request parsing,
    // validation mapping and DOM id/data-target bindings work reliably after
    // rows are removed or inserted. We call this after add/remove and before submit.
    function reindexLines() {
        $('#bills-table tbody tr').each(function(i){
            var $tr = $(this);
            $tr.find('input, select, textarea').each(function(){
                var $el = $(this);
                var name = $el.attr('name');
                if (name && name.indexOf('lines[') === 0) {
                    var newName = name.replace(/^lines\[\d+\]/, 'lines['+i+']');
                    $el.attr('name', newName);
                }
                var id = $el.attr('id');
                if (id && id.indexOf('lines_') === 0) {
                    var newId = id.replace(/^lines_\d+_/, 'lines_'+i+'_');
                    $el.attr('id', newId);
                }
                var dt = $el.attr('data-target');
                if (dt && dt.indexOf('#lines_') === 0) {
                    var newDt = dt.replace(/#lines_\d+_/, '#lines_'+i+'_');
                    $el.attr('data-target', newDt);
                }
            });
            var $hid = $tr.find('input[type=hidden][name$="[sale_id]"]');
            if ($hid.length) $hid.attr('name', 'lines['+i+'][sale_id]');
        });
    }
    // current receipt id (used so the server can return sale amounts "before" this receipt's payments)
    var receiptId = '{{ $receipt->id ?? '' }}';
    var isSaleReturnReceipt = {{ $receipt->sale_return_id ? '1' : '0' }};
    var appliedToCustomer = {{ $receipt->applied_to_customer !== null ? number_format($receipt->applied_to_customer/100, 2, '.', '') : 'null' }};
    var billsRequest = null; // track outstanding AJAX for bills so we can abort when selecting another customer
    var billsReady = false; // becomes true after bills API returns for initial load
    function formatAmount(v) {
        if (v === null || v === undefined || v === '') return '';
        var num = parseFloat(v);
        if (isNaN(num)) return v;
        return num.toFixed(2);
    }

    // Format for visible display: remove unnecessary trailing .00 when whole number
    function formatDisplayNoTrailing(v) {
        if (v === null || v === undefined || v === '') return '';
        var num = parseFloat(v);
        if (isNaN(num)) return v;
        if (Math.abs(num - Math.round(num)) < 0.005) return String(Math.round(num));
        return num.toFixed(2).replace(/\.00$/, '').replace(/(\.\d)0$/, '$1');
    }

    $('#customer-select').select2({
        ajax: {
            url: '{{ route('api.customers.search') }}',
            dataType: 'json',
            delay: 250,
            data: function(params) { return { q: params.term || '' }; },
            processResults: function(data) { return { results: data.results || [] }; },
            cache: true
        },
        minimumInputLength: 0,
        placeholder: 'Search and select customer...',
        allowClear: true,
        width: '100%'
    });

    // initialize currency inputs on page (server-rendered hidden raw inputs present)
    try { if (window.currencyInputInit) window.currencyInputInit(); } catch(e) {}

    // allow submitting without bill rows when apply_to_opening is checked
    $('#receipt-form').off('submit.apply_to_opening').on('submit.apply_to_opening', function(e){
        var rows = $('#bills-table tbody tr').length || 0;
        if (rows === 0 && !$('#apply_to_opening').is(':checked')) {
            e.preventDefault();
            alert('Select at least one bill or check "Apply to Opening Balance" before saving.');
            return false;
        }
    });

    // stored balance value (string) captured when receipt was created
    var storedBalance = '{{ isset($receipt->customer_balance_before) && $receipt->customer_balance_before !== null ? number_format($receipt->customer_balance_before/100,2,'.','') : '' }}';
    // track the customer for which the stored balance applies; if user selects a
    // different customer we clear the stored value so subsequent loads show live data.
    var currentCustomerId = '{{ $receipt->customer_id ?? '' }}';

    // helper to load customer details and available bills (used on select and initial load)
    function loadCustomerDetails(id, extraIncludeIds) {
        if (!id) return;
        // abort previous request if still pending to avoid race conditions that cause duplicated rows
        if (billsRequest && typeof billsRequest.abort === 'function') {
            try { billsRequest.abort(); } catch(e) {}
        }
        $('#available-bills-table tbody').empty();
        availableBillsMap = {};
        $('#available-bills .loading-spinner').show();
        // gather sale ids currently present in the receipt table so the server can include them
        var presentIds = [];
        $('#bills-table tbody input[type=hidden][name$="[sale_id]"]').each(function(){ presentIds.push($(this).val()); });
        // merge any extra ids (for example the id of a row just removed so server will include it)
        if (Array.isArray(extraIncludeIds)) {
            extraIncludeIds.forEach(function(i){ if (i && presentIds.indexOf(i.toString()) === -1) presentIds.push(i.toString()); });
        }

        billsReady = false;
        var custReq = $.get('{{ url('api/customers') }}/' + id);
        var billsReq = $.get('{{ route('salesreceipts.sales.search') }}', { customer_id: id, include_ids: presentIds, receipt_id: receiptId });
        billsRequest = billsReq;

        $.when(custReq, billsReq).done(function(custResp, billsResp){
            var resp = custResp[0];
            var res = billsResp[0];

            $('#area').val(resp.area || '');
            // when editing, apply stored balance only for the original customer
            if (storedBalance && currentCustomerId && id.toString() === currentCustomerId.toString()) {
                $('#opening_balance').val(storedBalance);
            } else {
                $('#opening_balance').val(resp.opening_balance_formatted || resp.opening_balance || 0);
                $('#bill_balance_display').val(resp.bill_balance_formatted !== undefined ? resp.bill_balance_formatted : '0.00');
                $('#total_balance_display').val(resp.total_balance_formatted !== undefined ? resp.total_balance_formatted : '0.00');
            }
            // populate excess amount
            if (resp.excess_amount_formatted !== undefined) {
                $('#excess_amount_display').val(resp.excess_amount_formatted);
                $('#excess_amount').val(resp.excess_amount !== undefined ? resp.excess_amount : 0);
            } else if (resp.excess_amount !== undefined) {
                $('#excess_amount_display').val(parseFloat(resp.excess_amount).toFixed(2));
                $('#excess_amount').val(resp.excess_amount);
            } else {
                $('#excess_amount_display').val('0.00');
                $('#excess_amount').val(0);
            }

            var results = res.results || [];
                var results = res.results || [];
                // dedupe by sale id to be defensive
                var seen = {};
                    if (results.length) {
                    results.forEach(function(s){
                        if (!s || !s.id) return;
                        if (seen[s.id]) return; seen[s.id] = true;
                        // Prefer values already present in the receipt table for sales that are
                        // part of the current receipt (so Available Bills matches the Bill section).
                        var $existingRow = $('#bills-table tbody input[type=hidden][value="' + s.id + '"]').closest('tr');
                        if ($existingRow.length) {
                            // read DOM cells (these are authoritative for the edit view)
                            var domPaid = $.trim($existingRow.find('.received-before').text());
                            var domDue = $.trim($existingRow.find('.balance-before').text());
                            if (domPaid !== '') {
                                s.paid_amount = parseNumber(domPaid);
                            } else {
                                s.paid_amount = (s.received_before !== undefined) ? s.received_before : ((s.paid_amount !== undefined) ? s.paid_amount : (s.paid !== undefined ? s.paid : ''));
                            }
                            if (domDue !== '') {
                                s.due_amount = parseNumber(domDue);
                            } else {
                                s.due_amount = (s.balance_before !== undefined) ? s.balance_before : (s.due_amount !== undefined ? s.due_amount : s.balance);
                            }
                        } else {
                            // Prefer authoritative "before" fields when present (server should provide
                            // received_before / balance_before relative to this receipt_id). Fall back
                            // to other common field names if not present.
                            s.due_amount = (s.balance_before !== undefined) ? s.balance_before : (s.due_amount !== undefined ? s.due_amount : s.balance);
                            s.paid_amount = (s.received_before !== undefined) ? s.received_before : ((s.paid_amount !== undefined) ? s.paid_amount : (s.paid !== undefined ? s.paid : ''));
                        }
                        availableBillsMap[s.id] = s;
                        var $tr = $('<tr>').attr('data-id', s.id);
                        $tr.append($('<td>').text(s.reference || s.ref || ''));
                        $tr.append($('<td>').text(formatDateDmy(s.date) || ''));
                        var billAmt = (s.bill_amount !== undefined) ? s.bill_amount : (s.total_amount !== undefined ? s.total_amount : (s.total !== undefined ? s.total : ''));
                        $tr.append($('<td>').text(formatAmount(billAmt)));
                        $tr.append($('<td>').text(formatAmount(s.paid_amount)));
                        $tr.append($('<td>').text(formatAmount(s.due_amount)));
                        var $btn = $('<button>', { type: 'button', 'class': 'btn btn-sm btn-primary add-bill', 'data-id': s.id }).text('Add');
                        $tr.append($('<td>').append($btn));
                        $('#available-bills-table tbody').append($tr);
                    });
                } else {
                    // No bills available - show message
                    var $tr = $('<tr>');
                    $tr.append($('<td>', { colspan: 6, 'class': 'text-center text-muted py-3' }).text('No bills available for this customer'));
                    $('#available-bills-table tbody').append($tr);
                }
                // Update any existing rows in the receipt table with authoritative amounts
                // returned by the bills API so client-side recalculation uses correct values.
                try {
                    $('#bills-table tbody tr').each(function(){
                        var $r = $(this);
                        var sid = $r.find('input[type=hidden][name$="[sale_id]"]').val();
                        if (sid && availableBillsMap[sid]) {
                            var s = availableBillsMap[sid];
                            // Update displayed amounts if available from API (prefer bill_amount)
                            if (s.bill_amount !== undefined) {
                                $r.find('.bill-amount').text(formatAmount(s.bill_amount));
                            } else if (s.total_amount !== undefined) {
                                $r.find('.bill-amount').text(formatAmount(s.total_amount));
                            }
                            if (s.paid_amount !== undefined) {
                                var $recvCell = $r.find('.received-before');
                                var curr = $.trim($recvCell.text());
                                if (curr === '' || curr === '-' ) {
                                    $recvCell.text(formatAmount(s.paid_amount));
                                }
                            }
                            if (s.due_amount !== undefined) {
                                var $balCell = $r.find('.balance-before');
                                var currB = $.trim($balCell.text());
                                if (currB === '' || currB === '-') {
                                    $balCell.text(formatAmount(s.due_amount));
                                }
                            }
                        }
                    });
                } catch(e) { console.debug('Failed to sync existing rows with bills API', e); }
                // After appending rows, mark as 'Added' any bills already present in the receipt table
                var presentIds = {};
                $('#bills-table tbody input[type=hidden][name$="[sale_id]"]').each(function(){ presentIds[$(this).val()] = true; });
                Object.keys(presentIds).forEach(function(id){
                    var $btn = $('#available-bills-table').find('button.add-bill[data-id="' + id + '"]');
                    if ($btn.length) { $btn.prop('disabled', true).text('Added').removeClass('btn-primary').addClass('btn-secondary'); }
                });

                // Mark bills as ready — it's now safe to compute settlement and update UI
                billsReady = true;

                // Show apply-to-opening whenever the customer has an Open Balance (even if bills exist).
                if (parseFloat($('#opening_balance').val() || '0') > 0) {
                    $('#apply_to_opening_container').show();
                } else {
                    $('#apply_to_opening_container').hide();
                    $('#apply_to_opening').prop('checked', false);
                    $('#bills-table tbody tr.opening-row').remove();
                    computeSettlement();
                }
            })
            .always(function(){ $('#available-bills .loading-spinner').hide(); billsRequest = null; ensureOpeningRow(); });
    }

    $('#customer-select').on('select2:select', function(e){
        var id = e.params.data.id;
        // if user switches away from the original customer, drop stored balance
        if (currentCustomerId && id.toString() !== currentCustomerId.toString()) {
            storedBalance = '';
        }
        currentCustomerId = id;
        loadCustomerDetails(id);
    });

    // hide apply-to-opening until customer selected and bills loaded
    $('#apply_to_opening_container').hide();

    // when customer cleared, hide the apply-to-opening control and remove opening rows
    $('#customer-select').on('select2:clear', function(){
        $('#apply_to_opening_container').hide();
        $('#apply_to_opening').prop('checked', false);
        $('#bills-table tbody tr.opening-row').remove();
        computeSettlement();
    });

    // If we have an initial customer (editing existing receipt), fetch its display name and select it
    var initialCustomerId = '{{ old('customer_id', $receipt->customer_id ?? '') }}';
    if (initialCustomerId) {
        console.debug('edit: initial customer id', initialCustomerId);
        // fetch canonical customer data to build display text (avoid relying on server-side rendered placeholder)
        $.get('{{ url('api/customers') }}/' + initialCustomerId).done(function(resp){
            var display = (resp.name || resp.customer_name || '') + (resp.code ? (' (' + resp.code + ')') : '');
            if (!display) display = 'Selected Customer';
            var opt = new Option(display, initialCustomerId, true, true);
            $('#customer-select').append(opt);
            // set value and notify Select2
            $('#customer-select').val(initialCustomerId).trigger('change');
            // Ensure Select2 displayed text is updated (some Select2 setups don't refresh immediately)
            try {
                var $container = $('#customer-select').siblings('.select2-container');
                if ($container && $container.length) {
                    $container.find('.select2-selection__rendered').text(display);
                }
            } catch (e) { /* ignore */ }
            // trigger select event so handlers (loading bills) run the same
            $('#customer-select').trigger({ type: 'select2:select', params: { data: { id: initialCustomerId, text: display } } });
            // also load details explicitly (defensive)
            loadCustomerDetails(initialCustomerId);
        }).fail(function(){
            // fallback: if API not reachable, still try to select the option rendered server-side
            $('#customer-select').val(initialCustomerId).trigger('change.select2');
            loadCustomerDetails(initialCustomerId);
        });
    }

    function addBillRow(s) {
        if ($('#bills-table tbody input[type=hidden][value="' + s.id + '"]').length) return;
        var tr = $('<tr>');
        var idx = $('#bills-table tbody tr').length;
        tr.append($('<td>').text(s.reference || s.ref || ''));
        tr.append($('<td>').text(formatDateDmy(s.date)));
    tr.append($('<td class="bill-amount">').text(formatAmount(s.total_amount)));
    tr.append($('<td class="received-before">').text(formatAmount(s.paid_amount)));
    tr.append($('<td class="balance-before">').text(formatAmount(s.due_amount)));
        // payment display (visible) + hidden raw (submitted)
        var payDisplayId = 'lines_' + idx + '_payment_display';
        var payRawId = 'lines_' + idx + '_payment_raw';
        var $payDisplay = $('<input>', { type: 'text', id: payDisplayId, 'class': 'form-control currency-input payment-amount-display', inputmode: 'decimal', maxlength: 15, placeholder: '0.00', 'data-target': '#' + payRawId, value: '' });
        var $payRaw = $('<input>', { type: 'hidden', name: 'lines[' + idx + '][payment_amount]', id: payRawId, 'class': 'payment-amount', value: '' });
        tr.append($('<td>').append($payDisplay).append($payRaw));

    tr.append($('<td class="final-balance">').text(formatAmount(s.due_amount)));
        tr.append($('<td>').html('<button type="button" class="btn btn-sm btn-danger remove-row">Remove</button>'));
        var hidden = $('<input>', { type: 'hidden', name: 'lines[' + idx + '][sale_id]' }).val(s.id);
        tr.append(hidden);
        // (no per-line settled flag; settlement computed server-side based on receipt amount vs allocations)
        $('#bills-table tbody').append(tr);
        var $availBtn = $('#available-bills-table').find('button.add-bill[data-id="' + s.id + '"]');
        if ($availBtn.length) { $availBtn.prop('disabled', true).text('Added').removeClass('btn-primary').addClass('btn-secondary'); }
        // initialize currency inputs on newly appended displays
        try { if (window.currencyInputInit) window.currencyInputInit(); } catch(e) {}
        // ensure newly added inputs are cleared and don't inherit stale dataset/raw
        var $newRow = $('#bills-table tbody tr').last();
        var $payDisplay = $newRow.find('#' + payDisplayId);
        var $payRaw = $newRow.find('#' + payRawId);
        try { $payRaw.val(''); $payDisplay.val(''); if ($payDisplay && $payDisplay.length) { try{ $payDisplay.get(0).dataset.raw = ''; } catch(e){} } } catch(e) {}
        var $paymentHidden = $newRow.find('.payment-amount');
        try { $paymentHidden.trigger('input'); } catch(e) {}
        // reindex lines and update UI/state
        try { reindexLines(); } catch(e) {}
        // focus the visible display for convenience
        try { $('#'+payDisplayId).focus(); } catch(e) {}
        computeSettlement();
    }

    // Ensure an opening-balance row is present when the checkbox is checked. It can
    // coexist with bill rows so one receipt settles bills AND Open Balance.
    function ensureOpeningRow() {
        var checked = $('#apply_to_opening').is(':checked');
        if (!checked) {
            $('#bills-table tbody tr.opening-row').remove();
            try { reindexLines(); } catch(e) {}
            computeSettlement();
            return;
        }
        // An opening line may already be present (server-rendered on edit) — detect it by
        // its empty sale_id so we never add a duplicate.
        var $existingOpening = $('#bills-table tbody tr').filter(function(){
            if ($(this).hasClass('opening-row')) return true;
            var v = $(this).find('input[type=hidden][name$="[sale_id]"]').val();
            return (v === '' || v === undefined || v === null);
        });
        if ($existingOpening.length > 0) { $existingOpening.addClass('opening-row'); computeSettlement(); return; }

        var openingBal = parseNumber($('#opening_balance').val() || '0');
        var receiptAmtRaw = $('#receipt_amount_raw').length ? $('#receipt_amount_raw').val() : $('#receipt_amount').val();
        var receiptAmt = parseNumber(receiptAmtRaw || '0');
        // Default opening payment: prefer the receipt's stored applied amount, else the
        // receipt amount not yet allocated to bills, capped at the Open Balance.
        var billPay = 0;
        $('#bills-table tbody tr').each(function(){ billPay += parseFloat($(this).find('.payment-amount').val()) || 0; });
        var openingPayment = Math.max(0, Math.min(receiptAmt - billPay, openingBal));
        try {
            if (typeof appliedToCustomer !== 'undefined' && appliedToCustomer !== null) {
                var ap = parseFloat(appliedToCustomer) || 0;
                if (ap > 0) openingPayment = ap;
            }
        } catch(e) {}
        var payVal = openingPayment > 0 ? openingPayment.toFixed(2) : '';

        var tr = $('<tr>').addClass('opening-row');
        tr.append($('<td>').text('Opening Balance'));
        tr.append($('<td>').text('-'));
        tr.append($('<td class="bill-amount">').text((0).toFixed(2)));
        tr.append($('<td class="received-before">').text('0.00'));
        tr.append($('<td class="balance-before">').text(openingBal.toFixed(2)));
        // non-numeric id suffix so reindexLines (which renumbers names) leaves id/data-target intact
        var payDisplayId = 'lines_open_payment_display';
        var payRawId = 'lines_open_payment_raw';
        var $payDisplay = $('<input>', { type: 'text', id: payDisplayId, 'class': 'form-control currency-input payment-amount-display', inputmode: 'decimal', maxlength: 15, placeholder: '0.00', 'data-target': '#' + payRawId, value: payVal });
        var $payRaw = $('<input>', { type: 'hidden', name: 'lines[0][payment_amount]', id: payRawId, 'class': 'payment-amount', value: payVal });
        tr.append($('<td>').append($payDisplay).append($payRaw));
        tr.append($('<td class="final-balance">').text((openingBal - (openingPayment || 0)).toFixed(2)));
        tr.append($('<td>').html('<button type="button" class="btn btn-sm btn-danger remove-row">Remove</button>'));
        tr.append($('<input>', { type: 'hidden', name: 'lines[0][sale_id]' }).val(''));
        $('#bills-table tbody').append(tr);
        try { reindexLines(); } catch(e) {}
        try { if (window.currencyInputInit) window.currencyInputInit(); } catch(e) {}
        computeSettlement();
    }

    // react to checkbox and receipt amount changes
    $('#apply_to_opening').on('change', function(){
        ensureOpeningRow();
    });
    $('#receipt_amount').on('input blur', function(){
        var src = $('#receipt_amount_raw').length ? $('#receipt_amount_raw').val() : $(this).val();
        var val = (src || '').toString().replace(/[^0-9\.\-]/g, '');
        var num = parseFloat(val || '0') || 0;
        var $orow = $('#bills-table tbody tr.opening-row');
        if ($orow.length) {
            // Do not overwrite displayed bill amounts when receipt changes.
            // Keep server-provided applied payment in sync, but do not change bill-amount.
            // Do not auto-fill opening payment from receipt amount. Preserve any server-provided
            // applied amount (already set), otherwise leave payment blank for manual allocation.
            var $pay = $orow.find('.payment-amount');
            var currentVal = ($pay.val() || '').toString().replace(/[^0-9\.-]/g, '');
            if (currentVal !== '') {
                // if a server-provided applied amount exists, keep it in sync with receipt changes
                // but do not overwrite a user's manual allocation here.
                var ap = parseFloat(currentVal) || 0;
                if (ap > 0 && ap <= num) {
                    $pay.val(ap.toFixed(2)).trigger('input');
                }
            }
        }
    });

    $('#available-bills-table').on('click', 'button.add-bill', function(){
        var id = $(this).data('id');
        var s = availableBillsMap[id];
        if (!s) return;
        addBillRow(s);
    });

    // settlement is computed on the server based on receipt amount vs per-line allocations

    $('#bills-table').on('input', '.payment-amount-display, .discount-amount-display', function(){
        if (!billsReady) return; // avoid computing with stale balances before API sync
        var row = $(this).closest('tr');
        // Final balance should be calculated from the current Balance Amount (balance-before)
        // minus any payment + discount applied to this row.
        var balanceBefore = parseFloat(row.find('.balance-before').text()) || 0;
        var pay = parseFloat(row.find('.payment-amount').val()) || 0;
        var disc = parseFloat(row.find('.discount-amount').val()) || 0;
        var finalBal = balanceBefore - (pay + disc);
        row.find('.final-balance').text(finalBal.toFixed(2));
        computeSettlement();
    });

    $('#bills-table').on('click', '.remove-row', function(){
        var $tr = $(this).closest('tr');
        var saleId = $tr.find('input[type=hidden][name$="[sale_id]"]').val();
        $tr.remove();
        if (saleId) {
            var $btn = $('#available-bills-table').find('button.add-bill[data-id="' + saleId + '"]');
            if ($btn.length) {
                // if the available list already contains the bill, re-enable its Add button
                $btn.prop('disabled', false).text('Add').removeClass('btn-secondary').addClass('btn-primary');
            } else {
                // Instead of creating a purely client-side fallback row, ask the server to include this sale id
                // so the authoritative row is returned. This relies on the search endpoint's include_ids handling.
                var currentCustomer = $('#customer-select').val();
                if (currentCustomer) {
                    // pass the removed sale id so the server will include it in results
                    loadCustomerDetails(currentCustomer, [saleId]);
                } else {
                    // fallback to local row if no customer is selected
                    var billRef = $tr.find('td').eq(0).text() || '';
                    var billDate = $tr.find('td').eq(1).text() || '';
                    var billAmount = $tr.find('.bill-amount').text() || '';
                    var balanceAmount = $tr.find('.balance-before').text() || '';

                    var $new = $('<tr>').attr('data-id', saleId);
                    $new.append($('<td>').text(billRef));
                    $new.append($('<td>').text(billDate));
                    $new.append($('<td>').text(billAmount));
                    $new.append($('<td>').text(balanceAmount));
                    var $addBtn = $('<button>', { type: 'button', 'class': 'btn btn-sm btn-primary add-bill', 'data-id': saleId }).text('Add');
                    $new.append($('<td>').append($addBtn));
                    $('#available-bills-table tbody').append($new);
                    // update the in-memory map so other code knows it's available
                    availableBillsMap[saleId] = { id: saleId, reference: billRef, date: billDate, total_amount: billAmount, due_amount: balanceAmount };
                }
            }
        }
        // reindex lines after removal
        try { reindexLines(); } catch(e) {}
    });

    // compute and set settlement checkbox based on receipt amount vs allocated amounts
    function computeSettlement() {
        var amtSource = $('#receipt_amount_raw').length ? $('#receipt_amount_raw').val() : $('#receipt_amount').val();
        var receiptAmt = parseNumber(amtSource || '0');

        if (!billsReady) return; // skip until bills API has provided authoritative balances

        // Compute initialAvailable first (sale-return rules depend on receiptAmt + opening balance)
        var initialAvailable = receiptAmt;
        if (isSaleReturnReceipt) {
            if (appliedToCustomer !== null) {
                var applied = parseFloat(appliedToCustomer) || 0;
                initialAvailable = Math.max(0, receiptAmt - applied);
            } else {
                var obRaw = ($('#opening_balance').val() || '').toString();
                var obClean = obRaw.replace(/[^0-9\.-]/g, '');
                var custBal = parseFloat(obClean || '0') || 0;
                if (custBal < 0) {
                    initialAvailable = Math.max(0, receiptAmt - Math.abs(custBal));
                } else {
                    initialAvailable = (custBal >= receiptAmt) ? 0 : (receiptAmt - custBal);
                }
            }
        }

        var totalAllocated = 0;
        var invalidAllocation = false;
        var $rows = $('#bills-table tbody tr');

        // Single pass: compute per-row final balance, accumulate totals, and flag invalid rows
        $rows.each(function(){
            var $row = $(this);
            var p = parseFloat($row.find('.payment-amount').val()) || 0;
            var d = parseFloat($row.find('.discount-amount').val()) || 0;
            var balanceBefore = parseFloat($row.find('.balance-before').text()) || 0;
            var finalBal = balanceBefore - (p + d);
            $row.find('.final-balance').text(finalBal.toFixed(2));
            // Only payment amounts reduce the receipt's available amount; discounts affect per-row final balance only.
            totalAllocated += (p);
            if (p > initialAvailable + 0.0001) {
                invalidAllocation = true;
                $row.addClass('table-danger');
            } else {
                $row.removeClass('table-danger');
            }
        });

        var receiptBalance = initialAvailable - totalAllocated;
        $('#receipt_balance').val((isNaN(receiptBalance) ? 0 : receiptBalance).toFixed(2));

        var isSettled;
        if (isSaleReturnReceipt) {
            isSettled = (receiptAmt > 0) && (Math.abs(initialAvailable - totalAllocated) < 0.01);
        } else {
            isSettled = (receiptAmt > 0) && (Math.abs(receiptAmt - totalAllocated) < 0.01);
        }
        $('#global_settled').prop('checked', isSettled);

        var settledText = '';
        var settledClass = '';
        if (receiptAmt <= 0) {
            settledText = 'Enter receipt amount';
            settledClass = 'text-muted';
        } else {
            settledText = isSettled ? 'Settled (Amount properly distributed)' : 'Not Settled (Amount mismatch)';
            settledClass = isSettled ? 'text-success' : 'text-warning';
        }
        if (!$('#settlement-status').length) {
            $('#global_settled').parent().after('<div id="settlement-status" class="small"></div>');
        }
        $('#settlement-status').text(settledText).removeClass('text-success text-warning text-muted').addClass(settledClass);

        if (totalAllocated > initialAvailable + 0.0001) invalidAllocation = true;
        var $submit = $('#receipt-form').find('button[type=submit]').first();
        if (invalidAllocation) {
            if (!$('#allocation-warning').length) {
                $('#receipt-form').prepend('<div id="allocation-warning" class="alert alert-warning">Allocated amounts exceed available receipt amount. Adjust Receipt Amount or Reduce Payment Amounts.</div>');
            }
            try { $submit.prop('disabled', true); } catch(e) {}
        } else {
            $('#allocation-warning').remove();
            try { $submit.prop('disabled', false); } catch(e) {}
        }
    }

    // Initial settlement and opening-row will be computed after customer/bills API returns

    // Recalculate per-row final balances from current inputs (fix stale server-rendered values)
    $('#bills-table tbody tr').each(function(){
        // Trigger the visible display inputs so the per-row handlers recalc final balances
        $(this).find('.payment-amount-display').trigger('input');
        $(this).find('.discount-amount-display').trigger('input');
    });

    // Disable manual checkbox toggle - settlement is calculated automatically
    $('#global_settled').on('click', function(e){
        e.preventDefault();
        if (!$('#auto-settlement-notice').length) {
            $('#global_settled').parent().after('<div id="auto-settlement-notice" class="small text-info mt-1">Settlement is automatically calculated based on amount distribution</div>');
            setTimeout(function() { $('#auto-settlement-notice').fadeOut(); }, 3000);
        }
        return false;
    });

    // Input constraints for edit page
    // Particular: allow only alphanumeric, spaces and hyphen
    $('#receipt-form').on('input', 'input[name="particular"]', function(){
        var v = $(this).val() || '';
        var clean = v.replace(/[^A-Za-z0-9 \-]/g, '');
        if (v !== clean) $(this).val(clean);
    });

    // numeric sanitizers (delegated) - operate on hidden raw inputs to avoid conflicting with currency-input display
    $('#receipt-form').on('input', '#receipt_amount_raw, .payment-amount, .discount-amount', function(){
        var v = $(this).val() || '';
        var clean = v.replace(/[^0-9\.\-]/g, '');
        var parts = clean.split('.');
        if (parts.length > 2) clean = parts.shift() + '.' + parts.join('');
        var max = parseInt($(this).attr('maxlength') || '15', 10) || 15;
        if (clean.length > max) clean = clean.substring(0, max);
        $(this).val(clean);
        // update settlement live while typing
        try { computeSettlement(); } catch(e) { /* ignore if computeSettlement not available yet */ }
    });

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

    $('#receipt-form').on('submit', function(e){
        e.preventDefault();
        $('#receipt-form').find('.client-validation-alert, .server-validation-alert').remove();
        var firstInvalid = null;
        var amtSource = $('#receipt_amount_raw').length ? $('#receipt_amount_raw').val() : $('#receipt_amount').val();
        var amtRaw = (amtSource || '').toString();
        var amtClean = amtRaw.replace(/[^0-9\.\-]/g, '');
        var amtNum = parseFloat(amtClean || '0');
        if (amtClean === '' || isNaN(amtNum) || amtNum <= 0) { firstInvalid = firstInvalid || $('#receipt_amount'); $('#receipt_amount').addClass('is-invalid'); } else { $('#receipt_amount').removeClass('is-invalid'); }
        var paymode = ($('#payment_mode').val() || '').toString();
        if (!paymode) { firstInvalid = firstInvalid || $('#payment_mode'); $('#payment_mode').addClass('is-invalid'); } else { $('#payment_mode').removeClass('is-invalid'); }
        if (firstInvalid) {
            var $alert = $('<div class="alert alert-danger client-validation-alert">Please fill required fields: Amount and Payment Mode.</div>');
            $('#receipt-form').prepend($alert);
            $('html,body').animate({ scrollTop: firstInvalid.offset().top - 100 }, 200);
            firstInvalid.focus();
            return false;
        }
        $('#bills-table tbody tr').each(function(){
            var $row = $(this);
            var $payment = $row.find('.payment-amount');
            if ($payment.length) {
                var raw = ($payment.val() || '').toString();
                var cleaned = raw.replace(/[^0-9\.]/g, '');
                if (cleaned === '' || isNaN(parseFloat(cleaned)) || parseFloat(cleaned) <= 0) {
                    $row.addClass('table-warning'); $payment.addClass('is-invalid'); if (!firstInvalid) firstInvalid = $payment;
                } else { $row.removeClass('table-warning'); $payment.removeClass('is-invalid'); }
            }
        });
        if (firstInvalid) {
            var $alert = $('<div class="alert alert-danger client-validation-alert">Please provide a payment amount for each selected bill before submitting.</div>');
            $('#receipt-form').prepend($alert);
            $('html,body').animate({ scrollTop: firstInvalid.offset().top - 100 }, 200);
            firstInvalid.focus();
            return false;
        }
        $('.payment-amount, .discount-amount').each(function(){ var v = $(this).val(); v = v.replace(/[^0-9\.]/g, ''); var num = parseFloat(v || 0); if (isNaN(num)) num = 0; $(this).val(num.toFixed(2)); });

    var $form = $(this);
    var url = $form.attr('action');
    // ensure contiguous indices before serializing
    try { reindexLines(); } catch(e) {}
    var data = $form.serialize();

        // UX: show loading state on submit button so user knows request is in progress
        var $submitBtn = $form.find('button[type=submit]').first();
        var origBtnHtml = $submitBtn.html();
        $submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Updating...');

        $.ajax({ url: url, method: 'POST', data: data, dataType: 'json', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' } }).done(function(resp){ window.location = '{{ route('sales-receipts.index') }}'; }).fail(function(xhr){
            if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                var errors = xhr.responseJSON.errors;
                var $list = $('<ul class="mb-0"></ul>');
                Object.keys(errors).forEach(function(key){
                    var msgs = errors[key];
                    if (!Array.isArray(msgs)) msgs = [msgs];
                    msgs.forEach(function(msg){
                        $list.append($('<li>').text(msg));
                        try {
                            var sel = key.replace(/\./g, '][').replace(/\*/g, '');
                            var $input = $form.find('[name="' + key + '"]').length ? $form.find('[name="' + key + '"]') : $form.find('[name="' + sel + '"]');
                            if ($input && $input.length) {
                                $input.addClass('is-invalid');
                                if (!firstInvalid) firstInvalid = $input.first();
                            }
                        } catch (e) {}
                    });
                });
                var $alert = $('<div class="alert alert-danger server-validation-alert"><strong>There were some problems with your input:</strong></div>');
                $alert.append($list); $('#receipt-form').prepend($alert);
                if (firstInvalid) { $('html,body').animate({ scrollTop: firstInvalid.offset().top - 100 }, 200); firstInvalid.focus(); }
            } else {
                // show more diagnostic info so the client can surface the server error
                var msg = 'An unexpected error occurred. Please try again.';
                try {
                    if (xhr.status) msg += ' (status: ' + xhr.status + ')';
                    if (xhr.responseText) {
                        // try to extract message from JSON, otherwise include raw text truncated
                        try {
                            var j = JSON.parse(xhr.responseText);
                            if (j.message) msg += ' - ' + j.message;
                        } catch(e) {
                            var txt = xhr.responseText.substring(0, 200);
                            msg += ' - ' + txt;
                        }
                    }
                } catch(e) {}
                var $alert = $('<div class="alert alert-danger server-validation-alert"></div>').text(msg);
                $('#receipt-form').prepend($alert);
            }
            // restore submit button so user can retry
            try { $submitBtn.prop('disabled', false).html(origBtnHtml); } catch(e) {}
        });
    });
    // ensure settlement status is visible on initial page load (after bindings)
    try { computeSettlement(); } catch(e) {}
});
</script>
@endunless
@endpush

@endsection
