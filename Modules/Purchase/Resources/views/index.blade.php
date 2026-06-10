@extends('layouts.app')

@section('title', 'Purchases')

@section('third_party_stylesheets')
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.25/css/dataTables.bootstrap4.min.css">
    <style>
        /* Custom status badge colors matching Sales module */
        .badge.status-pending { background: #f02a23; color: #fff; }
        .badge.status-partial { background: #ffc107; color: #212529; }
        .badge.status-paid { background: #198754; color: #fff; }
        .badge.status-overpaid { background: #6f42c1; color: #fff; }
        .badge.status-unknown { background: #6c757d; color: #fff; }
        /* Ensure badge padding/shape consistent */
        .badge { padding: 0.35em 0.6em; border-radius: 0.375rem; font-weight: 600; }
        /* Keep product search icon, select and clear button on a single line */
        #product-search-container .input-group { display: flex; align-items: center; gap: 6px; flex-wrap: nowrap; }
        #product-search-container .input-group .input-group-prepend,
        #product-search-container .input-group .input-group-append { flex: 0 0 auto; }
            #product-search-container { display: inline-block; }
            #product-search-container select { min-width: 180px; max-width: 360px; }
            #product-search-container .select2-container { width: 100% !important; }
            #product-search-container .select2-selection { min-height: 36px; }

            /* When moved into DataTable filter area, hide helper text and keep compact */
            #purchases-table_filter #product-search-container { display: inline-block; margin-right: 8px; vertical-align: middle; }
            #purchases-table_filter #product-search-container small { display: none; }
    </style>
@endsection

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item active">Purchases</li>
    </ol>
@endsection

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        @can('create_purchases')
                            <a href="{{ route('purchases.create') }}" class="btn btn-primary">
                                Add Purchase <i class="bi bi-plus"></i>
                            </a>
                        @endcan

                        <hr>

                        <div class="row mb-3">
                            <div class="col-12">
                                @include('components.daterange-filter', ['tableId' => 'purchases-table', 'totalsRoute' => route('purchases.totals')])
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div id="product-search-container" class="d-inline-block" style="min-width:260px;">
                                    <select id="product-search" style="width:100%"></select>
                                    <small class="text-muted">Filter the purchases list to rows that contain the searched product.</small>
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive">
                            {!! $dataTable->table(['class' => 'table table-striped table-bordered table-sm']) !!}
                        </div>

                        <!-- Purchase Totals Table -->
                        <div class="mt-4">
                            <h5 class="mb-3">Purchase Summary</h5>
                            <div class="table-responsive">
                                <table class="table table-bordered table-sm bg-light">
                                    <thead class="table-primary">
                                        <tr>
                                            <th class="text-center">Overall Bills Count</th>
                                            <th class="text-center">Overall count of the Suppliers</th>
                                            <th class="text-center">Overall Total Bills Amount</th>
                                            <th class="text-center">Overall Balance</th>
                                            <th class="text-center">Overall Paid Amount</th>
                                            <th class="text-center">Overall TAX Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr class="font-weight-bold">
                                            <td class="text-center" id="totals-count">0</td>
                                            <td class="text-center" id="totals-supplier-count">0</td>
                                            <td class="text-center" id="totals-amount">₹0.00</td>
                                            <td class="text-center" id="totals-balance">₹0.00</td>
                                            <td class="text-center" id="totals-paid">₹0.00</td>
                                            <td class="text-center" id="totals-tax">₹0.00</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('page_scripts')
    {!! $dataTable->scripts() !!}
    <script>
        (function($){
            function formatINR(v){
                return new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' }).format(v);
            }

            function fetchPurchaseTotals(){
                var start = $('#filter-start-purchases-table').val();
                var end = $('#filter-end-purchases-table').val();
                var searchVal = '';
                if ($.fn.dataTable.isDataTable('#purchases-table')) {
                    try { searchVal = $('#purchases-table').DataTable().search() || ''; } catch(e) { searchVal = ''; }
                }

                $.getJSON("{{ route('purchases.totals') }}", { start_date: start, end_date: end, search: searchVal }, function(data){
                    $('#totals-count').text(data.overall_count || data.count || 0);
                    $('#totals-supplier-count').text(data.overall_supplier_count || data.supplier_count || 0);
                    $('#totals-amount').text(formatINR(data.overall_total_amount || 0));
                    $('#totals-balance').text(formatINR(data.overall_balance || 0));
                    $('#totals-paid').text(formatINR(data.overall_paid_amount || 0));
                    $('#totals-tax').text(formatINR(data.overall_tax_amount || 0));
                });
            }

            $(function(){
                function reloadPurchasesTable() {
                    if ($.fn.dataTable && $.fn.dataTable.isDataTable('#purchases-table')) {
                        $('#purchases-table').DataTable().ajax.reload();
                    }
                }

                // Initialize Select2 for product search
                $('#product-search').select2({
                    placeholder: 'Search product by name or code',
                    allowClear: true,
                    ajax: {
                        url: '/api/products/search',
                        dataType: 'json',
                        delay: 250,
                        data: function(params) { return { q: params.term || '' }; },
                        processResults: function(data) { return { results: data.results || [] }; }
                    },
                    minimumInputLength: 1
                });

                // Move the product-search into the DataTable filter area (next to Search) when the table initializes
                $('#purchases-table').on('init.dt', function(){
                    try {
                        var $filter = $('#purchases-table_filter');
                        if ($filter.length) {
                            $filter.prepend($('#product-search-container'));
                            $('#product-search-container small').hide();
                        }
                    } catch(e) { console.warn('Could not move product-search into filter area', e); }
                });

                // Reload when selection changes
                $('#product-search').on('select2:select select2:clear', function(){ reloadPurchasesTable(); });

                // When the DataTable Reset button is clicked, clear the product selection too
                $(document).on('click', '.buttons-reset', function(){
                    try { $('#product-search').val(null).trigger('change'); } catch(e){}
                    reloadPurchasesTable();
                });

                // Keep summary aligned with state-saved search and refresh behavior.
                fetchPurchaseTotals();
                $(document).on('daterange.updated', function(){
                    fetchPurchaseTotals();
                });
                if ($.fn.dataTable.isDataTable('#purchases-table')) {
                    var purchasesTable = $('#purchases-table').DataTable();
                    purchasesTable.on('draw', function(){
                        fetchPurchaseTotals();
                    });
                }
            });
        })(jQuery);
    </script>
@endpush
