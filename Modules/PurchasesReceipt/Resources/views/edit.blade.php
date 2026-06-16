@extends('layouts.app')

@section('content')
@php
    $isReadOnly = !empty($readonly);
    $totalAllocated = 0;
    foreach($receipt->lines as $line) { $totalAllocated += (float)($line->payment_amount ?? 0) + (float)($line->discount_amount ?? 0); }
    $receiptBalance = (float)($receipt->total_amount ?? 0) - $totalAllocated;
    $isSettled = abs($receipt->total_amount - $totalAllocated) < 0.01;
    // supplier opening balance display – stored value takes precedence
    $displaySupplierBalance = old('opening_balance');
    if ($displaySupplierBalance === null) {
        if (isset($receipt->supplier_balance_before) && $receipt->supplier_balance_before !== null) {
            $displaySupplierBalance = number_format($receipt->supplier_balance_before/100, 2, '.', '');
        } else {
            $displaySupplierBalance = optional($receipt->supplier)->open_balance ?? '';
        }
    }
    // Bill / Total balance: prefer the snapshot frozen at creation; fall back to the
    // supplier's live balance for older receipts saved before snapshots existed.
    $openNum = (float) str_replace(',', '', (string) $displaySupplierBalance);
    $billNum = (isset($receipt->bill_balance_before) && $receipt->bill_balance_before !== null)
        ? ($receipt->bill_balance_before / 100)
        : (float) (optional($receipt->supplier)->bill_balance ?? 0);
    $displayBillBalance = number_format($billNum, 2, '.', '');
    $displayTotalBalance = number_format($openNum + $billNum, 2, '.', '');
