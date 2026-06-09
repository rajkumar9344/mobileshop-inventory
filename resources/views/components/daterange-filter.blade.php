@php
    $tableId    = $tableId    ?? 'items-table';
    $totalsRoute = $totalsRoute ?? '';
    $label      = $label      ?? null;
@endphp

{{-- Inject z-index fix only once even if this component is included multiple times --}}
@once
@push('page_css')
    <style>
        /* Ensure daterangepicker overlays the layout (sidebar/header) */
        .daterangepicker { z-index: 3000 !important; }
    </style>
@endpush
@endonce

{{-- Shared input-group markup (used in both wrapper and no-wrapper modes) --}}
@php
    $inputGroup = '
        <div class="input-group-prepend">
            <span class="input-group-text"><i class="bi bi-calendar3"></i></span>
        </div>
        <input type="text" id="filter-daterange-' . $tableId . '" class="form-control" placeholder="Select date range">
        <input type="hidden" id="filter-start-' . $tableId . '">
        <input type="hidden" id="filter-end-' . $tableId . '">
        <div class="input-group-append">
            <button id="apply-filters-' . $tableId . '" class="btn btn-primary" type="button"><i class="bi bi-check2"></i> Apply</button>
            <button id="clear-filters-' . $tableId . '" class="btn btn-outline-danger" type="button"><i class="bi bi-arrow-clockwise"></i> Clear</button>
        </div>
    ';
@endphp

@if(empty($noWrapper))
<div class="p-3 bg-light rounded filter-panel d-flex flex-wrap align-items-center gap-1">
    @if($label)
        <label for="filter-daterange-{{ $tableId }}" class="mr-2 font-weight-bold">{{ $label }}</label>
    @endif
    <div class="input-group" style="max-width:520px; width:100%;">
        {!! $inputGroup !!}
    </div>
    <div class="w-100 filter-shortcuts" style="margin-top:4px;">
        <small class="text-muted">Quick: select a preset or pick custom range</small>
    </div>
</div>
@else
    @if($label)
        <label for="filter-daterange-{{ $tableId }}" class="mr-2 font-weight-bold">{{ $label }}</label>
    @endif
    <div class="input-group" style="max-width:520px; width:100%;">
        {!! $inputGroup !!}
    </div>
@endif

