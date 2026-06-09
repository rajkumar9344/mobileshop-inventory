<?php

namespace App\Livewire\Reports;

use App\Services\LedgerService;
use Livewire\Component;
use Modules\People\Entities\Customer;

class LedgerReport extends Component
{
    public ?int $customer_id = null;
    public string $start_date = '';
    public string $end_date = '';
    public string $financial_year = '';
    public $customers = [];
    public array $data = [];

    public function mount(): void
    {
        $this->customers = Customer::orderBy('customer_name')->get();
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
        $this->customer_id = null;
        $this->financial_year = '';
        $this->setDefaultDates();
        $this->applyFilters();
    }

    public function updatedCustomerId(): void
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
        $this->data = LedgerService::buildLedgerData([
            'customer_id' => $this->customer_id,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
        ]);
    }

    public function render()
    {
        return view('livewire.reports.ledger-report', [
            'customers' => $this->customers,
            'data' => $this->data,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
        ]);
    }
}
