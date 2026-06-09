<?php

namespace App\Traits;

trait HasStatusBadge
{
    /**
     * Get the status badge HTML for this model
     */
    public function getStatusBadgeAttribute()
    {
        $due = $this->due_amount ?? 0;
        $total = $this->getTotalAmountForStatus();

        if ($due < 0) {
            $statusClass = 'status-overpaid';
            $statusText = 'Overpaid';
        } elseif ($due == 0) {
            $statusClass = 'status-paid';
            $statusText = 'Fully Paid';
        } elseif ($due == $total) {
            $statusClass = 'status-pending';
            $statusText = 'Pending';
        } elseif ($due > 0) {
            $statusClass = 'status-partial';
            $statusText = 'Partially Paid';
        } else {
            $statusClass = 'status-unknown';
            $statusText = 'Unknown';
        }

        return '<span class="badge ' . $statusClass . '">' . $statusText . '</span>';
    }

    /**
     * Get the total amount to use for status calculation
     * Should be overridden by implementing classes
     */
    protected function getTotalAmountForStatus()
    {
        return $this->total_amount ?? 0;
    }
}