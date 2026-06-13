<?php

namespace App\Services;

use Modules\Product\Entities\ProductCode;
use Modules\Product\Entities\Product;

class ProductCodeResolver
{
    // In-memory cache keyed by product_id
    private $cache = [];

    public function preload(array $productIds)
    {
        $this->cache = [];
        $productIds = array_values(array_filter(array_map('intval', $productIds)));
        if (empty($productIds)) return;

        $rows = ProductCode::whereIn('product_id', $productIds)->get();
        foreach ($rows as $r) {
            $pid = $r->product_id;
            $raw = trim((string) $r->code);
            $lower = strtolower($raw);
            $trimmedLower = strtolower(ltrim($raw, '0'));
            if (!isset($this->cache[$pid])) {
                $this->cache[$pid] = ['byCode' => [], 'codes' => []];
            }
            $this->cache[$pid]['byCode'][$lower] = $r->id;
            if ($trimmedLower !== $lower) {
                $this->cache[$pid]['byCode'][$trimmedLower] = $r->id;
            }
            $this->cache[$pid]['codes'][] = $raw;
        }
    }

    public function resolve(int $productId, $code)
    {
        // product_code_id is NOT NULL in the detail tables, so never return null for a
        // product that has any code — fall back to its first code when none is given/matched.
        if (empty($code)) return $this->firstCodeId($productId);
        $c = trim((string) $code);

        // check cache
        if (!empty($this->cache[$productId]['byCode'])) {
            $lower = strtolower($c);
            if (isset($this->cache[$productId]['byCode'][$lower])) {
                return $this->cache[$productId]['byCode'][$lower];
            }
            $trimmed = strtolower(ltrim($c, '0'));
            if ($trimmed !== '' && isset($this->cache[$productId]['byCode'][$trimmed])) {
                return $this->cache[$productId]['byCode'][$trimmed];
            }
        }

        // fallbacks to DB
        $id = ProductCode::where('product_id', $productId)->where('code', $c)->value('id');
        if ($id) return $id;

        $id = ProductCode::where('product_id', $productId)->whereRaw('LOWER(code) = ?', [strtolower($c)])->value('id');
        if ($id) return $id;

        $c2 = ltrim($c, '0');
        if ($c2 !== '' && $c2 !== $c) {
            $id = ProductCode::where('product_id', $productId)->where('code', $c2)->value('id');
            if ($id) return $id;
        }

        $primary = Product::where('id', $productId)->value('product_code');
        if (!empty($primary) && trim($primary) === $c) {
            $id = ProductCode::where('product_id', $productId)->where('code', $primary)->value('id');
            if ($id) return $id;
        }

        // last-resort LIKE
        $id = ProductCode::where('product_id', $productId)->where('code', 'like', "%{$c}%")->value('id');
        return $id ?: $this->firstCodeId($productId);
    }

    /**
     * First/any product code id for a product (from cache, else DB). Null only when
     * the product genuinely has no codes at all.
     */
    private function firstCodeId(int $productId)
    {
        if (!empty($this->cache[$productId]['byCode'])) {
            return reset($this->cache[$productId]['byCode']);
        }
        return ProductCode::where('product_id', $productId)->value('id');
    }
}
