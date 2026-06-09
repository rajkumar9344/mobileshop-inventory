<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;

class QueryFilters
{
    /**
     * Apply date filters to a query based on start/end (preferred) or year/month fallback.
     * $dateColumn should be a string like 'sales.date' or 'purchases.date'.
     */
    public static function applyDateFilters($query, $start, $end, $year = null, $month = null, $dateColumn = 'date')
    {
        if ($start && $end) {
            try {
                // Use full datetime range so DATETIME columns (eg. created_at)
                // include the entire selected days. Previously we used
                // toDateString() which produced 'YYYY-MM-DD' and caused
                // datetimes later in the day to be excluded.
                $s = \Carbon\Carbon::parse($start)->startOfDay()->toDateTimeString();
                $e = \Carbon\Carbon::parse($end)->endOfDay()->toDateTimeString();
                $query->whereBetween($dateColumn, [$s, $e]);
            } catch (\Exception $ex) {
                // ignore and fall back to year/month
            }
        } else {
            if ($year) {
                $query->whereYear($dateColumn, $year);
            }
            if ($month) {
                $query->whereMonth($dateColumn, str_pad($month, 2, '0', STR_PAD_LEFT));
            }
        }

        return $query;
    }

    /**
     * Apply a simple global search over name/area columns.
     * $nameColumn and $areaColumn should be fully qualified column names (eg. 'customers.customer_name').
     */
    public static function applyGlobalSearch($query, $search, $nameColumn = 'customer_name', $areaColumn = 'area')
    {
        if (empty($search)) return $query;

        $query->where(function($q) use ($search, $nameColumn, $areaColumn) {
            $q->where($nameColumn, 'like', "%{$search}%")
              ->orWhere($areaColumn, 'like', "%{$search}%");
        });

        return $query;
    }
}
