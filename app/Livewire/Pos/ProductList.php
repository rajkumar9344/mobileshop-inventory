<?php

namespace App\Livewire\Pos;

use Livewire\Component;
use Livewire\WithPagination;
use Modules\Product\Entities\Product;

class ProductList extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    protected $listeners = [
        'selectedCategory' => 'categoryChanged',
        'showCount'        => 'showCountChanged'
    ];

    public $categories;
    public $category_id;
    public $limit = 9;

    public function mount($categories) {
        $this->categories = $categories;
        $this->category_id = '';
    }

    public function render() {
        return view('livewire.pos.product-list', [
            'products' => Product::when($this->category_id, function ($query) {
                return $query->where('category_id', $this->category_id);
            })
            ->paginate($this->limit)
        ]);
    }

    public function categoryChanged($category_id) {
        $this->category_id = $category_id;
        $this->resetPage();
    }

    public function showCountChanged($value) {
        $this->limit = $value;
        $this->resetPage();
    }

    public function selectProduct($product) {
        // Livewire 3 decodes JSON-serialised models as arrays; normalise once here.
        if (is_array($product)) {
            $product = (object) $product;
        }
        // Nested relations (category, rack, bin) may also be arrays after JSON decode.
        $category = is_array($product->category ?? null) ? (object) $product->category : ($product->category ?? null);
        $rack     = is_array($product->rack     ?? null) ? (object) $product->rack     : ($product->rack     ?? null);
        $bin      = is_array($product->bin      ?? null) ? (object) $product->bin      : ($product->bin      ?? null);

        // Convert to array format expected by ProductCart
        $payload = [
            'id' => $product->id,
            'product_name' => $product->product_name,
            'product_code' => $product->product_code,
            'category' => $category ? ($category->category_name ?? null) : null,
            'category_name' => $category ? ($category->category_name ?? null) : null,
            'mrp' => $product->mrp,
            'product_price' => $product->mrp ?? $product->product_price,
            'list_price' => $product->list_price ?? null,
            'product_quantity' => $product->product_quantity,
            'hsn' => $product->hsn ?? null,
            'product_unit' => $product->product_unit,
            'rack' => $rack ? ($rack->rack_name ?? null) : null,
            'bin' => $bin ? ($bin->bin_name ?? null) : null,
            'product_order_tax'  => $product->product_order_tax ?? null,
            'product_tax_type'   => $product->product_tax_type  ?? 0,
        ];

        $this->dispatch('productSelected', $payload);
    }
}
