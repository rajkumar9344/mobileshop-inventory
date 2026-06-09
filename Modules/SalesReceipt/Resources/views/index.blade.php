@extends('layouts.app')

@section('title', 'Sales Receipts')

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        @can('create_sales_receipts')
                        <a href="{{ route('sales-receipts.create') }}" class="btn btn-primary">
                            Add Receipt <i class="bi bi-plus"></i>
                        </a>
                        @endcan

                        <hr>

                        <div class="row mb-3">
                            <div class="col-12">
                                @include('components.daterange-filter', ['tableId' => 'sales-receipts-table', 'totalsRoute' => route('salesreceipts.totals')])
                            </div>
                        </div>

                        <!-- Filters reduced: date range only (global Search already covers customer) -->

                        <div class="table-responsive">
                            {!! $dataTable->table(['class' => 'table table-striped table-bordered table-sm']) !!}
                        </div>

                        <div class="mt-4">
                            <h5 class="mb-3">Receipts Summary</h5>
                            <div class="table-responsive">
                                <table class="table table-bordered table-sm bg-light">
                                    <thead class="table-primary">
                                        <tr>
                                            <th class="text-center">Receipts Count</th>
                                            <th class="text-center">Cheque Amount</th>
                                            <th class="text-center">Cash Amount</th>
                                            <th class="text-center">Card Amount</th>
                                            <th class="text-center">UPI Amount</th>
                                            <th class="text-center">Bank Transfer Amount</th>
                                            <th class="text-center">Product Return Amount</th>
                                            <th class="text-center">Total Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr class="font-weight-bold">
                                            <td class="text-center" id="totals-count">0</td>
                                            <td class="text-center" id="totals-cheque">₹0.00</td>
                                            <td class="text-center" id="totals-cash">₹0.00</td>
                                            <td class="text-center" id="totals-card">₹0.00</td>
                                            <td class="text-center" id="totals-upi">₹0.00</td>
                                            <td class="text-center" id="totals-bank">₹0.00</td>
                                            <td class="text-center" id="totals-product-return">₹0.00</td>
                                            <td class="text-center" id="totals-total">₹0.00</td>
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

            function fetchTotals(){
                var start = $('#filter-start-sales-receipts-table').val();
                var end = $('#filter-end-sales-receipts-table').val();
                // include current DataTable global search value so totals match visible rows
                var searchVal = '';
                if ($.fn.dataTable.isDataTable('#sales-receipts-table')) {
                    try { searchVal = $('#sales-receipts-table').DataTable().search() || ''; } catch(e) { searchVal = ''; }
                }
                $.getJSON("{{ route('salesreceipts.totals') }}", { start_date: start, end_date: end, search: searchVal }, function(data){
                    $('#totals-count').text(data.overall_count || data.count || 0);
                    // populate per-mode cells (fallback if component didn't fill them)
                    $('#totals-cheque').text(formatINR(data.overall_cheque_amount || data.cheque || 0));
                    $('#totals-cash').text(formatINR(data.overall_cash_amount || data.cash || 0));
                    $('#totals-card').text(formatINR(data.overall_card_amount || data.card || 0));
                    $('#totals-upi').text(formatINR(data.overall_upi_amount || 0));
                    $('#totals-bank').text(formatINR(data.overall_bank_amount || 0));
                    $('#totals-product-return').text(formatINR(data.overall_product_return_amount || 0));
                    $('#totals-total').text(formatINR(data.overall_received_amount || data.total || 0));
                });
            }

            $(function(){
                // Only date-range filters remain; use the daterange component's apply/clear
                fetchTotals();

                $('#apply-filters-sales-receipts-table').on('click', function(e){
                    e.preventDefault();
                    var start = $('#filter-start-sales-receipts-table').val();
                    var end = $('#filter-end-sales-receipts-table').val();
                    var params = $.param({ start_date: start, end_date: end });
                    if ($.fn.dataTable.isDataTable('#sales-receipts-table')) {
                        var table = $('#sales-receipts-table').DataTable();
                        var base = '{{ route('sales-receipts.index') }}';
                        table.ajax.url(base + (params ? ('?' + params) : '')).load();
                    }
                    fetchTotals();
                });

                // Listen to global daterange events (emitted by the reusable component)
                $(document).on('daterange.updated', function(){
                    // Re-run totals when the shared daterange component applies/clears
                    fetchTotals();
                });

                $('#clear-filters-sales-receipts-table').on('click', function(e){
                    e.preventDefault();
                    $('#filter-start-sales-receipts-table').val('');
                    $('#filter-end-sales-receipts-table').val('');
                    if ($.fn.dataTable.isDataTable('#sales-receipts-table')) {
                        var table = $('#sales-receipts-table').DataTable();
                        var base = '{{ route('sales-receipts.index') }}';
                        table.ajax.url(base).load();
                    }
                    fetchTotals();
                });

                // Update totals after the DataTable completes a draw (includes searches, paging, ajax loads)
                if ($.fn.dataTable.isDataTable('#sales-receipts-table')) {
                    var _table = $('#sales-receipts-table').DataTable();
                    _table.on('draw', function(){
                        fetchTotals();
                    });
                }
            });
        })(jQuery);
    </script>
@endpush
