<?php

namespace App\Livewire\Reports;

use Livewire\Component;
use Livewire\WithPagination;
use Modules\Product\Entities\Product;

class CurrentStockReport extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    public $search;

    public function mount() {
        $this->search = '';
    }

    public function updatingSearch() {
        $this->resetPage();
    }

    public function render() {
        $products = Product::query()
            ->select('id', 'product_name', 'product_code', 'product_quantity')
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('product_name', 'like', '%' . $this->search . '%')
                      ->orWhere('product_code', 'like', '%' . $this->search . '%');
                });
            })
            ->orderBy('product_name')
            ->paginate(25);

        return view('livewire.reports.current-stock-report', [
            'products' => $products,
        ]);
    }

    public function resetFilters() {
        $this->search = '';
        $this->resetPage();
    }
}
