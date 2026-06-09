<?php

namespace Modules\Product\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_code',
        'category_name',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    public function products() {
        return $this->hasMany(Product::class, 'category_id', 'id');
    }

    public function subcategories() {
        return $this->hasMany(Subcategory::class);
    }
}
