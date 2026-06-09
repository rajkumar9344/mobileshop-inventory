<?php

namespace Modules\Product\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProductCode extends Model
{
    use HasFactory;

    protected $table = 'product_codes';

    protected $fillable = ['product_id', 'code', 'is_primary'];

    protected $casts = ['is_primary' => 'boolean'];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }
}
