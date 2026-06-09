<?php

namespace App\Livewire\Reports;

use Livewire\Component;
use Livewire\WithPagination;
use Modules\Sale\Entities\SaleDetails;

class GstrReport extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    public $perPage = 25;

    public $hsn = '';
    public $product = '';
    public $rate = '';
    public $hide_without_hsn = false;
    public $start_date = null;
    public $end_date = null;

    protected $listeners = ['setDateRange'];

    public function setDateRange($start, $end)
    {
        $this->start_date = $start;
        $this->end_date = $end;
        $this->resetPage();
    }

    public function resetFilters()
    {
        $this->hsn = '';
        $this->product = '';
        $this->rate = '';
        $this->hide_without_hsn = false;
        $this->start_date = null;
        $this->end_date = null;
        $this->resetPage();
    }

    public function updated($name, $value)
    {
        $this->resetPage();
    }

    protected function query()
    {
        return app(\App\Services\ReportQueryService::class)->buildGstrQuery([
            'hsn' => $this->hsn,
            'product' => $this->product,
            'rate' => $this->rate,
            'hide_without_hsn' => $this->hide_without_hsn,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
        ]);
    }

    public function render()
    {
        return view('livewire.reports.gstr-report', [
            'rows'  => $this->query()->paginate($this->perPage),
            'rates' => $this->getDistinctRates(),
        ]);
    }

    /**
     * Pull distinct GST rates from sale_details (non-Draft sales only) and
     * normalize to percent units (e.g. 5, 18).
     */
    protected function getDistinctRates(): array
    {
        return SaleDetails::query()
            ->selectRaw('DISTINCT tax_percentage as rate')
            ->whereHas('sale', fn ($q) => $q->where(
                fn ($s) => $s->whereNull('status')->orWhere('status', '!=', 'Draft')
            ))
            ->orderBy('tax_percentage')
            ->pluck('rate')
            ->map(fn ($r) => ($rf = (float) $r) > 1 ? $rf : $rf * 100)
            ->unique()
            ->sort()
            ->values()
            ->toArray();
    }
}
