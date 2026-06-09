<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $table = 'quotation_details';

        // Helper to fetch column data types for the table
        $cols = collect(DB::select("SELECT COLUMN_NAME, DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?", [$table]))
            ->mapWithKeys(fn($r) => [$r->COLUMN_NAME => $r->DATA_TYPE]);

        // Columns we want to convert from DECIMAL to BIGINT (paise)
        $decimalCols = [
            'mrp',
            'rate',
            'tax_amount',
            'cash_discount_amount',
            'discount_amount',
        ];

        // Only operate on columns that exist and are decimal
        $toConvert = array_filter($decimalCols, function ($c) use ($cols) {
            return isset($cols[$c]) && in_array(strtolower($cols[$c]), ['decimal','double','float']);
        });

        if (empty($toConvert)) {
            return; // nothing to do
        }

        // Add temporary bigint columns
        Schema::table($table, function (Blueprint $t) use ($toConvert) {
            foreach ($toConvert as $c) {
                if (!Schema::hasColumn('quotation_details', $c . '_tmp')) {
                    $t->bigInteger($c . '_tmp')->nullable();
                }
            }
        });

        // Populate tmp columns using per-row heuristic: compare related integer columns
        // and decide whether decimal values are rupees (need *100) or already paise.
        // Note: adjust comparisons as needed for your data patterns.
        $updates = [];

        if (in_array('mrp', $toConvert)) {
            $updates[] = "mrp_tmp = CASE
                WHEN mrp IS NULL THEN NULL
                WHEN ABS(price - (mrp * 100)) < ABS(price - mrp) THEN CAST(ROUND(mrp * 100) AS SIGNED)
                ELSE CAST(ROUND(mrp) AS SIGNED)
            END";
        }

        if (in_array('rate', $toConvert)) {
            $updates[] = "rate_tmp = CASE
                WHEN rate IS NULL THEN NULL
                WHEN ABS(unit_price - (rate * 100)) < ABS(unit_price - rate) THEN CAST(ROUND(rate * 100) AS SIGNED)
                ELSE CAST(ROUND(rate) AS SIGNED)
            END";
        }

        if (in_array('tax_amount', $toConvert)) {
            $updates[] = "tax_amount_tmp = CASE
                WHEN tax_amount IS NULL THEN NULL
                WHEN ABS(product_tax_amount - (tax_amount * 100)) < ABS(product_tax_amount - tax_amount) THEN CAST(ROUND(tax_amount * 100) AS SIGNED)
                ELSE CAST(ROUND(tax_amount) AS SIGNED)
            END";
        }

        if (in_array('cash_discount_amount', $toConvert)) {
            $updates[] = "cash_discount_amount_tmp = CASE
                WHEN cash_discount_amount IS NULL THEN NULL
                WHEN ABS(unit_price - (cash_discount_amount * 100)) < ABS(unit_price - cash_discount_amount) THEN CAST(ROUND(cash_discount_amount * 100) AS SIGNED)
                ELSE CAST(ROUND(cash_discount_amount) AS SIGNED)
            END";
        }

        if (in_array('discount_amount', $toConvert)) {
            $updates[] = "discount_amount_tmp = CASE
                WHEN discount_amount IS NULL THEN NULL
                WHEN ABS(sub_total - (discount_amount * 100)) < ABS(sub_total - discount_amount) THEN CAST(ROUND(discount_amount * 100) AS SIGNED)
                ELSE CAST(ROUND(discount_amount) AS SIGNED)
            END";
        }

        if (!empty($updates)) {
            $sql = 'UPDATE ' . $table . ' SET ' . implode(",\n", $updates);
            DB::statement($sql);
        }

        // Validate manually on staging before proceeding to drop/replace columns.
        // Now copy tmp values into new bigint columns safely by dropping decimal columns and recreating as bigint.
        Schema::table($table, function (Blueprint $t) use ($toConvert) {
            foreach ($toConvert as $c) {
                if (Schema::hasColumn('quotation_details', $c)) {
                    $t->dropColumn($c);
                }
            }
        });

        Schema::table($table, function (Blueprint $t) use ($toConvert) {
            foreach ($toConvert as $c) {
                if (!Schema::hasColumn('quotation_details', $c)) {
                    $t->bigInteger($c)->default(0);
                }
            }
        });

        // Move tmp values into final columns
        $sets = array_map(fn($c) => "$c = {$c}_tmp", $toConvert);
        DB::statement('UPDATE ' . $table . ' SET ' . implode(', ', $sets));

        // Drop tmp columns
        Schema::table($table, function (Blueprint $t) use ($toConvert) {
            foreach ($toConvert as $c) {
                if (Schema::hasColumn('quotation_details', $c . '_tmp')) {
                    $t->dropColumn($c . '_tmp');
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $table = 'quotation_details';

        // If we need to revert, create decimal tmp columns and attempt to convert back by dividing by 100
        $cols = ['mrp','rate','tax_amount','cash_discount_amount','discount_amount'];

        Schema::table($table, function (Blueprint $t) use ($cols) {
            foreach ($cols as $c) {
                if (!Schema::hasColumn('quotation_details', $c . '_dec_tmp')) {
                    $t->decimal($c . '_dec_tmp', 15, 2)->nullable();
                }
            }
        });

        // Populate decimal tmp by deciding if values look like paise (divide by 100) or already decimal
        $updates = [];
        foreach ($cols as $c) {
            $updates[] = "{$c}_dec_tmp = CASE
                WHEN {$c} IS NULL THEN NULL
                WHEN {$c} > 1000000 THEN ROUND({$c} / 100, 2)
                ELSE ROUND({$c}, 2)
            END";
        }

        DB::statement('UPDATE ' . $table . ' SET ' . implode(', ', $updates));

        // Drop bigint columns and recreate decimals
        Schema::table($table, function (Blueprint $t) use ($cols) {
            foreach ($cols as $c) {
                if (Schema::hasColumn('quotation_details', $c)) {
                    $t->dropColumn($c);
                }
            }
        });

        Schema::table($table, function (Blueprint $t) use ($cols) {
            foreach ($cols as $c) {
                if (!Schema::hasColumn('quotation_details', $c)) {
                    $t->decimal($c, 15, 2)->default(0);
                }
            }
        });

        // Move back values from tmp
        $sets = array_map(fn($c) => "$c = {$c}_dec_tmp", $cols);
        DB::statement('UPDATE ' . $table . ' SET ' . implode(', ', $sets));

        // Drop tmp fields
        Schema::table($table, function (Blueprint $t) use ($cols) {
            foreach ($cols as $c) {
                if (Schema::hasColumn('quotation_details', $c . '_dec_tmp')) {
                    $t->dropColumn($c . '_dec_tmp');
                }
            }
        });
    }
};
