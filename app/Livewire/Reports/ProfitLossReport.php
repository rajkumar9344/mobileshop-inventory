<?php

namespace App\Livewire\Reports;

use App\Services\ReportQueryService;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\People\Entities\Customer;

class ProfitLossReport extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    public $customer_id;
    public $reference;
    public $start_date;
    public $end_date;
    public $pl_status; // '', 'profit', 'loss'

    public function mount() {
        $this->customer_id = '';
        $this->reference   = '';
        $this->start_date  = '';
        $this->end_date    = '';
        $this->pl_status   = '';
    }

    public function updating($name, $value) {
        // Any filter change goes back to page 1
        $this->resetPage();
    }

    public function render() {
        $customers = Customer::select('id', 'customer_name')
            ->get()
            ->sortBy(fn ($c) => $c->customer_name, SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        $query = app(ReportQueryService::class)->buildProfitLossQuery($this->filters());

        $sales = $query->paginate(25);

        // Page totals (computed columns are in paise — divide by 100 for display)
        $pageTotals = [
            'incl_vat'       => $sales->sum('amount_incl_vat') / 100,
            'purchase_total' => $sales->sum('purchase_total') / 100,
            'profit'         => $sales->sum('profit_amount') / 100,
        ];

        return view('livewire.reports.profit-loss-report', [
            'sales'      => $sales,
            'customers'  => $customers,
            'pageTotals' => $pageTotals,
        ]);
    }

    public function resetFilters() {
        $this->mount();
        $this->resetPage();
    }

    public function filters(): array {
        return [
            'customer_id' => $this->customer_id,
            'reference'   => $this->reference,
            'start_date'  => $this->start_date,
            'end_date'    => $this->end_date,
            'pl_status'   => $this->pl_status,
        ];
    }
}
