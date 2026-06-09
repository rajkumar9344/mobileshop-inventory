<div>
    <div class="card mb-3">
        <div class="card-body">
            <form class="row align-items-end">
                <div class="col-lg-3 col-md-6 mb-2">
                    <label class="d-block mb-1">Customer</label>
                    <select wire:model.live="customer_id" class="form-control">
                        <option value="">Select Customer</option>
                        @foreach($customers as $c)
                            <option value="{{ $c->id }}">{{ $c->customer_name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-lg-2 col-md-6 mb-2">
                    <label class="d-block mb-1">Start Date</label>
                    <input type="date" wire:model.live.debounce.500ms="start_date" class="form-control">
                </div>

                <div class="col-lg-2 col-md-6 mb-2">
                    <label class="d-block mb-1">End Date</label>
                    <input type="date" wire:model.live.debounce.500ms="end_date" class="form-control">
                </div>

                <div class="col-lg-3 col-md-6 mb-2">
                    <label class="d-block mb-1">Financial Year</label>
                    <select wire:model.live="financial_year" class="form-control">
                        <option value="">Select Financial Year</option>
                        @php
                            $currentYear = date('Y');
                            $currentMonth = date('m');
                            $fyStartYear = ($currentMonth >= 4) ? $currentYear : ($currentYear - 1);
                        @endphp
                        @for($i = 0; $i < 5; $i++)
                            @php $fy = ($fyStartYear - $i) . '-' . ($fyStartYear - $i + 1); @endphp
                            <option value="{{ $fy }}">FY {{ $fyStartYear - $i }}-{{ substr($fyStartYear - $i + 1, -2) }}</option>
                        @endfor
                    </select>
                </div>

                <div class="col-lg-2 col-md-12 mb-2">
                    <label class="d-block mb-1">&nbsp;</label>
                    <button type="button" wire:click="resetFilters" class="btn btn-outline-danger btn-block"><i class="bi bi-arrow-clockwise"></i> Reset Filters</button>
                </div>
            </form>
        </div>
    </div>

    @if(!empty($customer_id))
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Customer Ledger Report</h5>
                <div>
                    <a href="{{ route('reports.ledger-excel', ['customer_id' => $customer_id, 'start_date' => $start_date, 'end_date' => $end_date]) }}" target="_blank" class="btn btn-success btn-sm me-2"><i class="bi bi-file-earmark-excel me-1"></i>Export Excel</a>
                    <a href="{{ route('reports.ledger-pdf', ['customer_id' => $customer_id, 'start_date' => $start_date, 'end_date' => $end_date]) }}" target="_blank" class="btn btn-danger btn-sm me-2"><i class="bi bi-file-earmark-pdf me-1"></i>Export PDF</a>
                    <a href="{{ route('reports.ledger-print', ['customer_id' => $customer_id, 'start_date' => $start_date, 'end_date' => $end_date]) }}" target="_blank" class="btn btn-secondary btn-sm me-2"><i class="bi bi-printer me-1"></i>Print</a>

                    <form method="POST" action="{{ route('reports.ledger-send-email') }}" class="d-inline-block" style="display:inline;">
                        @csrf
                        <input type="hidden" name="customer_id" value="{{ $customer_id }}">
                        <input type="hidden" name="start_date" value="{{ $start_date }}">
                        <input type="hidden" name="end_date" value="{{ $end_date }}">
                        <button type="submit" class="btn btn-primary btn-sm ms-2">Send Email</button>
                    </form>
                </div>
            </div>

            <div class="card-body p-0">
                @forelse($data as $block)
                <div class="p-3 border-bottom">
                    <h6 class="mb-3">{{ $block['customer']->customer_name }}</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:120px">Date</th>
                                    <th>Particulars</th>
                                    <th>Payment Mode</th>
                                    <th class="text-right">Sales Amount</th>
                                    <th class="text-right">Received Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php $s = $block['summary']; @endphp
                                <tr class="table-secondary">
                                    <td colspan="3"><strong>Opening Balance</strong></td>
                                    <td class="text-right"><strong>{{ number_format($s['opening_debit'] ?? ($block['opening'] > 0 ? $block['opening'] : 0),2) }}</strong></td>
                                    <td class="text-right"><strong>{{ number_format($s['opening_credit'] ?? ($block['opening'] < 0 ? abs($block['opening']) : 0),2) }}</strong></td>
                                </tr>
                                @forelse($block['transactions'] as $t)
                                    <tr>
                                        <td>{{ !empty($t['date']) ? \Carbon\Carbon::parse($t['date'])->format('d-M-Y') : '' }}</td>
                                        <td>{{ ucfirst($t['type']) }} - {{ $t['reference'] }}</td>
                                        <td>{{ $t['payment_mode'] ?? '' }}</td>
                                        <td class="text-right">{{ $t['debit'] ? number_format($t['debit'],2) : '' }}</td>
                                        <td class="text-right">{{ $t['credit'] ? number_format($t['credit'],2) : '' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">No transactions in this period</td>
                                    </tr>
                                @endforelse
                                <tr class="table-light">
                                    <td colspan="3" class="text-end"></td>
                                    <td class="text-right"><strong>{{ number_format($s['total_debit'],2) }}</strong></td>
                                    <td class="text-right"><strong>{{ number_format($s['total_credit'],2) }}</strong></td>
                                </tr>
                                @if($s['closing_balance'] >= 0)
                                <tr>
                                    <td colspan="3"><strong>By &nbsp;&nbsp;&nbsp; Closing Balance</strong></td>
                                    @if($s['closing_in_credit'])
                                        <td class="text-right"></td>
                                        <td class="text-right"><strong>{{ number_format($s['closing_balance'],2) }}</strong></td>
                                    @else
                                        <td class="text-right"><strong>{{ number_format($s['closing_balance'],2) }}</strong></td>
                                        <td class="text-right"></td>
                                    @endif
                                </tr>
                                @endif
                                <tr class="table-secondary">
                                    <td colspan="3"></td>
                                    <td class="text-right"><strong>{{ number_format($s['balanced_total'],2) }}</strong></td>
                                    <td class="text-right"><strong>{{ number_format($s['balanced_total'],2) }}</strong></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                @empty
                    <div class="alert alert-info m-3">No transactions for selected customer in this period.</div>
                @endforelse
            </div>
        </div>
    @endif
</div>