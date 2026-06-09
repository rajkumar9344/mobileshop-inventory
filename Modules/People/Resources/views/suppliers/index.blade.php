@extends('layouts.app')

@section('title', 'Suppliers')

@section('third_party_stylesheets')
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.25/css/dataTables.bootstrap4.min.css">
@endsection

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item active">Suppliers</li>
    </ol>
@endsection

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        @can('create_suppliers')
                            <a href="{{ route('suppliers.create') }}" class="btn btn-primary">
                                Add Supplier <i class="bi bi-plus"></i>
                            </a>
                        @endcan

                        <hr>

                        <div class="row mb-3">
                            <div class="col-12">
                                @include('components.daterange-filter', ['tableId' => 'suppliers-table', 'totalsRoute' => '', 'label' => 'Supplier Created Between'])
                            </div>
                        </div>

                        <div class="table-responsive">
                            {!! $dataTable->table() !!}
                        </div>

                        <!-- Suppliers Totals Table (match Customers UI) -->
                        <div class="mt-4">
                            <h5 class="mb-3">Suppliers Summary</h5>
                            <div class="table-responsive">
                                <table class="table table-bordered table-sm bg-light">
                                    <thead class="table-primary">
                                        <tr>
                                            <th class="text-center">Overall Suppliers Count</th>
                                            <th class="text-center">Overall Total Bills Amount</th>
                                            <th class="text-center">Overall Paid Amount</th>
                                            <th class="text-center">Overall Discount</th>
                                            <th class="text-center">Overall Bill Balance</th>
                                            <th class="text-center">Overall Open Balance</th>
                                            <th class="text-center">Overall Excess</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr class="font-weight-bold">
                                            <td class="text-center" id="totals-count">0</td>
                                            <td class="text-center" id="totals-amount">₹0.00</td>
                                            <td class="text-center" id="totals-received">₹0.00</td>
                                            <td class="text-center" id="totals-discount">₹0.00</td>
                                            <td class="text-center" id="totals-bill-balance">₹0.00</td>
                                            <td class="text-center" id="totals-open-balance">₹0.00</td>
                                            <td class="text-center" id="totals-excess">₹0.00</td>
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
        (function() {
            (function(){
                var supportsPassive = false;
                try {
                    var opts = Object.defineProperty({}, 'passive', { get: function() { supportsPassive = true; } });
                    window.addEventListener('test', null, opts);
                } catch(e) {}
                if (!supportsPassive) return;
                var orig = EventTarget.prototype.addEventListener;
                EventTarget.prototype.addEventListener = function(type, listener, options) {
                    try {
                        if ((type === 'touchstart' || type === 'touchmove' || type === 'wheel' || type === 'mousewheel')) {
                            if (options == null || typeof options === 'boolean') {
                                options = { passive: true, capture: !!options };
                            } else if (typeof options === 'object' && options.passive === undefined) {
                                options.passive = true;
                            }
                        }
                    } catch (e) {
                        // ignore
                    }
                    return orig.call(this, type, listener, options);
                };
            })();

            function getDT(name) {
                if (window.LaravelDataTables && window.LaravelDataTables[name]) return window.LaravelDataTables[name];
                var selector = '#' + name;
                if ($.fn.dataTable && $.fn.dataTable.isDataTable(selector)) {
                    return $(selector).DataTable();
                }
                return null;
            }

            function attachHandlers(retries = 0) {
                var dt = getDT('suppliers-table');
                if (!dt) {
                    if (retries < 10) setTimeout(function() { attachHandlers(retries + 1); }, 200);
                    return;
                }

                var selector = '#suppliers-table';
                $(selector).off('xhr.dt').on('xhr.dt', function(e, settings, json, xhr) {
                    if (json && json.summary) {
                        var s = json.summary;
                        document.getElementById('totals-count').textContent = (s.suppliers_count !== undefined ? s.suppliers_count : 0);
                        document.getElementById('totals-amount').textContent = (s.overall_total !== undefined ? s.overall_total : '₹0.00');
                        document.getElementById('totals-received').textContent = (s.overall_paid !== undefined ? s.overall_paid : '₹0.00');
                        document.getElementById('totals-discount').textContent = (s.overall_discount !== undefined ? s.overall_discount : '₹0.00');
                        document.getElementById('totals-bill-balance').textContent = (s.overall_balance !== undefined ? s.overall_balance : '₹0.00');
                        document.getElementById('totals-open-balance').textContent = (s.overall_open_balance !== undefined ? s.overall_open_balance : '₹0.00');
                        document.getElementById('totals-excess').textContent = (s.overall_excess !== undefined ? s.overall_excess : '₹0.00');
                    }
                });
                $(selector).off('draw.dt.totals');
            }

            attachHandlers();
        })();
    </script>
@endpush
