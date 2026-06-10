<?php

namespace App\Livewire;

use Illuminate\Support\Collection;
use Livewire\Attributes\Rule;
use Livewire\Component;
use Modules\Product\Entities\Product;
use Modules\Product\Entities\Category;
use Modules\Product\Entities\ProductCode;
use Modules\Product\Entities\Subcategory;

class SearchProduct extends Component
{

    public $global_discount_amount = 0;

    #[Rule('nullable|string|max:100')]
    public $query;
    public $search_results;
    public $category_id;
    public $subcategory_id;
    public $context; // 'sale', 'purchase', or 'purchase_return'
    public $showResults  = false;
    public $totalCount   = 0;   // total matched products (before any limit)
    public $isLimited    = false; // true when we're showing only the first 20

    // How many to show when no search text (or only 1 char) — just browsing
    const BROWSE_LIMIT = 20;

    // Columns fetched from the products table — defined once to avoid repetition
    const SELECT_COLUMNS = [
        'products.id',
        'products.category_id',
        'products.product_name',
        'products.product_code',
        'products.product_quantity',
        'products.purchase_quantity',
        'products.open_quantity',
        'products.product_cost',
        'products.product_price',
        'products.product_unit',
        'products.product_order_tax',
        'products.product_note',
        'products.list_price',
    ];

    public function mount($context = 'sale') {
        $this->query        = '';
        $this->category_id  = '';
        $this->subcategory_id = '';
        $this->context      = $context;
        $this->search_results = Collection::empty();
        $this->showResults  = false;
        $this->totalCount   = 0;
        $this->isLimited    = false;
    }

    public function render() {
        // Load brands (categories) and subcategories with natural, case-insensitive ordering
        $categories = Category::where('status', true)
            ->select('id', 'category_name')
            ->get()
            ->sortBy(function($c){ return $c->category_name; }, SORT_NATURAL|SORT_FLAG_CASE)
            ->values();

        // Preload subcategories: if a category is selected, show its subcategories;
        // otherwise show all active subcategories so the user can filter by subcategory directly.
        $subcategoriesQuery = Subcategory::where('status', true)->select('id', 'subcategory_name', 'category_id');
        if ($this->category_id) {
            $subcategoriesQuery->where('category_id', $this->category_id);
        }
        $subcategories = $subcategoriesQuery->get();
        // When no category is selected, deduplicate subcategories by name (case-insensitive)
        if (empty($this->category_id)) {
            $subcategories = $subcategories->unique(function($s){
                $n = preg_replace('/\s+/u', ' ', trim($s->subcategory_name));
                return mb_strtolower($n);
            })->values();
        }
        $subcategories = $subcategories->sortBy(function($s){
            $n = preg_replace('/\s+/u', ' ', trim($s->subcategory_name));
            return $n;
        }, SORT_NATURAL|SORT_FLAG_CASE)->values();

        return view('livewire.search-product', compact('categories', 'subcategories'));
    }

    public function updatedQuery() {
        $this->performSearch();
    }

    public function updatedCategoryId() {
        // Reset subcategory when brand changes; hide stale results before re-fetching
        $this->subcategory_id = '';
        $this->showResults = false;
        $this->performSearch();
    }

    public function updatedSubcategoryId() {
        $this->performSearch();
    }

