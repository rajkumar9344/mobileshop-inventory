@extends('layouts.app')

@section('title', 'Sales')

@section('third_party_stylesheets')
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.25/css/dataTables.bootstrap4.min.css">
    
    <style>
        /* Custom status badge colors for Sales listing */
        .badge.status-draft { background: #ffc107; color: #212529; }
        .badge.status-partial { background: #ffc107; color: #212529; }
        .badge.status-paid { background: #198754; color: #fff; }
        .badge.status-overpaid { background: #6f42c1; color: #fff; }
        .badge.status-pending { background: #f02a23; color: #fff; }
        .badge.status-unknown { background: #6c757d; color: #fff; }
        /* Ensure badge padding/shape consistent */
        .badge { padding: 0.35em 0.6em; border-radius: 0.375rem; font-weight: 600; }

        /* Filter panel improvements */
        .filter-panel { gap: .5rem; }
        .filter-panel .input-group .form-control { min-width: 260px; }
        .filter-panel .dropdown-menu { max-width: 220px; }
        .filter-panel .input-group .btn { border-top-left-radius: .375rem; border-bottom-left-radius: .375rem; }
        .filter-panel .input-group-append .btn { border-top-left-radius: 0; border-bottom-left-radius: 0; }
        @media (max-width: 768px) {
            .filter-panel .input-group { width: 100% !important; }
            .filter-panel .ms-auto { width: 100%; margin-top: .5rem; }
        }

        /* Keep product search icon, select and clear button on a single line */
        #product-search-container .input-group { display: flex; align-items: center; gap: 6px; flex-wrap: nowrap; }
        #product-search-container .input-group .input-group-prepend,
        #product-search-container .input-group .input-group-append { flex: 0 0 auto; }
            #product-search-container { display: inline-block; }
            #product-search-container select { min-width: 180px; max-width: 360px; }
            #product-search-container .select2-container { width: 100% !important; }
            #product-search-container .select2-selection { min-height: 36px; }

            /* When moved into DataTable filter area, hide helper text and keep compact */
            #sales-table_filter #product-search-container { display: inline-block; margin-right: 8px; vertical-align: middle; }
            #sales-table_filter #product-search-container small { display: none; }

        /* Filter panel improvements */
    </style>
@endsection

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item active">Sales</li>
    </ol>
@endsection

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        @can('create_sales')
                            <a href="{{ route('sales.create') }}" class="btn btn-primary">
                                Add Sale <i class="bi bi-plus"></i>
                            </a>
                        @endcan

                        <hr>

                        <div class="row mb-3">
                            <div class="col-12">
                                @include('components.daterange-filter', ['tableId' => 'sales-table', 'totalsRoute' => route('sales.totals')])
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div id="product-search-container" class="d-inline-block" style="min-width:240px;">
                                    <select id="product-search" style="width:100%"></select>
                                    <small class="text-muted">Filter the sales list to rows that contain the searched product.</small>
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive">
                            {!! $dataTable->table(['class' => 'table table-striped table-bordered table-sm']) !!}
                        </div>

                        <!-- Sales Totals Table -->
                        <div class="mt-4">
                            <h5 class="mb-3">Sales Summary</h5>
                            <div class="table-responsive">
                                <table class="table table-bordered table-sm bg-light">
                                    <thead class="table-primary">
                                        <tr>
                                            <th class="text-center">Overall Bills Count</th>
                                            <th class="text-center">Overall Total Bills Amount</th>
                                            <th class="text-center">Overall Balance</th>
                                            <th class="text-center">Overall Received Amount</th>
                                            <th class="text-center">Overall CGST</th>
                                            <th class="text-center">Overall SGST</th>
                                            <th class="text-center">Overall TAX Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr class="font-weight-bold">
                                            <td class="text-center" id="totals-count">0</td>
                                            <td class="text-center" id="totals-amount">₹0.00</td>
                                            <td class="text-center" id="totals-balance">₹0.00</td>
                                            <td class="text-center" id="totals-received">₹0.00</td>
                                            <td class="text-center" id="totals-cgst">₹0.00</td>
                                            <td class="text-center" id="totals-sgst">₹0.00</td>
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

                function fetchSalesTotals(){
                    var start = $('#filter-start-sales-table').val();
                    var end = $('#filter-end-sales-table').val();
                    var searchVal = '';
                    if ($.fn.dataTable.isDataTable('#sales-table')) {
                        try { searchVal = $('#sales-table').DataTable().search() || ''; } catch(e) { searchVal = ''; }
                    }

                    $.getJSON("{{ route('sales.totals') }}", { start_date: start, end_date: end, search: searchVal }, function(data){
                        $('#totals-count').text(data.overall_count || data.count || 0);
                        $('#totals-amount').text(formatINR(data.overall_total_amount || 0));
                        $('#totals-balance').text(formatINR(data.overall_balance || 0));
                        $('#totals-received').text(formatINR(data.overall_received_amount || data.total || 0));
                        $('#totals-cgst').text(formatINR(data.overall_cgst || 0));
                        $('#totals-sgst').text(formatINR(data.overall_sgst || 0));
                        $('#totals-tax').text(formatINR(data.overall_tax_amount || 0));
                    });
                }

                $(function(){
                // Reload DataTable with current product filter
                function reloadSalesTable() {
                    if ($.fn.dataTable && $.fn.dataTable.isDataTable('#sales-table')) {
                        $('#sales-table').DataTable().ajax.reload();
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
                $('#sales-table').on('init.dt', function(){
                    try {
                        var $filter = $('#sales-table_filter');
                        if ($filter.length) {
                            $filter.prepend($('#product-search-container'));
                            $('#product-search-container small').hide();
                        }
                    } catch(e) { console.warn('Could not move product-search into filter area', e); }
                });

                // Reload when selection changes
                $('#product-search').on('select2:select select2:clear', function(){ reloadSalesTable(); });

                // When the DataTable Reset button is clicked, clear the product selection too
                $(document).on('click', '.buttons-reset', function(){
                    try { $('#product-search').val(null).trigger('change'); } catch(e){}
                    reloadSalesTable();
                });

                // Keep summary aligned with state-saved search and refresh behavior.
                fetchSalesTotals();
                $(document).on('daterange.updated', function(){
                    fetchSalesTotals();
                });
                if ($.fn.dataTable.isDataTable('#sales-table')) {
                    var salesTable = $('#sales-table').DataTable();
                    salesTable.on('draw', function(){
                        fetchSalesTotals();
                    });
                }
            });
        })(jQuery);
    </script>
@endpush