@endphp
<div class="container-fluid mb-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    @include('utils.alerts')
                    <h3>{{ $isReadOnly ? 'View Purchases Receipt' : 'Edit Purchases Receipt' }}</h3>

                    @if(!$isReadOnly)
                        <form method="POST" action="{{ route('purchases-receipts.update', $receipt->id) }}" id="receipt-form">
                            @csrf
                            @method('PATCH')
                    @else
                        {{-- readonly view: don't submit --}} 
                        <div id="receipt-form">
                    @endif

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
                                    <input type="date" class="form-control" name="date" value="{{ old('date', $receipt->date) }}" required @if($isReadOnly) disabled @endif>
    <script>function formatDateDmy(d){if(!d)return '';var p=d.split('-');return (p.length===3&&p[0].length===4)?(p[2]+'-'+p[1]+'-'+p[0]):d;}</script>
                                </div>
                                <div class="col-md-6">
                                    <label>Supplier</label>
                                    @if($isReadOnly)
                                        <input type="text" class="form-control" readonly value="{{ optional($receipt->supplier)->supplier_name ?? 'Selected Supplier' }}">
                                    @else
                                        <select id="supplier-select" name="supplier_id" class="form-control" required>
                                            {{-- preserve existing selection until select2 loads --}}
                                            <option value="{{ old('supplier_id', $receipt->supplier_id) }}" selected>{{ old('supplier_name', optional($receipt->supplier)->supplier_name ?? 'Selected Supplier') }}</option>
                                        </select>
                                    @endif
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-3">
                                    <label>Area</label>
                                    <input type="text" id="area" class="form-control" readonly value="{{ optional($receipt->supplier)->area ?? '' }}">
                                </div>
                                <div class="col-md-3">
                                    <label>Open Balance</label>
                                    <input type="text" id="opening_balance" name="opening_balance" class="form-control" readonly value="{{ $displaySupplierBalance }}">
                                </div>
                                <div class="col-md-3">
                                    <label>Bill Balance</label>
                                    <input type="text" id="bill_balance_display" class="form-control" readonly value="{{ $receipt->supplier_id ? $displayBillBalance : '0.00' }}">
                                </div>
                                <div class="col-md-3">
                                    <label>Total Balance</label>
                                    <input type="text" id="total_balance_display" class="form-control" readonly value="{{ $receipt->supplier_id ? $displayTotalBalance : '0.00' }}">
                                </div>
                                <div class="col-md-2">
                                    <label>Excess</label>
                                    <input type="text" id="excess_amount" class="form-control" readonly value="{{ optional($receipt->supplier)->excess_amount ?? '' }}">
                                </div>
                                <div class="col-md-4">
                                    <label>Particular</label>
                                    <input type="text" name="particular" class="form-control" maxlength="100" value="{{ old('particular', $receipt->particular) }}" @if($isReadOnly) disabled @endif>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-3">
                                    <label>Receipt Amount <span class="text-danger">*</span></label>
                                    <input type="text" id="receipt_amount_display" class="form-control currency-input" data-target="#receipt_amount_raw" maxlength="15" placeholder="0.00" value="{{ number_format($receipt->total_amount, 2, '.', '') }}" inputmode="decimal" @if($isReadOnly) disabled @endif>
                                    <input type="hidden" name="amount" id="receipt_amount_raw" value="{{ old('amount', number_format($receipt->total_amount, 2, '.', '')) }}">
                                </div>
                                <div class="col-md-3">
                                    <label>Receipt Balance <span class="text-danger">*</span></label>
                                    <input type="text" id="receipt_balance" class="form-control" maxlength="15" readonly placeholder="0.00" value="{{ $isReadOnly ? number_format($receiptBalance, 2, '.', '') : '' }}" inputmode="decimal">
                                </div>
                                <div class="col-md-3">
                                    <label>Payment Mode <span class="text-danger">*</span></label>
                                    <select name="payment_mode" id="payment_mode" class="form-control" required @if($isReadOnly) disabled @endif>
                                        <option value="">-- Select Payment Mode --</option>
                                        <option value="Cash" {{ old('payment_mode', $receipt->payment_mode) == 'Cash' ? 'selected' : '' }}>Cash</option>
                                        <option value="Cheque" {{ old('payment_mode', $receipt->payment_mode) == 'Cheque' ? 'selected' : '' }}>Cheque</option>
                                        <option value="Cards" {{ old('payment_mode', $receipt->payment_mode) == 'Cards' ? 'selected' : '' }}>Cards</option>
                                        <option value="Bank Transfer" {{ old('payment_mode', $receipt->payment_mode) == 'Bank Transfer' ? 'selected' : '' }}>Bank Transfer</option>
                                        <option value="UPI Payments" {{ old('payment_mode', $receipt->payment_mode) == 'UPI Payments' ? 'selected' : '' }}>UPI Payments</option>
                                        <option value="Product return" {{ old('payment_mode', $receipt->payment_mode) == 'Product return' ? 'selected' : '' }}>Product return</option>
                                    </select>
                                </div>
                                <div class="col-md-3"></div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-12">
                                    @if($isReadOnly)
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="apply_to_opening" id="apply_to_opening" value="1" {{ old('apply_to_opening') ? 'checked' : ((isset($receipt) && ($receipt->applied_to_supplier ?? 0) > 0) ? 'checked' : '') }} disabled>
                                            <label class="form-check-label" for="apply_to_opening">Apply to Open Balance</label>
                                        </div>
                                    @else
                                        <div id="apply_to_opening_container" style="display:none">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="apply_to_opening" id="apply_to_opening" value="1" {{ old('apply_to_opening') ? 'checked' : ((isset($receipt) && ($receipt->applied_to_supplier ?? 0) > 0) ? 'checked' : '') }}>
                                                <label class="form-check-label" for="apply_to_opening">Apply to Open Balance</label>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{-- Bills will be listed here when a supplier is selected; user can add rows to the receipt table --}}
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
                        @endunless

                        <table class="table table-bordered" id="bills-table">
                            <thead>
                                <tr>
                                    <th>Bill Ref No</th>
                                    <th>Bill Date</th>
                                    <th>Bill Amount</th>
                                    <th>Paid Amount</th>
                                    <th>Balance Amount</th>
                                    <th>Payment Amount</th>
                                    <th>Final Balance</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($receipt->lines as $line)
                                    <tr @if(empty($line->purchase_id)) class="opening-row" @endif>
                                        <td>{{ $line->bill_ref }}</td>
                                        <td>{{ ($line->bill_date && $line->bill_date !== '-' && strtotime($line->bill_date) !== false) ? \Carbon\Carbon::parse($line->bill_date)->format('d-m-Y') : '' }}</td>
                                        <td class="bill-amount">{{ number_format($line->bill_amount, 2, '.', '') }}</td>
                                        <td class="paid-before">{{ number_format($line->paid_before, 2, '.', '') }}</td>
                                        <td class="balance-before">{{ number_format($line->balance_before, 2, '.', '') }}</td>
                                        <td>
                                            <input type="text" class="form-control currency-input payment-amount-display" data-target="#lines_{{ $loop->index }}_payment_raw" inputmode="decimal" maxlength="15" value="{{ number_format($line->payment_amount, 2, '.', '') }}" @if($isReadOnly) disabled readonly @endif>
                                            <input type="hidden" id="lines_{{ $loop->index }}_payment_raw" name="lines[{{ $loop->index }}][payment_amount]" class="payment-amount" value="{{ number_format($line->payment_amount, 2, '.', '') }}">
                                            @if($errors->has("lines.{$loop->index}.payment_amount"))
                                                <small class="text-danger">{{ $errors->first("lines.{$loop->index}.payment_amount") }}</small>
                                            @endif
                                        </td>
                                        <td class="final-balance">{{ number_format($line->final_balance, 2, '.', '') }}</td>
                                        @unless($isReadOnly)
                                            <td><button type="button" class="btn btn-sm btn-danger remove-row">Remove</button></td>
                                        @else
                                            <td></td>
                                        @endunless
                                        <input type="hidden" name="lines[{{ $loop->index }}][purchase_id]" value="{{ $line->purchase_id }}">
                                        <input type="hidden" name="lines[{{ $loop->index }}][is_settled]" value="{{ $line->is_settled ? '1' : '0' }}" class="settled-hidden">
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>

                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="form-check">
                                    @php
                                        if ($receipt->lines->isNotEmpty()) {
                                            $allSettled = $receipt->lines->every(function($l){ return ($l->is_settled ?? false); });
                                        } elseif ($receipt->applied_to_supplier > 0) {
                                            $applied = intval($receipt->applied_to_supplier ?? 0);
                                            $totalPaise = intval(round(floatval($receipt->total_amount ?? 0) * 100));
                                            $allSettled = $applied >= $totalPaise;
                                        } else {
                                            $allSettled = false;
                                        }
                                    @endphp
                                    <input class="form-check-input" type="checkbox" id="global_settled" {{ $isSettled ? 'checked' : '' }} @if($isReadOnly) disabled @endif>
                                    <label class="form-check-label" for="global_settled">@if($isReadOnly) {{ $isSettled ? 'Settled (Amount properly distributed)' : 'Not Settled (Amount mismatch)' }} @else Settlement Status (Auto-calculated) @endif</label>
                                </div>
                            </div>
                            <div>
                                <a href="{{ route('purchases-receipts.index') }}" class="btn btn-secondary mr-2">Back</a>
                                @unless($isReadOnly)
                                    <button class="btn btn-primary" type="submit">Update Receipt</button>
                                @endunless
                            </div>
                        </div>
                    @if(!$isReadOnly)
                        </form>
                    @else
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

@push('page_scripts')
@if(!empty($isReadOnly) && $isReadOnly)
    {{-- Readonly view: skip page scripts to prevent interactive behavior --}}
@else
<script src="{{ asset('js/currency-input.js') }}"></script>
<script>
$(function(){
    // populate supplier select via AJAX (simple select2 if available)
    console.debug('init: supplier-select script running');

    // stored balance (string) for existing receipt – shown only for the original supplier
    var storedSupplierBalance = '{{ isset($receipt->supplier_balance_before) && $receipt->supplier_balance_before !== null ? number_format($receipt->supplier_balance_before/100,2,'.','') : '' }}';
    var currentSupplierId = '{{ $receipt->supplier_id ?? '' }}';

    // helper to parse numbers robustly from formatted displays or raw fields
    function parseNumber(v) {
        if (v === null || v === undefined) return 0;
        var s = v.toString();
        // strip commas and spaces
        s = s.replace(/[,\s]+/g, '');
        // keep only digits, dot and minus
        s = s.replace(/[^0-9.\-]/g, '');
        var n = parseFloat(s);
        return isNaN(n) ? 0 : n;
    }

    // Read numeric value from a table row with fallback: hidden raw -> dataset.raw -> display value
    function readNumericFromRow($row, hiddenSelector, displaySelector) {
        try {
            var hv = $row.find(hiddenSelector).val();
            if (hv !== undefined && hv !== null && hv !== '') return parseNumber(hv);
        } catch(e) {}
        try {
            var $d = $row.find(displaySelector).first();
            if ($d && $d.length) {
                var ds = ($d.get(0) && $d.get(0).dataset) ? $d.get(0).dataset.raw : null;
                if (ds) return parseNumber(ds);
                return parseNumber($d.val());
            }
        } catch(e) {}
        return 0;
    }

    // Re-index line input names/ids after add/remove so names remain contiguous.
    // Reason: keeps `lines[0]..lines[n]` sequential so Laravel request parsing,
    // validation mapping and DOM id/data-target bindings work reliably after
    // rows are removed or inserted. We call this after add/remove and before submit.
    function reindexLines() {
        $('#bills-table tbody tr').each(function(i){
            var $tr = $(this);
            // update name attributes like lines[<n>][...]
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
                // update data-target attributes that reference ids
                var dt = $el.attr('data-target');
                if (dt && dt.indexOf('#lines_') === 0) {
                    var newDt = dt.replace(/#lines_\d+_/, '#lines_'+i+'_');
                    $el.attr('data-target', newDt);
                }
            });
            // ensure hidden purchase_id has correct name
            var $hid = $tr.find('input[type=hidden][name$="[purchase_id]"]');
            if ($hid.length) $hid.attr('name', 'lines['+i+'][purchase_id]');
            // settled flag
            var $sett = $tr.find('input.settled-hidden');
            if ($sett.length) $sett.attr('name', 'lines['+i+'][is_settled]');
        });
    }

    // in-memory map of available bills returned for current supplier
    var availableBillsMap = {};
    var billsRequest; // holds current AJAX request when loading bills, used to abort stale requests
    var billsReady = true; // becomes false while purchases API is loading; prevents transient incorrect calculations

    // helper to load supplier details and available bills (used on select, initial load and when a row
    // is removed so the server can include it in results)
    function loadSupplierDetails(id, extraIncludeIds) {
        if (!id) return;

        // abort previous request if still pending to avoid race conditions that cause duplicated rows
        if (billsRequest && typeof billsRequest.abort === 'function') {
            try { billsRequest.abort(); } catch(e) {}
        }
        $('#available-bills-table tbody').empty();
        availableBillsMap = {};
        $('#available-bills .loading-spinner').show();

        // gather purchase ids currently present in the receipt table so the server can include them
        var presentIds = [];
        $('#bills-table tbody input[type=hidden][name$="[purchase_id]"]').each(function(){ presentIds.push($(this).val()); });
        // merge any extra ids (for example the id of a row just removed so server will include it)
        if (Array.isArray(extraIncludeIds)) {
            extraIncludeIds.forEach(function(i){ if (i && presentIds.indexOf(i.toString()) === -1) presentIds.push(i.toString()); });
        }

        billsReady = false;
        var supReq = $.get('{{ url('api/suppliers') }}/' + id);
        var billsReq = $.get('{{ route('purchasesreceipts.purchases.search') }}', { supplier_id: id, include_ids: presentIds, receipt_id: '{{ $receipt->id ?? '' }}' });
        billsRequest = billsReq;

        $.when(supReq, billsReq).done(function(supResp, billsResp){
            var resp = supResp[0];
            var res = billsResp[0];

            $('#area').val(resp.area || '');
            if (storedSupplierBalance && currentSupplierId && id.toString() === currentSupplierId.toString()) {
                $('#opening_balance').val(storedSupplierBalance);
            } else {
                $('#opening_balance').val(resp.open_balance_formatted || resp.open_balance || '');
                $('#bill_balance_display').val(resp.bill_balance_formatted !== undefined ? resp.bill_balance_formatted : '0.00');
                $('#total_balance_display').val(resp.total_balance_formatted !== undefined ? resp.total_balance_formatted : '0.00');
            }

            var results = res.results || [];
                var results = res.results || [];
                // dedupe to be defensive
                var seen = {};
                if (results.length) {
                    results.forEach(function(p){
                        if (!p || !p.id) return;
                        if (seen[p.id]) return; seen[p.id] = true;
                        // defensive: skip if a row for this id already exists in the table
                        if ($('#available-bills-table tbody tr[data-id="' + p.id + '"]').length) return;
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
                // after appending rows, mark any bills already present in the receipt table as added
                var presentMap = {};
                $('#bills-table tbody input[type=hidden][name$="[purchase_id]"]').each(function(){ presentMap[$(this).val()] = true; });
                Object.keys(presentMap).forEach(function(id){
                    var $btn = $('#available-bills-table').find('button.add-bill[data-id="' + id + '"]');
                    if ($btn.length) { $btn.prop('disabled', true).text('Added').removeClass('btn-primary').addClass('btn-secondary'); }
                });

                // Mark bills as ready — it's now safe to compute settlement and update UI
                billsReady = true;

                // Sync any existing rows in the receipt table with authoritative values
                // returned by the purchases API so client-side final-balance calculation
                // uses current paid/due values instead of stale stored values.
                try {
                    $('#bills-table tbody tr').each(function(){
                        var $r = $(this);
                        var pid = $r.find('input[type=hidden][name$="[purchase_id]"]').val();
                        if (pid && availableBillsMap[pid]) {
                            var p = availableBillsMap[pid];
                            if (p.total_amount !== undefined) $r.find('.bill-amount').text(formatAmount(p.total_amount));
                            if (p.paid_amount !== undefined) $r.find('.paid-before').text(formatAmount(p.paid_amount));
                            if (p.due_amount !== undefined) $r.find('.balance-before').text(formatAmount(p.due_amount));
                        }
                    });
                } catch(e) { console.debug('Failed to sync existing purchase rows with API', e); }

                // Show or hide apply-to-opening checkbox: only show when no bills and supplier has an opening balance
                if (parseFloat($('#opening_balance').val() || '0') > 0) {
                    $('#apply_to_opening_container').show();
                } else {
                    $('#apply_to_opening_container').hide();
                    $('#apply_to_opening').prop('checked', false);
                    $('#bills-table tbody tr.opening-row').remove();
                    // update settlement now that authoritative balances are present
                    updateSettlementStatus();
                }
            })
            .always(function(){ $('#available-bills .loading-spinner').hide(); billsRequest = null; });
    }

    // quick debug ping to see if API is reachable from the page (use safe endpoint)
    $.getJSON('{{ route('api.suppliers.search') }}', { q: '' })
        .done(function(resp) { console.debug('debug: /api/suppliers/search ok', resp); })
        .fail(function(xhr) { console.warn('debug: /api/suppliers/search failed', xhr.status, xhr.responseText); });

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

    // When supplier is selected via select2, load bills similarly to initial load
    $('#supplier-select').on('select2:select', function(e){
        var id = e.params.data.id;
        // if the supplier has changed, drop the stored balance
        if (currentSupplierId && id.toString() !== currentSupplierId.toString()) {
            storedSupplierBalance = '';
        }
        currentSupplierId = id;
        loadSupplierDetails(id);
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

    // log when Select2 opens (helps confirm the widget initialized)
    $('#supplier-select').on('select2:opening select2:open', function(){ console.debug('select2: opening for #supplier-select'); });

    // Load available bills for the initially selected supplier (for edit)
    var selectedSupplier = $('#supplier-select').val();
    if (selectedSupplier) {
        loadSupplierDetails(selectedSupplier);
    }


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

        // payment amount - leave blank by default (match Sales Receipt behavior)
        // create visible currency display + hidden raw input for payment
        var paymentDisplay = $('<input>', {
            type: 'text',
            'class': 'form-control currency-input payment-amount-display',
            'data-target': '#lines_' + idx + '_payment_raw',
            inputmode: 'decimal',
            maxlength: 15,
            required: true,
            value: '',
            placeholder: '0.00'
        });
        var paymentRaw = $('<input>', {
            type: 'hidden',
            id: 'lines_' + idx + '_payment_raw',
            name: 'lines[' + idx + '][payment_amount]',
            'class': 'payment-amount'
        }).val('');
        tr.append($('<td>').append(paymentDisplay).append(paymentRaw));

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

        // initialize currency bindings for newly added inputs
        currencyInputInit();
        // ensure newly added payment fields are empty (do not inherit previous values)
        var $newRow = $('#bills-table tbody tr').last();
        var $paymentDisplay = $newRow.find('.payment-amount-display');
        var $paymentRaw = $newRow.find('.payment-amount');
        try {
            $paymentRaw.val('');
            $paymentDisplay.val('');
            // clear dataset raw used by currency input so it doesn't read stale hidden values
            $paymentDisplay.each(function(){ try{ this.dataset.raw = ''; } catch(e){} });
        } catch(e) {}
        // trigger input on the hidden raw field (matches SalesReceipt behavior) so calculations run
        try { $paymentRaw.trigger('input'); } catch(e) {}
        // reindex lines so names/ids remain contiguous
        try { reindexLines(); } catch(e) {}
        // update settlement status after adding new row
        updateSettlementStatus();
        // focus the visible payment display so user can type immediately
        try { $paymentDisplay.first().focus(); } catch(e) {}
    }

    // Ensure an opening-balance synthetic row is present when the checkbox is checked and there are no bill rows
    // For edit, we also need to consider the applied_to_supplier value from the server
    var appliedToSupplier = {{ isset($receipt) && $receipt->applied_to_supplier ? $receipt->applied_to_supplier / 100 : 'null' }};

    function ensureOpeningRow() {
        var checked = $('#apply_to_opening').is(':checked');
        if (!checked) {
            $('#bills-table tbody tr.opening-row').remove();
            try { reindexLines(); } catch(e) {}
            updateSettlementStatus();
            return;
        }
        // An opening line may already be present (server-rendered on edit) — detect it by
        // its empty purchase_id so we never add a duplicate.
        var $existingOpening = $('#bills-table tbody tr').filter(function(){
            if ($(this).hasClass('opening-row')) return true;
            var v = $(this).find('input[type=hidden][name$="[purchase_id]"]').val();
            return (v === '' || v === undefined || v === null);
        });
        if ($existingOpening.length > 0) { $existingOpening.addClass('opening-row'); updateSettlementStatus(); return; }

        var openingBal = parseNumber($('#opening_balance').val() || '0') || 0;
        var receiptAmt = parseNumber($('#receipt_amount_raw').val() || '0') || 0;
        var billAlloc = 0;
        $('#bills-table tbody tr').each(function(){
            billAlloc += (parseFloat($(this).find('.payment-amount').val()) || 0) + (parseFloat($(this).find('.discount-amount').val()) || 0);
        });
        var openingPayment = Math.max(0, Math.min(receiptAmt - billAlloc, openingBal));
        try {
            if (typeof appliedToSupplier !== 'undefined' && appliedToSupplier !== null) {
                var ap = parseFloat(appliedToSupplier) || 0;
                if (ap > 0) openingPayment = ap;
            }
        } catch(e) {}
        var payVal = openingPayment > 0 ? openingPayment.toFixed(2) : '';

        var tr = $('<tr>').addClass('opening-row');
        tr.append($('<td>').text('Opening Balance'));
        tr.append($('<td>').text('-'));
        tr.append($('<td class="bill-amount">').text((0).toFixed(2)));
        tr.append($('<td class="paid-before">').text('0.00'));
        tr.append($('<td class="balance-before">').text(openingBal.toFixed(2)));
        // non-numeric id suffix so reindexLines (which renumbers names) leaves id/data-target intact
        var paymentDisplay = $('<input>', { type: 'text', id: 'lines_open_payment_display', 'class': 'form-control currency-input payment-amount-display', 'data-target': '#lines_open_payment_raw', inputmode: 'decimal', maxlength: 15, value: payVal });
        var paymentRaw = $('<input>', { type: 'hidden', id: 'lines_open_payment_raw', name: 'lines[0][payment_amount]', 'class': 'payment-amount' }).val(payVal);
        tr.append($('<td>').append(paymentDisplay).append(paymentRaw));
        tr.append($('<td class="final-balance">').text((openingBal - openingPayment).toFixed(2)));
        tr.append($('<td>').html('<button type="button" class="btn btn-sm btn-danger remove-row">Remove</button>'));
        tr.append($('<input>', { type: 'hidden', name: 'lines[0][purchase_id]' }).val(''));
        $('#bills-table tbody').append(tr);
        try { reindexLines(); } catch(e) {}
        try { currencyInputInit(); } catch(e) {}
        updateSettlementStatus();
    }

    // react to checkbox and receipt amount changes
    $('#apply_to_opening').on('change', function(){
        ensureOpeningRow();
    });
    // react to changes on hidden raw receipt value (currency-input updates this)
    $('#receipt_amount_raw').on('input change blur', function(){
        // When receipt amount changes, only recalculate settlement — do not change bill amounts.
        updateSettlementStatus();
    });

    // delegate events for payment/discount inputs (listen on visible displays but compute using raw hidden values)
    $('#bills-table').on('input', '.payment-amount-display, .discount-amount-display', function(){
        if (!billsReady) return; // avoid using stale balances before API sync
        var row = $(this).closest('tr');
        // Use Balance Amount (balance-before) when computing final balance
        var balanceBefore = parseNumber(row.find('.balance-before').text()) || 0;
        var pay = readNumericFromRow(row, '.payment-amount', '.payment-amount-display');
        var disc = readNumericFromRow(row, '.discount-amount', '.discount-amount-display');
        var finalBal = balanceBefore - (pay + disc);
        row.find('.final-balance').text(finalBal.toFixed(2));
        // Update settlement status when payment/discount amounts change
        updateSettlementStatus();
    });

    $('#bills-table').on('click', '.remove-row', function(){
        var $tr = $(this).closest('tr');
        var purchaseId = $tr.find('input[type=hidden][name$="[purchase_id]"]').val();

        // capture display values in case we need a local fallback
        var refText = $tr.find('td').eq(0).text() || '';
        var dateText = $tr.find('td').eq(1).text() || '';
        var billAmountText = $tr.find('td').eq(2).text() || '';
        var paidBeforeText = $tr.find('td').eq(3).text() || '';
        var balanceBeforeText = $tr.find('td').eq(4).text() || '';

        $tr.remove();

        if (purchaseId) {
            var $btn = $('#available-bills-table').find('button.add-bill[data-id="' + purchaseId + '"]');
            if ($btn.length) {
                // bill row still present in the available list; just re-enable the button
                $btn.prop('disabled', false).text('Add').removeClass('btn-secondary').addClass('btn-primary');
            } else {
                // the available list does not currently contain this bill – ask server to return the authoritative row
                var currentSupplier = $('#supplier-select').val();
                if (currentSupplier) {
                    loadSupplierDetails(currentSupplier, [purchaseId]);
                } else {
                    // fallback to local rebuild if supplier not selected
                    // avoid adding a duplicate row if it already exists
                    if (!$('#available-bills-table tbody tr[data-id="' + purchaseId + '"]').length) {
                        var $new = $('<tr>').attr('data-id', purchaseId);
                        $new.append($('<td>').text(refText));
                        $new.append($('<td>').text(dateText));
                        $new.append($('<td>').text(billAmountText));
                        $new.append($('<td>').text(paidBeforeText));
                        $new.append($('<td>').text(balanceBeforeText));
                        var $addBtn = $('<button>', { type: 'button', 'class': 'btn btn-sm btn-primary add-bill', 'data-id': purchaseId }).text('Add');
                        $new.append($('<td>').append($addBtn));
                        $('#available-bills-table tbody').append($new);
                        availableBillsMap[purchaseId] = {
                            id: purchaseId,
                            reference: refText,
                            date: dateText,
                            total_amount: billAmountText,
                            paid_amount: paidBeforeText,
                            due_amount: balanceBeforeText
                        };
                    }
                }
            }
        }

    // reindex lines after removal
    try { reindexLines(); } catch(e) {}
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

    // sanitize numeric inputs on-the-fly for visible displays
    $('#receipt-form').on('input', '#receipt_amount_display, .payment-amount-display, .discount-amount-display', function(){
        var v = $(this).val() || '';
        var clean = v.replace(/[^0-9\.]/g, '');
        var parts = clean.split('.');
        if (parts.length > 2) clean = parts.shift() + '.' + parts.join('');
        // enforce maxlength if present (character count including dot)
        var max = parseInt($(this).attr('maxlength') || '15', 10) || 15;
        if (clean.length > max) clean = clean.substring(0, max);
        $(this).val(clean);
        // sync to raw hidden if data-target exists
        try {
            var tgt = $(this).data('target');
            if (tgt) { $(tgt).val(clean); }
        } catch(e){}
    });

    // format numeric fields to 2 decimals on blur for visible displays and sync raw
    $('#receipt-form').on('blur', '#receipt_amount_display, .payment-amount-display, .discount-amount-display', function(){
        var v = ($(this).val() || '').toString();
        v = v.replace(/[^0-9\.]/g, '');
        if (v === '') return;
        var n = parseFloat(v);
        if (isNaN(n)) n = 0;
        var s = n.toFixed(2);
        var max = parseInt($(this).attr('maxlength') || '15', 10) || 15;
        if (s.length > max) s = s.substring(0, max);
        $(this).val(s);
        try { var tgt = $(this).data('target'); if (tgt) { $(tgt).val(s).trigger('input'); } } catch(e){}
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
        var amtNum = parseNumber($('#receipt_amount_raw').val() || '0');
        if (amtNum <= 0) {
            firstInvalid = firstInvalid || $('#receipt_amount_display');
            $('#receipt_amount_display').addClass('is-invalid');
        } else {
            $('#receipt_amount_display').removeClass('is-invalid');
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
            var $paymentRaw = $row.find('.payment-amount');
            var $paymentDisplay = $row.find('.payment-amount-display');
            if ($paymentRaw.length) {
                var raw = ($paymentRaw.val() || '').toString();
                var num = parseNumber(raw || '0');
                if (isNaN(num) || num <= 0) {
                    $row.addClass('table-warning');
                    $paymentDisplay.addClass('is-invalid');
                    if (!firstInvalid) firstInvalid = $paymentDisplay;
                } else {
                    $row.removeClass('table-warning');
                    $paymentDisplay.removeClass('is-invalid');
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

        // sanitize raw numeric inputs (format to 2 decimals)
        $('.payment-amount, .discount-amount').each(function(){
            var v = $(this).val();
            v = v.replace(/[^0-9\.]/g, '');
            var num = parseFloat(v || 0);
            if (isNaN(num)) num = 0;
            $(this).val(num.toFixed(2));
        });

        // perform AJAX submit to avoid full page refresh on server validation errors
        var $form = $(this);
        var url = $form.attr('action');
        // ensure per-line settled flags reflect the global checkbox before serializing
        $('.settled-hidden').val($('#global_settled').is(':checked') ? '1' : '0');
        // ensure contiguous indices before serializing
        try { reindexLines(); } catch(e) {}
        var data = $form.serialize();

        // UX: show loading state on submit button so user knows request is in progress
        var $submitBtn = $form.find('button[type=submit]').first();
        var origBtnHtml = $submitBtn.html();
        $submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Updating...');

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
            // restore submit button so user can retry
            try { $submitBtn.prop('disabled', false).html(origBtnHtml); } catch(e) {}
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
        if (!billsReady) return false; // skip until authoritative balances are available
        var receiptAmount = parseNumber($('#receipt_amount_raw').val() || '0');
        var totalAllocated = 0;
        var invalidAllocation = false;

        // Calculate total allocated amount from all rows (use hidden raw values)
        $('#bills-table tbody tr').each(function(){
            var $row = $(this);
            var paymentAmt = readNumericFromRow($row, '.payment-amount', '.payment-amount-display');
            var discountAmt = readNumericFromRow($row, '.discount-amount', '.discount-amount-display');

            // compute final balance using balance-before (not gross bill amount)
            var balanceBefore = parseNumber($row.find('.balance-before').text()) || 0;
            var finalBal = balanceBefore - (paymentAmt + discountAmt);
            $row.find('.final-balance').text(finalBal.toFixed(2));

            // Both payment and discount reduce the receipt's available amount; include discounts in allocation.
            totalAllocated += (paymentAmt + discountAmt);

            // highlight rows where a single allocated amount (payment + discount) exceeds the receipt amount
            if ((paymentAmt + discountAmt) > receiptAmount + 0.0001) {
                invalidAllocation = true;
                $row.addClass('table-danger');
            } else {
                $row.removeClass('table-danger');
            }
        });

        // Calculate and display receipt balance
        var receiptBalance = receiptAmount - totalAllocated;
        $('#receipt_balance').val((isNaN(receiptBalance) ? 0 : receiptBalance).toFixed(2));

        // Check if amounts match (allowing for minor rounding differences)
        var isSettled = Math.abs(receiptAmount - totalAllocated) < 0.01;

        // Update global checkbox to reflect calculated settlement status
        $('#global_settled').prop('checked', isSettled);

        // Update all hidden settlement flags
        $('.settled-hidden').val(isSettled ? '1' : '0');

        // Update visual feedback
        var settledText = isSettled ? 'Settled (Amount properly distributed)' : 'Not Settled (Amount mismatch)';
        var settledClass = isSettled ? 'text-success' : 'text-warning';

        if (!$('#settlement-status').length) {
            $('#global_settled').parent().after('<div id="settlement-status" class="small"></div>');
        }
        $('#settlement-status').text(settledText).removeClass('text-success text-warning').addClass(settledClass);

        // Show warning and disable submit when allocations exceed receipt
        var $submit = $('#receipt-form').find('button[type=submit]').first();
        if (invalidAllocation || totalAllocated > receiptAmount + 0.0001) {
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

    // Update settlement status when hidden receipt raw changes
    $('#receipt_amount_raw').on('input change blur', function(){
        updateSettlementStatus();
    });

    // Update settlement status when payment or discount raw amounts change
    $('#bills-table').on('input blur', '.payment-amount, .discount-amount', function(){
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

    // Initialize settlement status on page load
    $(document).ready(function() {
        // If receipt was previously applied to opening (lineless), ensure opening row is shown
        if ($('#apply_to_opening').is(':checked') && $('#bills-table tbody tr').length === 0) {
            ensureOpeningRow();
        }
        // initialize currency inputs (visible displays -> hidden raw)
        try { currencyInputInit(); } catch(e) {}
        updateSettlementStatus();
    });
});
</script>
@endif
@endpush
@endsection