<?php

namespace App\Livewire\Reports;

use Livewire\Component;
use Livewire\WithPagination;
use Modules\Sale\Entities\Sale;
use Modules\People\Entities\Customer;
use Carbon\Carbon;

class SalesOutstandingReport extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    public $customer_id = '';
    public $reference = '';
    public $aging_range = '';

    protected $queryString = [
        'customer_id' => ['except' => ''],
        'reference' => ['except' => ''],
        'aging_range' => ['except' => ''],
    ];

    public function updatingCustomerId()
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
        $this->customer_id = '';
        $this->reference = '';
        $this->aging_range = '';
        $this->resetPage();
    }

    public function render()
    {
        $customers = Customer::where('is_active', true)
            ->orderBy('customer_name')
            ->get(['id', 'customer_name']);

        $sales = $this->getFilteredSalesQuery()->paginate(15);

        return view('livewire.reports.sales-outstanding-report', [
            'sales' => $sales,
            'customers' => $customers,
            'agingRanges' => $this->getAgingRanges(),
        ]);
    }

    public function getFilteredSalesQuery()
    {
        $today = Carbon::today();

        $query = Sale::query()
            ->whereIn('payment_status', ['Unpaid', 'Pending', 'Partial', 'Partially Paid'])
            ->where('status', '!=', 'Draft')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', $today)
            ->with('customer:id,customer_name');

        // Filter by customer
        if (!empty($this->customer_id)) {
            $query->where('customer_id', $this->customer_id);
        }

        // Filter by reference
        if (!empty($this->reference)) {
            $query->where('reference', 'like', '%' . $this->reference . '%');
        }

        // Filter by aging range
        if (!empty($this->aging_range)) {
            $query = $this->applyAgingFilter($query, $this->aging_range, $today);
        }

        // Order by aging (most aged first)
        // Calculate aging as days between due_date and today
        // Order descending so oldest (highest aging) comes first
        $query->orderByRaw('DATEDIFF(?, due_date) DESC', [$today]);

        return $query;
    }

    protected function applyAgingFilter($query, $range, $today)
    {
        switch ($range) {
            case '1-10':
                $query->whereDate('due_date', '>=', $today->copy()->subDays(10))
                      ->whereDate('due_date', '<', $today);
                break;
            case '10-20':
                $query->whereDate('due_date', '>=', $today->copy()->subDays(20))
                      ->whereDate('due_date', '<', $today->copy()->subDays(10));
                break;
            case '20-30':
                $query->whereDate('due_date', '>=', $today->copy()->subDays(30))
                      ->whereDate('due_date', '<', $today->copy()->subDays(20));
                break;
            case '30-60':
                $query->whereDate('due_date', '>=', $today->copy()->subDays(60))
                      ->whereDate('due_date', '<', $today->copy()->subDays(30));
                break;
            case '60-90':
                $query->whereDate('due_date', '>=', $today->copy()->subDays(90))
                      ->whereDate('due_date', '<', $today->copy()->subDays(60));
                break;
            case '90+':
                $query->whereDate('due_date', '<', $today->copy()->subDays(90));
                break;
        }
        return $query;
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

    /**
     * Calculate aging days for a sale
     */
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

    /**
     * Get aging badge class based on days
     */
    public static function getAgingBadgeClass($agingDays)
    {
        if ($agingDays > 90) {
            return 'badge-danger';
        } elseif ($agingDays > 60) {
            return 'badge-warning';
        } elseif ($agingDays > 30) {
            return 'badge-info';
        } else {
            return 'badge-secondary';
        }
    }
}
