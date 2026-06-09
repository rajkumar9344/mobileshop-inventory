<?php

namespace Modules\Sale\Events;

use Illuminate\Queue\SerializesModels;

class SaleFullyPaid
{
    use SerializesModels;

    /** @var int */
    public $saleId;

    public function __construct(int $saleId)
    {
        $this->saleId = $saleId;
    }
}
