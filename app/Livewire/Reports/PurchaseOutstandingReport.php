<?php

namespace App\Livewire\Reports;

use Livewire\Component;
use Livewire\WithPagination;
use Modules\Purchase\Entities\Purchase;
use Modules\People\Entities\Supplier;
use Carbon\Carbon;

class PurchaseOutstandingReport extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    public $supplier_id = '';
    public $reference = '';
    public $aging_range = '';

    protected $queryString = [
        'supplier_id' => ['except' => ''],
        'reference' => ['except' => ''],
        'aging_range' => ['except' => ''],
    ];

    public function updatingSupplierId()
    {
        $this->resetPage();
    }

    public function updatingReference()
    {
        $this->resetPage();
    }

    public function updatingAgingRange()
    {
        $this->resetPage();
    }

    public function resetFilters()
    {
        $this->supplier_id = '';
        $this->reference = '';
        $this->aging_range = '';
        $this->resetPage();
    }

    public function render()
    {
        $suppliers = Supplier::where('status', 'active')
            ->orderBy('supplier_name')
            ->get(['id', 'supplier_name']);

        $purchases = $this->getFilteredPurchasesQuery()->paginate(15);

        return view('livewire.reports.purchase-outstanding-report', [
            'purchases' => $purchases,
            'suppliers' => $suppliers,
            'agingRanges' => $this->getAgingRanges(),
        ]);
    }

    public function getFilteredPurchasesQuery()
    {
        return app(\App\Services\ReportQueryService::class)->buildPurchaseOutstandingQuery([
            'supplier_id' => $this->supplier_id,
            'reference' => $this->reference,
            'aging_range' => $this->aging_range,
        ]);
    }

    protected function getAgingRanges()
    {
        return [
            '1-10' => '1-10 Days',
            '10-20' => '10-20 Days',
            '20-30' => '20-30 Days',
            '30-60' => '30-60 Days',
            '60-90' => '60-90 Days',
            '90+' => 'More than 90 Days',
        ];
    }

    public static function calculateAging($dueDate)
    {
        if (!$dueDate) {
            return 0;
        }

        $due = Carbon::parse($dueDate);
        $today = Carbon::today();

        if ($due >= $today) {
            return 0;
        }

        return $today->diffInDays($due);
    }

    public static function getAgingBadgeClass($agingDays)
    {
        if ($agingDays > 90) {
            return 'badge-danger';
        } elseif ($agingDays > 60) {
            return 'badge-warning';
        } elseif ($agingDays > 30) {
            return 'badge-info';
        }

        return 'badge-secondary';
    }
}
