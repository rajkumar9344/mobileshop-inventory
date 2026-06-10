@extends('layouts.app')

@section('title', 'Customers')

@section('third_party_stylesheets')
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.25/css/dataTables.bootstrap4.min.css">
@endsection

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item active">Customers</li>
    </ol>
@endsection

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        @can('create_customers')
                            <a href="{{ route('customers.create') }}" class="btn btn-primary">
                                Add Customer <i class="bi bi-plus"></i>
                            </a>
                        @endcan

                        <hr>

                        <div class="row mb-3">
                            <div class="col-12">
                                @include('components.daterange-filter', ['tableId' => 'customers-table', 'totalsRoute' => '', 'label' => 'Customer Created Between'])
                            </div>
                        </div>

                        <div class="table-responsive">
                            {!! $dataTable->table(['class' => 'table table-striped table-bordered']) !!}
                        </div>

                        <!-- Customers Totals Table (matches Sales UI) -->
                        <div class="mt-4">
                            <h5 class="mb-3">Customers Summary</h5>
                            <div class="table-responsive">
                                <table class="table table-bordered table-sm bg-light">
                                    <thead class="table-primary">
                                        <tr>
                                            <th class="text-center">Overall Customers Count</th>
                                            <th class="text-center">Overall Total Bills Amount</th>
                                            <th class="text-center">Overall Received Amount</th>
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
            // Make scroll-blocking listeners passive by default when supported.
            // This reduces the Chrome console warnings about non-passive touch/wheel listeners
            // while avoiding changes in third-party code.
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
            // Helper to get DataTable instance (Yajra exposes window.LaravelDataTables[name])
            function getDT(name) {
                // Prefer Yajra's exposed instance when available
                if (window.LaravelDataTables && window.LaravelDataTables[name]) return window.LaravelDataTables[name];
                var selector = '#' + name;
                // Avoid reinitialising DataTable — check first
                if ($.fn.dataTable && $.fn.dataTable.isDataTable(selector)) {
                    return $(selector).DataTable();
                }
                return null;
            }

            function formatCurrency(v) {
                var n = Number(v || 0);
                if (isNaN(n)) return '₹0.00';
                return new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' }).format(n);
            }

            // Note: DataTable already returns a server 'summary' in its JSON via ->with(),
            // so the UI is updated from the DataTable xhr handler below.

            function attachHandlers(retries = 0) {
                var dt = getDT('customers-table');
                if (!dt) {
                    // DataTable not ready yet; retry a few times
                    if (retries < 10) setTimeout(function() { attachHandlers(retries + 1); }, 200);
                    return;
                }

                var selector = '#customers-table';
                // Unbind previous handlers to avoid duplicate listeners
                $(selector).off('xhr.dt').on('xhr.dt', function(e, settings, json, xhr) {
                    // DataTable returns summary with these keys (customers_count, overall_total, overall_paid, overall_balance)
                    if (json && json.summary) {
                        var s = json.summary;
                        // customers_count is an integer; other fields are already formatted strings from the server
                        document.getElementById('totals-count').textContent = (s.customers_count !== undefined ? s.customers_count : 0);
                        document.getElementById('totals-amount').textContent = (s.overall_total !== undefined ? s.overall_total : '₹0.00');
                        document.getElementById('totals-received').textContent = (s.overall_paid !== undefined ? s.overall_paid : '₹0.00');
                        document.getElementById('totals-bill-balance').textContent = (s.overall_balance !== undefined ? s.overall_balance : '₹0.00');
                        document.getElementById('totals-open-balance').textContent = (s.overall_open_balance !== undefined ? s.overall_open_balance : '₹0.00');
                        document.getElementById('totals-excess').textContent = (s.overall_excess !== undefined ? s.overall_excess : '₹0.00');
                    }
                });
                // After draw, ensure authoritative totals are fetched (helps if other code re-renders parts)
                // No-op on draw here; the DataTable's xhr.dt handler will contain the authoritative summary
                $(selector).off('draw.dt.totals');
            }

            // We no longer call the dedicated /customers/totals endpoint from the page;
            // the DataTable will include the summary in its JSON and the xhr.dt handler
            // above updates the UI when json.summary is present.

            // The daterange component handles apply/clear and reloads the DataTable

            // Start: attach handlers (with retries if DataTable isn't ready yet)
            // The DataTable's own initial AJAX will include the summary and the xhr handler
            attachHandlers();
        })();
    </script>
@endpush
