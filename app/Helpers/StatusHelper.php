<?php

if (!function_exists('getStatusBadge')) {
    /**
     * Get status badge HTML for sales/purchases
     *
     * @param object $model The Sale or Purchase model instance
     * @return string HTML badge
     */
    function getStatusBadge($model)
    {
        return $model->status_badge;
    }
}