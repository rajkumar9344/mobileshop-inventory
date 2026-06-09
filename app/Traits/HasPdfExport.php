<?php

namespace App\Traits;

/**
 * Shared PDF export logic for all DataTables.
 *
 * Usage: add `use \App\Traits\HasPdfExport;` inside the DataTable class body
 * and remove any existing pdf() / dompdfPdf() methods from that class.
 *
 * Features:
 *  - Memory limit removed (no crash on large datasets)
 *  - 5-minute timeout ceiling
 *  - Record guard: shows a friendly error if filtered record count exceeds the limit
 *    (default 1,000 — override with `protected int $pdfMaxRecords = 500;` in your DataTable)
 *    (the count uses the DataTable's own ajax() with length=0, so it automatically
 *     respects every active filter including search, date range, column filters, etc.)
 *  - try/catch shows errors.export-error view on any failure
 */
trait HasPdfExport
{
    public function pdf()
    {
        return $this->dompdfPdf();
    }

    protected function dompdfPdf()
    {
        try {
            ini_set('memory_limit', '-1');
            set_time_limit(300);

            if (! class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
                throw new \Exception('DomPDF not installed.');
            }

            // Count filtered records by calling the DataTable's own ajax() with
            // length=0 (fast — SQL LIMIT 0 skips data rows but still computes
            // recordsFiltered, which respects all active filters and search terms).
            $this->request()->merge(['start' => 0, 'length' => 0]);
            $countResponse = app()->call([$this, 'ajax']);
            $countData     = $countResponse->getData(true);
            $count         = $countData['recordsFiltered'] ?? $countData['recordsTotal'] ?? 0;
            $maxRecords    = property_exists($this, 'pdfMaxRecords') ? $this->pdfMaxRecords : 1000;

            if ($count > $maxRecords) {
                return response()->view('errors.export-error', [
                    'message' => 'There are ' . number_format($count) . ' records. PDF supports up to ' . number_format($maxRecords) . ' records. Please filter the data or use Excel / Print for large datasets.',
                ], 422);
            }

            $options     = config('datatables-buttons.dompdf.options', []);
            $orientation = config('datatables-buttons.dompdf.orientation', 'portrait');
            $view        = config('datatables-buttons.dompdf.view', 'datatables.pdf');

            $pdf = app('dompdf.wrapper');
            $pdf->setOptions($options);
            $pdf->setPaper('a4', $orientation);

            $data = $this->getDataForPrint();

            return $pdf->loadView($view, compact('data'))->download($this->getFilename() . '.pdf');
        } catch (\Throwable $e) {
            return response()->view('errors.export-error', [
                'message' => 'PDF export failed. Try filtering the data or use Excel / Print for large datasets.',
            ], 500);
        }
    }
}