@push('page_scripts')
    <script>
        (function($){
            var tableSelector = '#{{ $tableId }}';
            var totalsUrl = '{{ $totalsRoute }}';

            function formatINR(v){
                return new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' }).format(v);
            }

            function fetchTotals(){
                var start = $('#filter-start-{{ $tableId }}').val();
                var end = $('#filter-end-{{ $tableId }}').val();
                if (!totalsUrl) return;

                // Collect extra DataTable parameters (search term, custom filters) when available
                var extra = {};
                if ($.fn.dataTable.isDataTable(tableSelector)) {
                    try {
                        var api = $(tableSelector).DataTable();
                        var params = api.ajax.params() || {};
                        if (params.search && params.search.value) {
                            extra.search = params.search.value;
                        }
                        // include simple named filters if present (customer_id, supplier_id, etc.)
                        ['customer_id','supplier_id','payment_mode'].forEach(function(k){
                            if (typeof params[k] !== 'undefined' && params[k] !== null && params[k] !== '') {
                                extra[k] = params[k];
                            }
                        });
                    } catch (e) {
                        // ignore
                    }
                }

                var data = Object.assign({ start_date: start, end_date: end }, extra);

                $.getJSON(totalsUrl, data, function(data){
                    // Basic counts/totals
                    $('#totals-count').text(data.overall_count || data.count || 0);
                    // Supplier count (purchases page)
                    if (typeof data.overall_supplier_count !== 'undefined' || typeof data.supplier_count !== 'undefined') {
                        $('#totals-supplier-count').text(data.overall_supplier_count || data.supplier_count || 0);
                    }
                    // Per-mode amounts (new fields)
                    $('#totals-cheque').text(formatINR(data.overall_cheque_amount || data.cheque || 0));
                    $('#totals-cash').text(formatINR(data.overall_cash_amount || data.cash || 0));
                    $('#totals-card').text(formatINR(data.overall_card_amount || data.card || 0));
                    $('#totals-upi').text(formatINR(data.overall_upi_amount || 0));
                    $('#totals-bank').text(formatINR(data.overall_bank_amount || 0));
                    $('#totals-product-return').text(formatINR(data.overall_product_return_amount || 0));

                    // Total / Received / Balance
                    var totalAmt = data.overall_total_amount || 0;
                    var received = data.overall_received_amount || data.total || 0;
                    var balance = (data.overall_balance !== undefined) ? data.overall_balance : (totalAmt - received);
                    // populate both legacy and new IDs for compatibility
                    $('#totals-amount').text(formatINR(totalAmt));
                    $('#totals-received').text(formatINR(received));
                    $('#totals-total').text(formatINR(received));
                    $('#totals-balance').text(formatINR(balance));
                    $('#totals-bill-balance').text(formatINR(balance));
                    $('#totals-paid').text(formatINR(data.overall_paid_amount || 0));

                    // Overall Open Balance (new) - support both `overall_open_balance` and legacy `open_balance`
                    $('#totals-open-balance').text(formatINR(data.overall_open_balance || data.open_balance || 0));
                    // Some pages may use a different id, populate both for compatibility
                    $('#totals-open').text(formatINR(data.overall_open_balance || data.open_balance || 0));

                    // keep tax placeholders (if present)
                    $('#totals-cgst').text(formatINR(data.overall_cgst || 0));
                    $('#totals-sgst').text(formatINR(data.overall_sgst || 0));
                    $('#totals-tax').text(formatINR(data.overall_tax_amount || 0));
                });
            }

            $(function(){
                var drEl = $('#filter-daterange-{{ $tableId }}');
                if (drEl.length && typeof drEl.daterangepicker === 'function') {
                    drEl.daterangepicker({
                        autoUpdateInput: false,
                        autoApply: true,
                        opens: 'right',
                        drops: 'down',
                        showDropdowns: true,
                        linkedCalendars: false,
                        alwaysShowCalendars: true,
                        showCustomRangeLabel: false,
                        locale: { 
                            cancelLabel: 'Clear',
                            applyLabel: 'Apply',
                            format: 'DD/MM/YYYY'
                        },
                        ranges: {
                            'Today': [moment(), moment()],
                            'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                            'Last 7 Days': [moment().subtract(6, 'days'), moment()],
                            'Last 30 Days': [moment().subtract(29, 'days'), moment()],
                            'This Month': [moment().startOf('month'), moment().endOf('month')],
                            'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
                        }
                    });
                    // Single handler for date selection — avoids double-firing with autoApply + inline callback
                    drEl.on('apply.daterangepicker', function(ev, picker) {
                        try {
                            $('#filter-start-{{ $tableId }}').val(picker.startDate.format('YYYY-MM-DD'));
                            $('#filter-end-{{ $tableId }}').val(picker.endDate.format('YYYY-MM-DD'));
                            drEl.val(picker.startDate.format('DD/MM/YYYY') + ' - ' + picker.endDate.format('DD/MM/YYYY'));

                            if ($.fn.dataTable.isDataTable(tableSelector)) {
                                $(tableSelector).DataTable().ajax.reload();
                            }
                            fetchTotals();
                            $(document).trigger('daterange.updated');
                        } catch (e) { /* ignore */ }
                    });

                    drEl.on('cancel.daterangepicker', function(ev, picker) {
                        $(this).val('');
                        $('#filter-start-{{ $tableId }}').val('');
                        $('#filter-end-{{ $tableId }}').val('');
                        if ($.fn.dataTable.isDataTable(tableSelector)) {
                            $(tableSelector).DataTable().ajax.reload();
                        }
                        fetchTotals();
                        $(document).trigger('daterange.updated');
                    });

                    $('#apply-filters-{{ $tableId }}').on('click', function(e){
                        e.preventDefault();
                        if ($.fn.dataTable.isDataTable(tableSelector)) {
                            $(tableSelector).DataTable().ajax.reload();
                        }
                        fetchTotals();
                        $(document).trigger('daterange.updated');
                    });

                    $('#clear-filters-{{ $tableId }}').on('click', function(e){
                        e.preventDefault();
                        $('#filter-daterange-{{ $tableId }}').val('');
                        $('#filter-start-{{ $tableId }}').val('');
                        $('#filter-end-{{ $tableId }}').val('');
                        if ($.fn.dataTable.isDataTable(tableSelector)) {
                            $(tableSelector).DataTable().ajax.reload();
                        }
                        fetchTotals();
                        $(document).trigger('daterange.updated');
                    });

                    $(document).on('init.dt', function(e, settings){
                        try{
                            var api = new $.fn.dataTable.Api(settings);
                            if ($(api.table().node()).attr('id') === tableSelector.replace('#','')){
                                api.on('preXhr.dt', function(e, settings, data){
                                    data.start_date = $('#filter-start-{{ $tableId }}').val() || null;
                                    data.end_date = $('#filter-end-{{ $tableId }}').val() || null;
                                });

                                // When the table's global search changes, refresh the totals so the summary matches
                                api.on('search.dt', function(){
                                    fetchTotals();
                                });

                                // Also refresh totals after redraw (covers column filters, paging changes)
                                api.on('draw', function(){
                                    fetchTotals();
                                });
                            }
                        }catch(err){/* ignore */}
                    });

                    fetchTotals();
                }
            });
        })(jQuery);
    </script>
@endpush
