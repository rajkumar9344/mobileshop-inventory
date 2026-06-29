<?php

namespace App\Services;

class PdfGenerator
{
    /**
     * TTF font variants to register for Arabic text rendering.
     * Files must exist in storage/fonts/.
     */
    private const ARABIC_FONTS = [
        ['family' => 'Arial', 'style' => 'normal', 'weight' => 'normal', 'file' => 'arial.ttf'],
        ['family' => 'Arial', 'style' => 'normal', 'weight' => 'bold',   'file' => 'arialbd.ttf'],
        ['family' => 'Arial', 'style' => 'italic', 'weight' => 'normal', 'file' => 'ariali.ttf'],
        ['family' => 'Arial', 'style' => 'italic', 'weight' => 'bold',   'file' => 'arialbi.ttf'],
    ];

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

        // Register Arial TTF fonts (which carry Arabic glyphs) so that
        // font-family:Arial in the invoice CSS renders Arabic characters
        // instead of "????". Registration is skipped on subsequent calls
        // once dompdf has cached the metrics in storage/fonts/.
        $this->registerArabicFonts($pdf->getDomPDF()->getFontMetrics());

        $pdf->setPaper($config['paper'] ?? 'a4', $config['orientation'] ?? 'portrait');

        return $pdf;
    }

    private function registerArabicFonts(\Dompdf\FontMetrics $metrics): void
    {
        $dir = storage_path('fonts') . DIRECTORY_SEPARATOR;

        foreach (self::ARABIC_FONTS as $v) {
            $path = $dir . $v['file'];
            if (file_exists($path)) {
                $metrics->registerFont(
                    ['family' => $v['family'], 'style' => $v['style'], 'weight' => $v['weight']],
                    $path
                );
            }
        }
    }
}
