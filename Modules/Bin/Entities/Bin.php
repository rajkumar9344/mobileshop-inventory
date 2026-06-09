<?php

namespace Modules\Bin\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Bin extends Model
{
    use HasFactory;

    protected $table = 'bins';

    protected $fillable = [
        'rack_id',
        'bin_id',
        'bin_name',
        'capacity',
        'status',
        'barcode',
    ];

    protected $casts = [
        'rack_id' => 'integer',
        'capacity' => 'integer',
        'status' => 'string',
        'barcode' => 'string',
    ];

    public function rack()
    {
        return $this->belongsTo(\Modules\Rack\Entities\Rack::class);
    }
}