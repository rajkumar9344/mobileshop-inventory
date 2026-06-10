<div>
    {{-- Top Filter: Closing Date (hidden on Home screen) --}}
    @if($showFilter)
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-end justify-content-between flex-wrap" style="gap:0.75rem;">
                        <div>
                            <label class="small mb-1">Closing Date</label>
                            <input type="date" wire:model.live="report_date" class="form-control" style="min-width:160px;">
                        </div>

                        <div class="d-flex align-items-center flex-wrap" style="gap:0.5rem;">
                            <a href="{{ route('reports.daily-operations-excel', ['date' => $report_date]) }}" 
                               class="btn btn-success" target="_blank">
                                <i class="bi bi-file-earmark-excel"></i> Export Excel
                            </a>
                            <a href="{{ route('reports.daily-operations-pdf', ['date' => $report_date]) }}" 
                               class="btn btn-danger" target="_blank">
                                <i class="bi bi-file-earmark-pdf"></i> Export PDF
                            </a>
                            <a href="{{ route('reports.daily-operations-print', ['date' => $report_date]) }}" 
                               class="btn btn-secondary" target="_blank">
                                <i class="bi bi-printer"></i> Print
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @else
    {{-- Home screen shortcut bar --}}
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body d-flex align-items-center justify-content-between flex-wrap" style="gap:0.5rem;">
                    <div class="text-muted small">
                        <i class="bi bi-calendar-date mr-1"></i>
                        Showing data for: <strong>{{ \Carbon\Carbon::parse($report_date)->format('d M Y') }}</strong>
                    </div>
                    <a href="{{ route('daily-operations-report.index') }}" class="btn btn-primary">
                        <i class="bi bi-bar-chart-line mr-1"></i> Open Full Report
                    </a>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Loading Indicator --}}
    <div wire:loading.flex class="position-fixed w-100 h-100 justify-content-center align-items-center rm-loading-overlay"
         style="top:0;left:0;z-index:9999;">
        <div class="spinner-border text-primary" role="status">
            <span class="sr-only">Loading...</span>
        </div>
    </div>

    {{-- KPI Cards Section --}}
    <div class="row mb-4">
        <div class="col-12">
            <h5 class="text-primary mb-3">Daily Closing Summary</h5>
        </div>
        
        {{-- Opening Balance --}}
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card border-0 shadow-sm h-100 kpi-card kpi-info">
                <div class="card-body d-flex align-items-center">
                    <div>
                        <div class="text-info h4 mb-0">{{ format_currency($opening_balance) }}</div>
                        <div class="text-uppercase font-weight-bold small text-muted">Opening Balance</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Daily Sales Gross --}}
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card border-0 shadow-sm h-100 kpi-card kpi-primary">
                <div class="card-body d-flex align-items-center">
                    <div>
                        <div class="text-primary h4 mb-0">{{ format_currency($daily_sales_gross) }}</div>
                        <div class="text-uppercase font-weight-bold small text-muted">Sales Gross (MRP)</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Daily Sales Net --}}
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card border-0 shadow-sm h-100 kpi-card kpi-success">
                <div class="card-body d-flex align-items-center">
                    <div>
                        <div class="text-success h4 mb-0">{{ format_currency($daily_sales_net) }}</div>
                        <div class="text-uppercase font-weight-bold small text-muted">Sales Net Amount</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Daily Received --}}
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card border-0 shadow-sm h-100 kpi-card kpi-warning">
                <div class="card-body d-flex align-items-center">
                    <div>
                        <div class="text-warning h4 mb-0">{{ format_currency($daily_received) }}</div>
                        <div class="text-uppercase font-weight-bold small text-muted">Received Amount</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Daily Expenses --}}
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card border-0 shadow-sm h-100 kpi-card kpi-danger">
                <div class="card-body d-flex align-items-center">
                    <div>
                        <div class="text-danger h4 mb-0">{{ format_currency($daily_expenses) }}</div>
                        <div class="text-uppercase font-weight-bold small text-muted">Daily Expenses</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Net Closing Before Petty Cash --}}
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card border-0 shadow-sm h-100 kpi-card kpi-secondary">
                <div class="card-body d-flex align-items-center">
                    <div>
                        <div class="h4 mb-0 {{ floatval($net_closing_before_petty) >= 0 ? 'text-success' : 'text-danger' }}">{{ format_currency($net_closing_before_petty) }}</div>
                        <div class="text-uppercase font-weight-bold small text-muted">Net Closing (Before Petty)</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Tomorrow Opening Balance --}}
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card border-0 shadow-sm h-100 kpi-card kpi-info">
                <div class="card-body d-flex align-items-center">
                    <div>
                        @if($showFilter)
                        <div class="d-flex align-items-center">
                            <input type="number" step="0.01" wire:model.debounce.500ms="tomorrow_opening" class="form-control form-control-sm" style="width:140px; display:inline-block;" />
                            <button wire:click.prevent="saveTomorrowOpening" class="btn btn-sm btn-primary ml-2">Save</button>
                        </div>
                        @else
                        <div class="text-info h4 mb-0">{{ format_currency($tomorrow_opening) }}</div>
                        @endif
                        <div class="text-uppercase font-weight-bold small text-muted mt-2">Tomorrow Opening</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Closing Balance --}}
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card border-0 shadow-sm h-100 border-primary kpi-card kpi-dark" style="border-width: 2px !important;">
                <div class="card-body d-flex align-items-center">
                    <div>
                            <div class="h4 text-dark mb-1 font-weight-bold">{{ format_currency($closing_balance) }}</div>
                            <div class="text-uppercase small text-muted">Closing Balance</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Payment Method Totals Section --}}
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0 text-primary">Payment Method Totals</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        {{-- Total Before Expense --}}
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="border rounded p-3 text-center bg-light">
                                <div class="h4 text-primary mb-1">{{ format_currency($total_before_expense) }}</div>
                                <small class="text-muted text-uppercase">Total Received (Before Expense)</small>
                            </div>
                        </div>

                        @foreach($payment_totals as $mode => $totals)
                            @if($mode !== 'Product return')
                            <div class="col-lg-3 col-md-6 mb-3">
                                <div class="border rounded p-3">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="font-weight-bold">{{ $mode }}</span>
                                        <div class="h4 text-primary mb-0">{{ format_currency($totals['before_expense']) }}</div>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <div class="text-danger">Expense: <span class="h6 mb-0">{{ format_currency($totals['expense']) }}</span></div>
                                        <div class="text-success">After: <span class="h6 mb-0 font-weight-bold">{{ format_currency($totals['after_expense']) }}</span></div>
                                    </div>
                                </div>
                            </div>
                            @endif
                        @endforeach

                        {{-- Product Return --}}
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="border rounded p-3 text-center bg-light">
                                <div class="h4 text-warning mb-1 font-weight-bold">{{ format_currency($product_return_amount) }}</div>
                                <small class="text-uppercase text-muted">Product Return Amount</small>
                            </div>
                        </div>

                        {{-- Total After Expense --}}
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="border rounded p-3 text-center bg-light">
                                <div class="h4 text-success mb-1 font-weight-bold">{{ format_currency($total_after_expense) }}</div>
                                <small class="text-uppercase text-muted">Total After Expenses</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Receipts for Payment Method Totals (table with expandable rows) --}}
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 text-primary">Receipts for Payment Method Totals <small class="text-muted">(click a row to expand)</small></h5>
                    <div>
                        <button wire:click="expandAll" class="btn btn-outline-primary btn-sm mr-1">Expand</button>
                        <button wire:click="collapseAll" class="btn btn-outline-secondary btn-sm">Collapse</button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive receipts-table">
                        <table class="table mb-0 table-hover">
                            <thead class="thead-light">
                                <tr>
                                    <th>Payment Method</th>
                                    <th class="text-center">Total Amount</th>
                                    <th class="text-center">Receipt Count</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($receipts_by_method as $mode => $receipts)
                                    <tr wire:click="toggleMethod('{{ $mode }}')" style="cursor:pointer;">
                                        <td>{{ $mode }}</td>
                                        <td class="text-center">{{ format_currency($payment_totals[$mode]['before_expense'] ?? 0) }}</td>
                                        <td class="text-center">{{ count($receipts) }}</td>
                                    </tr>

                                    @if(in_array($mode, $expanded_methods))
                                        <tr class="bg-light">
                                            <td colspan="3" class="p-0">
                                                @if(count($receipts) > 0)
                                                    <div class="receipts-breakdown p-3">
                                                        <div class="small text-muted mb-2">Breakdown: {{ $mode }} ({{ count($receipts) }} receipts)</div>
                                                        <div class="table-responsive">
                                                            <table class="table table-sm mb-0">
                                                                <thead class="thead-light small">
                                                                    <tr>
                                                                        <th class="text-uppercase">Reference</th>
                                                                        <th class="text-uppercase">Invoice / Payer</th>
                                                                        <th class="text-right text-uppercase">Amount</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    @foreach($receipts as $receipt)
                                                                        <tr>
                                                                            <td>
                                                                                <a href="{{ route('sales-receipts.show', $receipt['id']) }}" target="_blank">{{ $receipt['reference'] }}</a>
                                                                            </td>
                                                                            <td>{{ $receipt['customer_name'] }}</td>
                                                                            <td class="text-right">{{ format_currency($receipt['total_amount']) }}</td>
                                                                        </tr>
                                                                    @endforeach
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    </div>
                                                @else
                                                    <div class="p-3 text-center text-muted">No receipts for this payment method</div>
                                                @endif
                                            </td>
                                        </tr>
                                    @endif
                                @endforeach
                                {{-- Grand total footer --}}
                                <tr class="font-weight-bold bg-white">
                                    <td>Grand Total (incl. returns)</td>
                                    <td class="text-center">{{ format_currency($total_before_expense) }}</td>
                                    <td class="text-center">{{ collect($receipts_by_method)->sum(fn($r) => count($r)) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Expenses by Payment Mode --}}
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0 text-danger">Expense Amount by Payment Mode</h5>
                </div>
                <div class="card-body">
                    @if(count($expenses_by_mode) > 0)
                        <div class="p-3">
                            <div class="border rounded p-2 bg-white expenses-box">
                                <ul class="list-group list-group-flush">
                                    @foreach($expenses_by_mode as $mode => $data)
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <span>{{ $mode ?? 'Cash' }}</span>
                                            <span class="font-weight-bold">{{ format_currency($data['total']) }}</span>
                                        </li>
                                    @endforeach
                                    <li class="list-group-item d-flex justify-content-between font-weight-bold">
                                        <span>Total</span>
                                        <span>{{ format_currency($daily_expenses) }}</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    @else
                        <div class="text-center text-muted p-4">
                            <p class="mb-0">No expenses recorded for this date</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Monthwise Summary Section --}}
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0 text-primary">Monthwise Summary for Received Payments</h5>
                </div>
                <div class="card-body">
                    @if($showFilter)
                    <div class="inner-filters mb-3 p-3 rounded bg-white border">
                        <div class="d-flex align-items-center">
                            <div class="mr-3" style="min-width:180px;">
                                <label class="small mb-1">Year</label>
                                <select wire:model.live="summary_year" class="form-control">
                                    @foreach($years as $year)
                                        <option value="{{ $year }}">{{ $year }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="mr-3" style="min-width:220px;">
                                <label class="small mb-1">Month</label>
                                <select wire:model.live="summary_month" class="form-control">
                                    <option value="all">All Months</option>
                                    @foreach($months as $key => $month)
                                        <option value="{{ $key }}">{{ $month }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="ml-auto d-flex">
                                <a href="{{ route('reports.daily-operations-monthwise-excel', ['year' => $summary_year, 'month' => $summary_month]) }}" 
                                   class="btn btn-success btn-sm mr-2" target="_blank">
                                    <i class="bi bi-file-earmark-excel"></i> Export Excel
                                </a>
                                <a href="{{ route('reports.daily-operations-monthwise-pdf', ['year' => $summary_year, 'month' => $summary_month]) }}" 
                                   class="btn btn-danger btn-sm" target="_blank">
                                    <i class="bi bi-file-earmark-pdf"></i> Export PDF
                                </a>
                            </div>
                        </div>
                    </div>
                    @endif

                    <div class="table-responsive monthwise-table">
                        <table class="table table-bordered table-striped text-center mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th>Month</th>
                                    <th>TOTAL (INCL. RETURNS) <br><small>(RECEIPTS)</small></th>
                                    <th>Cash</th>
                                    <th>Cheque</th>
                                    <th>Cards</th>
                                    <th>Bank Transfer</th>
                                    <th>UPI</th>
                                    <th>Product Return</th>
                                    <th>Overall Expenses (Month)</th>
                                    <th>Net After Expenses</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($monthwise_data as $row)
                                    <tr>
                                        <td class="text-left">{{ $row['date'] }}</td>
                                        <td class="text-right">{{ format_currency($row['grand_total'] ?? 0) }} @if(isset($row['count'])) <br><small>({{ $row['count'] }} receipts)</small> @endif</td>
                                        <td class="text-right">{{ format_currency($row['totals']['Cash'] ?? 0) }}</td>
                                        <td class="text-right">{{ format_currency($row['totals']['Cheque'] ?? 0) }}</td>
                                        <td class="text-right">{{ format_currency($row['totals']['Cards'] ?? 0) }}</td>
                                        <td class="text-right">{{ format_currency($row['totals']['Bank Transfer'] ?? 0) }}</td>
                                        <td class="text-right">{{ format_currency($row['totals']['UPI Payment'] ?? 0) }}</td>
                                        <td class="text-right">{{ format_currency($row['totals']['Product return'] ?? 0) }}</td>
                                        <td class="text-right">{{ format_currency($row['overall_expenses'] ?? 0) }}</td>
                                        <td class="text-right font-weight-bold">{{ format_currency($row['net_after_expenses'] ?? ($row['grand_total'] ?? 0)) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="10" class="text-warning">
                                            No data available for this period
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                            @if(count($monthwise_data) > 0)
                                <tfoot style="background:var(--rm-bg-table-head);" class="font-weight-bold">
                                    <tr>
                                        <td>Total</td>
                                        <td class="text-right">{{ format_currency(collect($monthwise_data)->sum('grand_total')) }}</td>
                                        <td class="text-right">{{ format_currency(collect($monthwise_data)->sum(fn($r) => $r['totals']['Cash'] ?? 0)) }}</td>
                                        <td class="text-right">{{ format_currency(collect($monthwise_data)->sum(fn($r) => $r['totals']['Cheque'] ?? 0)) }}</td>
                                        <td class="text-right">{{ format_currency(collect($monthwise_data)->sum(fn($r) => $r['totals']['Cards'] ?? 0)) }}</td>
                                        <td class="text-right">{{ format_currency(collect($monthwise_data)->sum(fn($r) => $r['totals']['Bank Transfer'] ?? 0)) }}</td>
                                        <td class="text-right">{{ format_currency(collect($monthwise_data)->sum(fn($r) => $r['totals']['UPI Payment'] ?? 0)) }}</td>
                                        <td class="text-right">{{ format_currency(collect($monthwise_data)->sum(fn($r) => $r['totals']['Product return'] ?? 0)) }}</td>
                                        <td class="text-right">{{ format_currency(collect($monthwise_data)->sum(fn($r) => $r['overall_expenses'] ?? 0)) }}</td>
                                        <td class="text-right">{{ format_currency(collect($monthwise_data)->sum(fn($r) => $r['net_after_expenses'] ?? ($r['grand_total'] ?? 0))) }}</td>
                                    </tr>
                                </tfoot>
                            @endif
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('page_css')
    <style>
    .bg-warning-light { background-color: rgba(255, 193, 7, 0.1); }
    .font-2xl { font-size: 1.5rem; }
    .text-value { font-weight: 600; }

    /* Receipts table styling to match design */
    .receipts-table .thead-light th {
        background: #f3f6f9;
        border-bottom: 1px solid #e6e9ee;
        color: #6b7c93;
        font-weight: 700;
        letter-spacing: .02em;
    }
    .receipts-table tbody tr { border-bottom: 1px solid #eef2f6; }
    .receipts-table tbody tr:hover { background: #fbfdff; }

    .receipts-breakdown {
        background: #f6f9fb;
        border-left: 1px solid #e9eef3;
        border-right: 1px solid #e9eef3;
    }
    .receipts-breakdown .thead-light th { background: #e9edf0; color: #7b8a99; }
    .receipts-breakdown td { vertical-align: middle; }
    .receipts-breakdown a { color: #2b6cb0; }

    .card-header .btn { min-width: 80px; }

    /* Expenses box styling */
    .expenses-box { background: #fff; }
    .expenses-box .list-group-item { border: none; border-bottom: 1px solid #eef2f6; }
    .expenses-box .list-group-item:last-child { border-bottom: none; }
    .expenses-box .list-group-item.total { font-weight:700; }

    /* Monthwise summary inner filters & table */
    .inner-filters { box-shadow: none; }
    .monthwise-table .thead-light th { background: #f3f6f9; color: #6b7c93; font-weight:700; }
    .monthwise-table tbody tr { background: #fff; }
    .monthwise-table tfoot { background: #f8fafc; }
    /* Ensure totals row text is visible (dark and bold) */
    .monthwise-table tfoot, .monthwise-table tfoot td { color: #374151 !important; }
    .monthwise-table tfoot td { font-weight: 700; }
    .monthwise-table tfoot td:first-child { color: #1f2937 !important; }

    /* KPI subtle left-color accent (thin pastel left border) */
    .kpi-card { border-left: 6px solid transparent; }
    .kpi-card .card-body { transition: background-color .15s ease; background-color: #fff; padding-left: 1rem; }
    .kpi-card.kpi-info { border-left-color: rgba(23,162,184,0.22); }
    .kpi-card.kpi-primary { border-left-color: rgba(0,123,255,0.22); }
    .kpi-card.kpi-success { border-left-color: rgba(40,167,69,0.22); }
    .kpi-card.kpi-warning { border-left-color: rgba(255,193,7,0.22); }
    .kpi-card.kpi-danger { border-left-color: rgba(220,53,69,0.22); }
    .kpi-card.kpi-secondary { border-left-color: rgba(108,117,125,0.18); }
    .kpi-card.kpi-dark { border-left-color: rgba(52,58,64,0.22); }
    .kpi-card .text-value { margin: 0; }

</style>
@endpush
