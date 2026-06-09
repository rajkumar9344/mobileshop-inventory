@extends('layouts.app')

@section('title', 'Purchases Receipts')

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        @can('create_purchases_receipts')
                        <a href="{{ route('purchases-receipts.create') }}" class="btn btn-primary">
                            Add Receipt <i class="bi bi-plus"></i>
                        </a>
                        @endcan

                        <hr>

                        @include('components.daterange-filter', ['tableId' => 'purchases-receipts-table', 'totalsRoute' => route('purchasesreceipts.totals')])

                        {{-- Add view-specific spacing below the shared filter so buttons don't overlap here --}}
                        <div class="mb-3"></div>

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
                var start = $('#filter-start-purchases-receipts-table').val();
                var end = $('#filter-end-purchases-receipts-table').val();

                // Read DataTable state-saved search so refresh keeps summary aligned with visible rows.
                var searchVal = '';
                if ($.fn.dataTable.isDataTable('#purchases-receipts-table')) {
                    try { searchVal = $('#purchases-receipts-table').DataTable().search() || ''; } catch (e) { searchVal = ''; }
                }

                $.getJSON("{{ route('purchasesreceipts.totals') }}", {
                    start_date: start,
                    end_date: end,
                    search: searchVal
                }, function(data){
                    $('#totals-count').text(data.overall_count || data.count || 0);
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
                fetchTotals();

                // Fired by reusable date-range component after apply/clear.
                $(document).on('daterange.updated', function(){
                    fetchTotals();
                });

                // Keep summary synced for search, paging and state-restored draw after refresh.
                if ($.fn.dataTable.isDataTable('#purchases-receipts-table')) {
                    var table = $('#purchases-receipts-table').DataTable();
                    table.on('draw', function(){
                        fetchTotals();
                    });
                }
            });
        })(jQuery);
    </script>
@endpush