    public function performSearch() {
        // If no filter is active at all, clear results and return
        if (empty($this->query) && empty($this->category_id) && empty($this->subcategory_id)) {
            $this->search_results = Collection::empty();
            $this->totalCount  = 0;
            $this->isLimited   = false;
            return;
        }

        // Named $builder to avoid confusion with the $this->query search-text property
        $builder = Product::query()->with(['category']);

        if ($this->category_id) {
            $builder->where('products.category_id', $this->category_id);
        }

        if ($this->subcategory_id) {
            // If the value is numeric we received a real subcategory ID (category-filtered select).
            // If it's non-numeric we received a deduplicated subcategory NAME (no category selected),
            // so resolve matching subcategory IDs across all brands and filter by those IDs.
            if (is_numeric($this->subcategory_id)) {
                $builder->where('products.subcategory_id', (int) $this->subcategory_id);
            } else {
                // Normalize client-provided name (lowercase, collapse spaces)
                $normalized = mb_strtolower(preg_replace('/\s+/u', ' ', trim($this->subcategory_id)));

                // Attempt an exact normalized match via a DB query (avoids loading all rows into memory)
                $matchedIds = Subcategory::where('status', true)
                    ->whereRaw('LOWER(REPLACE(REGEXP_REPLACE(subcategory_name, "\\s+", " "), "\r", "")) = ?', [$normalized])
                    ->pluck('id')
                    ->toArray();

                if (!empty($matchedIds)) {
                    $builder->whereIn('products.subcategory_id', $matchedIds);
                } else {
                    // Fall back to a case-insensitive LIKE search if DB doesn't support the exact normalization above
                    // This uses a WHERE HAS to match related subcategory names.
                    $builder->whereHas('subcategory', function ($q) use ($normalized) {
                        $q->whereRaw('LOWER(subcategory_name) like ?', ["%{$normalized}%"]);
                    });
                }
            }
        }

        // Only apply text filter when the user has typed something
        if (!empty($this->query)) {
            $builder->where(function ($sub) {
                $sub->where('products.product_name', 'like', '%' . $this->query . '%')
                    ->orWhere('products.product_code', 'like', '%' . $this->query . '%')
                    ->orWhereHas('productCodes', function ($pc) {
                        $pc->where('code', 'like', '%' . $this->query . '%');
                    });
            });
        }

        // Smart hybrid logic:
        // - 0 or 1 character typed  → cap at BROWSE_LIMIT (fast, avoids loading 200+ rows)
        // - 2+ characters typed     → show ALL matches (text is specific enough)
        $queryLen = mb_strlen(trim($this->query));

        if ($queryLen < 2) {
            // Count first only when we need the limit banner
            $this->totalCount = (clone $builder)->distinct()->count('products.id');
            $products = $builder->select(self::SELECT_COLUMNS)->distinct()->take(self::BROWSE_LIMIT)->get();
            $this->isLimited = $this->totalCount > self::BROWSE_LIMIT;
        } else {
            // For a specific text search, fetch all matches in one query
            $products = $builder->select(self::SELECT_COLUMNS)->distinct()->get();
            $this->totalCount = $products->count();
            $this->isLimited = false;
        }

        $isPurchaseReturn = $this->context === 'purchase_return';

        // Load all codes for these products in one query (avoids N+1)
        $productIds     = $products->pluck('id')->toArray();
        $codesGrouped   = ProductCode::whereIn('product_id', $productIds)
            ->orderByDesc('is_primary')
            ->get()
            ->groupBy('product_id');

        $this->search_results = $products->map(function ($product) use ($isPurchaseReturn, $codesGrouped) {
            $availableStock = $isPurchaseReturn ? ($product->purchase_quantity ?? 0) : $product->product_quantity;
            $allCodes = $codesGrouped->get($product->id, collect())->pluck('code')->toArray();
            if (empty($allCodes)) {
                $allCodes = [$product->product_code];
            }

            return (object) [
                'id'               => $product->id,
                'product_name'     => $product->product_name,
                'product_code'     => $product->product_code,
                'product_codes'    => $allCodes,
                'category'         => $product->category?->category_name,
                'product_cost'     => $product->product_cost ?? null,
                'product_price'    => $product->product_price,
                'list_price'       => $product->list_price,
                'product_quantity' => $product->product_quantity,
                'purchase_quantity'=> $product->purchase_quantity ?? 0,
                'stock'            => $availableStock,
                'product_unit'     => $product->product_unit,
                'product_order_tax'=> $product->product_order_tax ?? null,
                'product_note'     => $product->product_note ?? null,
            ];
        });

        $this->showResults = true;
    }

    /**
     * Only close the dropdown panel — keeps brand/subcategory selections intact.
     * Used when the user clicks outside the dropdown.
     */
    public function closeDropdown() {
        $this->showResults  = false;
        $this->totalCount   = 0;
        $this->isLimited    = false;
        $this->search_results = Collection::empty();
    }

    /**
     * Full reset — clears all filters including brand and subcategory.
     * Used by the "Clear All" button.
     */
    public function resetQuery() {
        $this->query        = '';
        $this->category_id  = '';
        $this->subcategory_id = '';
        $this->showResults  = false;
        $this->totalCount   = 0;
        $this->isLimited    = false;
        $this->search_results = Collection::empty();
    }

    public function selectProduct($product) {
        // Accept product id, load fresh product and dispatch a simple array payload
        $productModel = Product::with(['category'])->find($product);
        if (!$productModel) return;

        $allCodes = ProductCode::where('product_id', $productModel->id)
            ->orderByDesc('is_primary')
            ->pluck('code')
            ->toArray();
        if (empty($allCodes)) {
            $allCodes = [$productModel->product_code];
        }

        $payload = [
            'id' => $productModel->id,
            'product_name' => $productModel->product_name,
            'product_code' => $productModel->product_code,
            'product_codes' => $allCodes,
            'category' => $productModel->category ? $productModel->category->category_name : null,
            'category_name' => $productModel->category ? $productModel->category->category_name : null,
            'product_cost' => $productModel->product_cost ?? null,
            'product_price' => $productModel->product_price,
            'list_price' => $productModel->list_price,
            'product_quantity' => $productModel->product_quantity,
            'open_quantity' => $productModel->open_quantity ?? 0,
            'purchase_quantity' => $productModel->purchase_quantity ?? 0,
            'product_unit' => $productModel->product_unit,
            'product_order_tax' => $productModel->product_order_tax,
        ];

        $this->dispatch('productSelected', $payload);
        $this->resetQuery();
    }
}
