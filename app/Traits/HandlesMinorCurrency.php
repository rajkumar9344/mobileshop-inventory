<?php

namespace App\Traits;

trait HandlesMinorCurrency
{
    /**
     * Convert major-unit amount (rupees) to minor units (paise) for storage.
     */
    protected function toMinor($value)
    {
        if ($value === null || $value === '') return null;
        if (is_string($value)) $value = str_replace(',', '', $value);
        return (int) round(floatval($value) * 100);
    }

    /**
     * Ensure the provided value is in minor units. If it appears already
     * to be in minor units (large integer), return as-is; otherwise convert.
     */
    protected function ensureMinorValue($value)
    {
        if ($value === null || $value === '') return null;

        if (is_numeric($value)) {
            $numeric = floatval($value);
            if ($numeric > 1000000 && floor($numeric) == $numeric) {
                return (int) $numeric;
            }
        }

        return $this->toMinor($value);
    }
}
