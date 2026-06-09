<?php

namespace App\Livewire\Reports;

use App\Services\LedgerService;
use Livewire\Component;
use Modules\People\Entities\Supplier;

class SupplierLedgerReport extends Component
{
    public ?int $supplier_id = null;
    public string $start_date = '';
    public string $end_date = '';
    public string $financial_year = '';
    public $suppliers = [];
    public array $data = [];

    public function mount(): void
    {
        $this->suppliers = Supplier::orderBy('supplier_name')->get();
        $this->setDefaultDates();
        $this->applyFilters();
    }

    public function updatedFinancialYear(): void
    {
        if (!empty($this->financial_year)) {
            $dates = LedgerService::getFinancialYearDates($this->financial_year);
            $this->start_date = $dates['start_date'];
            $this->end_date = $dates['end_date'];
            $this->applyFilters();
        }
    }

    public function resetFilters(): void
    {
        $this->supplier_id = null;
        $this->financial_year = '';
        $this->setDefaultDates();
        $this->applyFilters();
    }

    public function updatedSupplierId(): void
    {
        $this->applyFilters();
    }

    public function updatedStartDate(): void
    {
        $this->applyFilters();
    }

    public function updatedEndDate(): void
    {
        $this->applyFilters();
    }

    protected function setDefaultDates(): void
    {
        $dates = LedgerService::getFinancialYearDates();
        $this->start_date = $dates['start_date'];
        $this->end_date = $dates['end_date'];
    }

    public function applyFilters(): void
    {
        $this->data = LedgerService::buildSupplierLedgerData([
            'supplier_id' => $this->supplier_id,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
        ]);
    }

    public function render()
    {
        return view('livewire.reports.supplier-ledger-report', [
            'suppliers' => $this->suppliers,
            'data' => $this->data,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
        ]);
    }
}
