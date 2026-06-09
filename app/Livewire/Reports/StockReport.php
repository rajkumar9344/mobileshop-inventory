<?php

namespace App\Livewire\Reports;

use Livewire\Component;
use Livewire\WithPagination;
use Modules\Product\Entities\Product;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ReorderReportExport;
use PDF;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StockReport extends Component
{

    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    public $categories;
    public $category_id;
    public $search;
    public $supplier_id;
    public $compatibility;
    public $generated_date_from;
    public $generated_date_to;

    public function mount($categories) {
        $this->categories = collect($categories)->sortBy(function($c) {
            return $c->category_name ?? ($c->name ?? '');
        }, SORT_NATURAL|SORT_FLAG_CASE)->values();
        $this->category_id = '';
        $this->search = '';
        $this->supplier_id = '';
        $this->compatibility = '';
        $this->generated_date_from = '';
        $this->generated_date_to = '';
    }

    public function render() {
        $suppliers = \Modules\People\Entities\Supplier::where('status', 'active')
            ->select('id','supplier_name')
            ->get()
            ->sortBy(function($s) { return $s->supplier_name; }, SORT_NATURAL|SORT_FLAG_CASE)
            ->values();

        // Build a filters array and use the shared ReportQueryService so listing,
        // PDF and Excel use the same query. Preserve the current behaviour of
        // including products that already have active purchases by setting the
        // flag `include_active_purchases` to true.
        $filters = [
            'category_id' => $this->category_id,
            'supplier_id' => $this->supplier_id,
            'compatibility' => $this->compatibility,
            'generated_date_from' => $this->generated_date_from,
            'generated_date_to' => $this->generated_date_to,
            'search' => $this->search,
            'include_active_purchases' => true,
        ];

        $query = app(\App\Services\ReportQueryService::class)->buildReorderQuery($filters);
        $products = $query->paginate(10);

        return view('livewire.reports.stock-report', [
            'products' => $products,
            'suppliers' => $suppliers
        ]);
    }

    public function generateReport() {
        $this->render();
    }

    public function resetFilters() {
        $this->category_id = '';
        $this->search = '';
        $this->supplier_id = '';
        $this->compatibility = '';
        $this->generated_date_from = '';
        $this->generated_date_to = '';
        $this->resetPage();
    }

    public function exportExcel() {
        $filters = [
            'category_id' => $this->category_id,
            'supplier_id' => $this->supplier_id,
            'compatibility' => $this->compatibility,
            'generated_date_from' => $this->generated_date_from,
            'generated_date_to' => $this->generated_date_to,
            'search' => $this->search,
        ];

        return Excel::download(new ReorderReportExport($filters), 'reorder-report-' . date('Y-m-d') . '.xlsx');
    }

    public function exportPdf() {
        $filters = [
            'category_id' => $this->category_id,
            'supplier_id' => $this->supplier_id,
            'hsn_no' => $this->hsn_no,
            'generated_date_from' => $this->generated_date_from,
            'generated_date_to' => $this->generated_date_to,
            'search' => $this->search,
        ];

        $products = $this->getFilteredProducts();

        $pdf = app(\App\Services\PdfGenerator::class)->make('exports.reorder-report-pdf', [
            'products' => $products,
            'filters' => $filters
        ]);

        // Save PDF to public storage and trigger browser download via JS event
        $filename = 'reorder-report-' . date('Y-m-d') . '-' . Str::random(6) . '.pdf';
        $path = 'exports/' . $filename;
        Storage::disk('public')->put($path, $pdf->output());

        // Redirect browser to the saved PDF so it opens/downloads directly.
        return redirect()->to(Storage::url($path));
    }

    private function getFilteredProducts() {
        $filters = [
            'category_id' => $this->category_id,
            'supplier_id' => $this->supplier_id,
            'compatibility' => $this->compatibility,
            'generated_date_from' => $this->generated_date_from,
            'generated_date_to' => $this->generated_date_to,
            'search' => $this->search,
            'include_active_purchases' => true,
        ];

        return app(\App\Services\ReportQueryService::class)
            ->buildReorderQuery($filters)
            ->get();
    }
}