<?php

namespace App\Livewire\Reports;

use Livewire\Component;
use Livewire\WithPagination;
use Modules\People\Entities\Customer;
use Illuminate\Pagination\LengthAwarePaginator;

class CustomersPaymentReport extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    public $customer_id = '';
    public $reference = '';
    public $payment_mode = '';
    public $start_date;
    public $end_date;

    protected $listeners = ['setDateRange'];

    protected $queryString = [
        'customer_id' => ['except' => ''],
        'reference' => ['except' => ''],
        'payment_mode' => ['except' => ''],
        'start_date' => ['except' => ''],
        'end_date' => ['except' => ''],
    ];

    public function mount()
    {
        $this->start_date = today()->subDays(30)->format('Y-m-d');
        $this->end_date = today()->format('Y-m-d');
    }

    public function updatingCustomerId()
    {
        $this->resetPage();
    }

    public function updatingReference()
    {
        $this->resetPage();
    }

    public function updatingPaymentMode()
    {
        $this->resetPage();
    }

    public function updatingStartDate()
    {
        $this->resetPage();
    }

    public function updatingEndDate()
    {
        $this->resetPage();
    }

    public function resetFilters()
    {
        $this->customer_id = '';
        $this->reference = '';
        $this->payment_mode = '';
        $this->start_date = today()->subDays(30)->format('Y-m-d');
        $this->end_date = today()->format('Y-m-d');
        $this->resetPage();
    }

    public function setDateRange($start, $end)
    {
        $this->start_date = $start;
        $this->end_date = $end;
        $this->resetPage();
    }

    public function render()
    {
        $customers = Customer::where('is_active', true)
            ->orderBy('customer_name')
            ->get(['id', 'customer_name']);

        $all = app(\App\Services\ReportQueryService::class)->getCustomersPaymentCollection([
            'customer_id'  => $this->customer_id,
            'reference'    => $this->reference,
            'payment_mode' => $this->payment_mode,
            'start_date'   => $this->start_date,
            'end_date'     => $this->end_date,
        ]);

        $perPage = 15;
        $page    = $this->getPage();
        $items   = $all->slice(($page - 1) * $perPage, $perPage)->values();

        $payments = new LengthAwarePaginator($items, $all->count(), $perPage, $page, [
            'path'     => LengthAwarePaginator::resolveCurrentPath(),
            'pageName' => 'page',
        ]);

        return view('livewire.reports.customers-payment-report', [
            'customers' => $customers,
            'payments'  => $payments,
        ]);
    }
}
