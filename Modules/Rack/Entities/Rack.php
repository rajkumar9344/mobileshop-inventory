<?php

namespace Modules\Rack\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Rack extends Model
{
    use HasFactory;

    protected $table = 'rack_master';

    protected $fillable = [
        'rack_id',
        'rack_name',
        'barcode',
        'status',
    ];

    protected $casts = [
        'barcode' => 'string',
        'status' => 'string',
    ];

    public function bins()
    {
        return $this->hasMany(\Modules\Bin\Entities\Bin::class);
    }
}