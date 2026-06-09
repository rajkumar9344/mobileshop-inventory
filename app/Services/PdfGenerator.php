<?php

namespace App\Services;

class PdfGenerator
{
    /**
     * Create a DomPDF wrapper instance with common options applied.
     *
     * @param string $view
     * @param array $data
     * @param array $config Optional config: ['paper' => 'a4', 'orientation' => 'portrait']
     * @return \Barryvdh\DomPDF\PDF
     */
    public function make(string $view, array $data = [], array $config = [])
    {
        ini_set('memory_limit', '-1');
        set_time_limit(300);

        $pdf = \Pdf::loadView($view, $data);

        // Paper size & orientation
        $paper       = $config['paper'] ?? 'a4';
        $orientation = $config['orientation'] ?? 'portrait';
        $pdf->setPaper($paper, $orientation);

        return $pdf;
    }
}
