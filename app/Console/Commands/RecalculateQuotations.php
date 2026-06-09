<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Quotation\Entities\Quotation;

class RecalculateQuotations extends Command
{
    protected $signature = 'quotations:recalc {reference?} {--fix}';
    protected $description = 'Recalculate quotation totals from details and optionally fix stored values';

    public function handle()
    {
        $ref = $this->argument('reference');
        $fix = $this->option('fix');

        $query = Quotation::query();
        if ($ref) {
            $query->where('reference', $ref);
        }

        $count = 0;
        foreach ($query->with('quotationDetails')->get() as $q) {
            $count++;
            $details = $q->quotationDetails;
            $calcGross = 0.0;
            $calcTax = 0.0;
            $calcDiscount = 0.0;

            foreach ($details as $d) {
                $qty = (float) ($d->quantity ?? 0);
                $rate = (float) ($d->rate ?? 0);
                $tax = (float) ($d->product_tax_amount ?? 0);
                $discount = (float) ($d->product_discount_amount ?? 0);

                $calcGross += $rate * $qty;
                $calcTax += $tax;
                $calcDiscount += $discount;
            }

            $calcAmount = round($calcGross, 2);
            $calcNet = round($calcAmount + $calcTax + (float) ($q->overall_other ?? 0) - ($q->discount_amount ?? 0), 2);

            $storedNet = (float) $q->overall_net_rate;
            $storedGross = (float) $q->overall_amount;

            $this->line("Quotation {$q->reference}: stored_net={$storedNet}, calc_net={$calcNet}, stored_gross={$storedGross}, calc_gross={$calcAmount}");

            if ($fix) {
                $q->overall_gross_amount = $calcAmount;
                $q->overall_tax_amount = $calcTax;
                $q->overall_amount = $calcAmount;
                $q->overall_net_rate = $calcNet;
                $q->discount_amount = $calcDiscount;
                $q->save();
                $this->info(" -> Fixed {$q->reference}");
            }
        }

        if ($count === 0) {
            $this->info('No quotations found');
        }

        return 0;
    }
}
