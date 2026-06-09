<?php

namespace Modules\Setting\Entities;

use Illuminate\Database\Eloquent\Model;

class PettyCashEntry extends Model
{
    protected $table = 'petty_cash_entries';
    protected $guarded = [];

    protected $casts = [
        'amount' => 'decimal:2',
        'date' => 'date',
    ];
}
