<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\Product\Entities\Product;
use Modules\Product\Entities\Category;
use Modules\Sale\Entities\Sale;
use Modules\Purchase\Entities\Purchase;
use Modules\People\Entities\Customer;
use Modules\People\Entities\Supplier;
use App\Exports\ReorderReportExport;
use App\Exports\SalesOutstandingExport;
use App\Exports\PurchaseOutstandingExport;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;
use PDF;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Modules\SalesReceipt\Entities\SalesReceiptLine;
use App\Exports\CustomersPaymentExport;
use Maatwebsite\Excel\Facades\Excel as ExcelFacade;
use Modules\SalesReceipt\Entities\SalesReceipt;
use App\Exports\LedgerReportExport;
use App\Exports\SupplierLedgerReportExport;
use App\Services\LedgerService;
use App\Exports\DailyOperationsReportExport;
use App\Exports\MonthwiseSummaryExport;
use App\Services\DailyOperationsService;
use Modules\Expense\Entities\Expense;

class ReportsController extends Controller
{
    /**
     * Maximum records allowed for PDF export. Beyond this, suggest Excel.
     */
    private const PDF_MAX_RECORDS = 1000;

    /**
     * Return a user-friendly error response for PDF export failures.
     */
    private function pdfExportErrorResponse(string $message, string $suggestion = ''): \Illuminate\Http\Response
    {
        return response()->view('errors.export-error', [
            'title' => 'PDF Export Error',
            'message' => $message,
            'suggestion' => $suggestion ?: 'Please narrow your filters or use the Excel export for large datasets.',
        ], 422);
    }

    public function reorderPdf(Request $request)
    {
        try {
            $filters = $request->only([
                'category_id',
                'supplier_id',
                'compatibility',
                'generated_date_from',
                'generated_date_to',
                'search'
            ]);

            // Include active purchases to match the web/listing view which shows
            // products even when there's an active purchase in progress.
            $filters['include_active_purchases'] = true;
            $query = app(\App\Services\ReportQueryService::class)->buildReorderQuery($filters);
            $count = $query->count();

            if ($count > self::PDF_MAX_RECORDS) {
                return $this->pdfExportErrorResponse(
                    'There are ' . number_format($count) . ' records. PDF export supports a maximum of ' . number_format(self::PDF_MAX_RECORDS) . ' records.'
                );
            }

            $products = $query->get();

            $pdf = app(\App\Services\PdfGenerator::class)->make('exports.reorder-report-pdf', [
                'products' => $products,
                'filters'  => $filters,
            ]);

            $filename = 'purchase-order-report-' . date('Y-m-d') . '.pdf';
            return $pdf->download($filename);
        } catch (\Throwable $e) {
            Log::error('Reorder PDF export failed: ' . $e->getMessage());
            return $this->pdfExportErrorResponse('An error occurred while generating the PDF.');
        }
    }

    public function reorderExcel(Request $request)
    {
        $filters = $request->only([
            'category_id',
            'supplier_id',
            'compatibility',
            'generated_date_from',
            'generated_date_to',
            'search'
        ]);

        $filename = 'purchase-order-report-' . date('Y-m-d') . '.xlsx';
        return Excel::download(new ReorderReportExport($filters), $filename);
    }

    /**
     * Sales Outstanding Report - PDF Export
     */
    public function salesOutstandingPdf(Request $request)
    {
        try {
            $filters = $request->only(['customer_id', 'reference', 'aging_range']);

            // Get customer name for filter display
            if (!empty($filters['customer_id'])) {
                $customer = Customer::find($filters['customer_id']);
                $filters['customer_name'] = $customer ? $customer->customer_name : '';
            }

            $query = $this->getSalesOutstandingQuery($filters);
            $count = $query->count();

            if ($count > self::PDF_MAX_RECORDS) {
                return $this->pdfExportErrorResponse(
                    'There are ' . number_format($count) . ' outstanding records. PDF export supports a maximum of ' . number_format(self::PDF_MAX_RECORDS) . ' records.'
                );
            }

            $sales = $query->get();

            $pdf = app(\App\Services\PdfGenerator::class)->make('exports.sales-outstanding-pdf', [
                'sales' => $sales,
                'filters' => $filters,
            ]);

            $filename = 'sales-outstanding-report-' . date('Y-m-d') . '.pdf';
            return $pdf->download($filename);
        } catch (\Throwable $e) {
            Log::error('Sales outstanding PDF export failed: ' . $e->getMessage());
            return $this->pdfExportErrorResponse('An error occurred while generating the PDF.');
        }
    }

    /**
     * Sales Outstanding Report - Excel Export
     */
    public function salesOutstandingExcel(Request $request)
    {
        $filters = $request->only(['customer_id', 'reference', 'aging_range']);

        $filename = 'sales-outstanding-report-' . date('Y-m-d') . '.xlsx';
        return Excel::download(new SalesOutstandingExport($filters), $filename);
    }

    /**
     * Purchase Outstanding Report - PDF Export
     */
    public function purchaseOutstandingPdf(Request $request)
    {
        try {
            $filters = $request->only(['supplier_id', 'reference', 'aging_range']);

            if (!empty($filters['supplier_id'])) {
                $supplier = Supplier::find($filters['supplier_id']);
                $filters['supplier_name'] = $supplier ? $supplier->supplier_name : '';
            }

            $query = $this->getPurchaseOutstandingQuery($filters);
            $count = $query->count();

            if ($count > self::PDF_MAX_RECORDS) {
                return $this->pdfExportErrorResponse(
                    'There are ' . number_format($count) . ' outstanding records. PDF export supports a maximum of ' . number_format(self::PDF_MAX_RECORDS) . ' records.'
                );
            }

            $purchases = $query->get();

            $pdf = app(\App\Services\PdfGenerator::class)->make('exports.purchase-outstanding-pdf', [
                'purchases' => $purchases,
                'filters' => $filters,
            ]);

            $filename = 'purchase-outstanding-report-' . date('Y-m-d') . '.pdf';
            return $pdf->download($filename);
        } catch (\Throwable $e) {
            Log::error('Purchase outstanding PDF export failed: ' . $e->getMessage());
            return $this->pdfExportErrorResponse('An error occurred while generating the PDF.');
        }
    }

    /**
     * Purchase Outstanding Report - Excel Export
     */
    public function purchaseOutstandingExcel(Request $request)
    {
        $filters = $request->only(['supplier_id', 'reference', 'aging_range']);

        $filename = 'purchase-outstanding-report-' . date('Y-m-d') . '.xlsx';
        return Excel::download(new PurchaseOutstandingExport($filters), $filename);
    }

    /**
     * Build the sales outstanding query with filters
     */
    protected function getSalesOutstandingQuery($filters)
    {
        return app(\App\Services\ReportQueryService::class)->buildSalesOutstandingQuery($filters);
    }

    /**
     * Build the purchase outstanding query with filters
     */
    protected function getPurchaseOutstandingQuery($filters)
    {
        return app(\App\Services\ReportQueryService::class)->buildPurchaseOutstandingQuery($filters);
    }

    /**
     * Customers Payment Report - PDF Export
     */
    public function customersPaymentPdf(Request $request)
    {
        try {
            ini_set('memory_limit', '1G');

            $filters = $request->only(['customer_id', 'reference', 'start_date', 'end_date', 'payment_mode']);

            if (!empty($filters['customer_id'])) {
                $customer = Customer::find($filters['customer_id']);
                $filters['customer_name'] = $customer ? $customer->customer_name : '';
            }

            $payments = app(\App\Services\ReportQueryService::class)->getCustomersPaymentCollection($filters);
            $count = $payments->count();

            if ($count > self::PDF_MAX_RECORDS) {
                return $this->pdfExportErrorResponse(
                    'There are ' . number_format($count) . ' payment records. PDF export supports a maximum of ' . number_format(self::PDF_MAX_RECORDS) . ' records.'
                );
            }

            $pdf = app(\App\Services\PdfGenerator::class)->make('exports.customers-payment-pdf', compact('payments', 'filters'));

            $filename = 'customers-payment-report-' . date('Y-m-d') . '.pdf';
            return $pdf->download($filename);
        } catch (\Throwable $e) {
            Log::error('Customers payment PDF export failed: ' . $e->getMessage());
            return $this->pdfExportErrorResponse('An error occurred while generating the PDF.');
        }
    }

    /**
     * Customers Payment Report - Excel Export
     */
    public function customersPaymentExcel(Request $request)
    {
        $filters = $request->only(['customer_id', 'reference', 'start_date', 'end_date', 'payment_mode']);

        $filename = 'customers-payment-report-' . date('Y-m-d') . '.xlsx';
        return ExcelFacade::download(new CustomersPaymentExport($filters), $filename);
    }

    /**
     * Customers Payment Report - Print View (all records, no pagination)
     */
    public function customersPaymentPrint(Request $request)
    {
        $filters = $request->only(['customer_id', 'reference', 'start_date', 'end_date', 'payment_mode']);

        if (!empty($filters['customer_id'])) {
            $customer = Customer::find($filters['customer_id']);
            $filters['customer_name'] = $customer ? $customer->customer_name : '';
        }

        $payments = app(\App\Services\ReportQueryService::class)
            ->getCustomersPaymentCollection($filters);

        return view('reports.customers-payment-print', compact('payments', 'filters'));
    }

    /**
     * Sales Outstanding Report - Print (all records, browser print)
     */
    public function salesOutstandingPrint(Request $request)
    {
        $filters = $request->only(['customer_id', 'reference', 'aging_range']);

        if (!empty($filters['customer_id'])) {
            $customer = Customer::find($filters['customer_id']);
            $filters['customer_name'] = $customer ? $customer->customer_name : '';
        }

        $sales = $this->getSalesOutstandingQuery($filters)->get();

        return view('reports.sales-outstanding-print', compact('sales', 'filters'));
    }

    /**
     * Purchase Outstanding Report - Print (all records, browser print)
     */
    public function purchaseOutstandingPrint(Request $request)
    {
        $filters = $request->only(['supplier_id', 'reference', 'aging_range']);

        if (!empty($filters['supplier_id'])) {
            $supplier = Supplier::find($filters['supplier_id']);
            $filters['supplier_name'] = $supplier ? $supplier->supplier_name : '';
        }

        $purchases = $this->getPurchaseOutstandingQuery($filters)->get();

        return view('reports.purchase-outstanding-print', compact('purchases', 'filters'));
    }

    /**
     * GSTR Report - Print (all records, browser print)
     */
    public function gstrPrint(Request $request)
    {
        $filters = $request->only(['hsn', 'product', 'rate', 'hide_without_hsn', 'start_date', 'end_date']);

        $rows = app(\App\Services\ReportQueryService::class)->buildGstrQuery($filters)->get();

        return view('reports.gstr-print', compact('rows', 'filters'));
    }

    /**
     * Reorder (Purchase Order) Report - Print (all records, browser print)
     */
    public function reorderPrint(Request $request)
    {
        $filters = $request->only(['category_id', 'supplier_id', 'compatibility', 'generated_date_from', 'generated_date_to', 'search']);

        if (!empty($filters['category_id'])) {
            $cat = Category::find((int) $filters['category_id']);
            $filters['category_name'] = $cat ? $cat->category_name : '';
        }

        if (!empty($filters['supplier_id'])) {
            $sup = Supplier::find((int) $filters['supplier_id']);
            $filters['supplier_name'] = $sup ? $sup->supplier_name : '';
        }

        $products = app(\App\Services\ReportQueryService::class)->buildReorderQuery($filters)->get();

        return view('reports.reorder-print', compact('products', 'filters'));
    }

    /**
     * Ledger Report - Print (all records, browser print)
     */
    public function ledgerPrint(Request $request)
    {
        $filters = $request->only(['customer_id', 'start_date', 'end_date', 'financial_year']);

        if (empty($filters['start_date']) || empty($filters['end_date'])) {
            $dates = LedgerService::getFinancialYearDates($filters['financial_year'] ?? null);
            $filters['start_date'] = $filters['start_date'] ?? $dates['start_date'];
            $filters['end_date']   = $filters['end_date']   ?? $dates['end_date'];
        }

        if (!empty($filters['customer_id'])) {
            $customer = Customer::find((int) $filters['customer_id']);
            $filters['customer_name'] = $customer ? $customer->customer_name : '';
        }

        $data = LedgerService::buildLedgerData($filters);

        return view('reports.ledger-print', compact('data', 'filters'));
    }

    /**
     * Supplier Ledger Report - Print (all records, browser print)
     */
    public function supplierLedgerPrint(Request $request)
    {
        $filters = $request->only(['supplier_id', 'start_date', 'end_date', 'financial_year']);

        if (empty($filters['start_date']) || empty($filters['end_date'])) {
            $dates = LedgerService::getFinancialYearDates($filters['financial_year'] ?? null);
            $filters['start_date'] = $filters['start_date'] ?? $dates['start_date'];
            $filters['end_date']   = $filters['end_date']   ?? $dates['end_date'];
        }

        if (!empty($filters['supplier_id'])) {
            $supplier = Supplier::find((int) $filters['supplier_id']);
            $filters['supplier_name'] = $supplier ? $supplier->supplier_name : '';
        }

        $data = LedgerService::buildSupplierLedgerData($filters);

        return view('reports.supplier-ledger-print', compact('data', 'filters'));
    }

    /**
     * Daily Operations Report - Print (browser print)
     */
    public function dailyOperationsPrint(Request $request)
    {
        $date = $request->get('date', today()->format('Y-m-d'));

        // Sanitize: ensure it is a valid date string before passing to the service
        try {
            $date = Carbon::parse($date)->format('Y-m-d');
        } catch (\Exception $e) {
            $date = today()->format('Y-m-d');
        }

        $data = DailyOperationsService::getReportData($date);

        return view('reports.daily-operations-print', compact('data', 'date'));
    }

    /**
     * GSTR Report - PDF Export
     */
    public function gstrPdf(Request $request)
    {
        try {
            $filters = $request->only(['hsn', 'product', 'rate', 'hide_without_hsn', 'start_date', 'end_date']);

            $query = app(\App\Services\ReportQueryService::class)->buildGstrQuery($filters);
            $count = $query->count();

            if ($count > self::PDF_MAX_RECORDS) {
                return $this->pdfExportErrorResponse(
                    'There are ' . number_format($count) . ' GSTR records. PDF export supports a maximum of ' . number_format(self::PDF_MAX_RECORDS) . ' records.'
                );
            }

            $rows = $query->get();

            $pdf = app(\App\Services\PdfGenerator::class)->make('exports.gstr-report-pdf', [
                'rows' => $rows,
                'filters' => $filters,
            ]);

            $filename = 'gstr-report-' . date('Y-m-d') . '.pdf';
            return $pdf->download($filename);
        } catch (\Throwable $e) {
            Log::error('GSTR PDF export failed: ' . $e->getMessage());
            return $this->pdfExportErrorResponse('An error occurred while generating the GSTR PDF.');
        }
    }

    /**
     * GSTR Report - Excel Export
     */
    public function gstrExcel(Request $request)
    {
        $filters = $request->only(['hsn', 'product', 'rate', 'hide_without_hsn', 'start_date', 'end_date']);

        $filename = 'gstr-report-' . date('Y-m-d') . '.xlsx';
        return ExcelFacade::download(new \App\Exports\GstrReportExport($filters), $filename);
    }

    /**
     * Ledger Report - PDF Export
     */
    public function ledgerPdf(Request $request)
    {
        try {
            $filters = $request->only(['customer_id', 'start_date', 'end_date', 'financial_year']);

            // Default to current FY if dates not provided
            if (empty($filters['start_date']) || empty($filters['end_date'])) {
                $dates = LedgerService::getFinancialYearDates($filters['financial_year'] ?? null);
                $filters['start_date'] = $filters['start_date'] ?? $dates['start_date'];
                $filters['end_date'] = $filters['end_date'] ?? $dates['end_date'];
            }

            $data = LedgerService::buildLedgerData($filters);

            $pdf = app(\App\Services\PdfGenerator::class)->make('exports.ledger-report-pdf', [
                'data' => $data,
                'filters' => $filters,
            ]);

            $filename = 'ledger-report-' . date('Y-m-d') . '.pdf';
            return $pdf->download($filename);
        } catch (\Throwable $e) {
            Log::error('Ledger PDF export failed: ' . $e->getMessage());
            return $this->pdfExportErrorResponse('An error occurred while generating the Ledger PDF.');
        }
    }

    /**
     * Ledger Report - Send by Email
     */
    public function ledgerSendEmail(Request $request)
    {
        $data = $request->validate([
            'to' => 'nullable|email',
            'customer_id' => 'nullable|integer',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);

        $filters = [
            'customer_id' => $data['customer_id'] ?? null,
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
        ];

        // Default dates if missing
        if (empty($filters['start_date']) || empty($filters['end_date'])) {
            $dates = \App\Services\LedgerService::getFinancialYearDates();
            $filters['start_date'] = $filters['start_date'] ?? $dates['start_date'];
            $filters['end_date'] = $filters['end_date'] ?? $dates['end_date'];
        }

        try {
            $ledgerData = \App\Services\LedgerService::buildLedgerData($filters);

            // Resolve recipient: prefer customer's email when customer_id provided
            $recipient = $data['to'] ?? null;
            if (!empty($filters['customer_id'])) {
                $cust = Customer::find($filters['customer_id']);
                if ($cust && !empty($cust->customer_email)) {
                    $recipient = $cust->customer_email;
                }
            }

            if (empty($recipient)) {
                toast('No recipient email found for this customer', 'error');
                return back();
            }

            // Build PDF using PdfGenerator
            $pdf = app(\App\Services\PdfGenerator::class)->make('exports.ledger-report-pdf', [
                'data' => $ledgerData,
                'filters' => $filters,
            ]);

            $pdfContent = $pdf->output();
            $filename = 'ledger-report-' . date('Y-m-d') . '.pdf';

            // save PDF to storage using Storage facade (supports drivers)
            $relativePath = 'exports/' . $filename;
            Storage::put($relativePath, $pdfContent);
            $fullPath = storage_path('app/' . $relativePath);

            // create EmailLog
            $emailLog = \App\Models\EmailLog::create([
                'emailable_type' => 'reports.ledger',
                'emailable_id' => 0,
                'recipient' => $recipient,
                'subject' => 'Ledger Report - ' . settings()->company_name,
                'status' => 'queued',
            ]);

            // prepare mailable with file path (avoid serializing binary)
            $mailable = new \App\Mail\LedgerReportMail($fullPath, $filename, 'Ledger Report', 'Please find the ledger report attached.', $recipient);
            $mailable->emailLogId = $emailLog->id;

            Mail::to($recipient)->queue($mailable);

            toast('Ledger report queued for sending to ' . $recipient, 'success');
        } catch (\Exception $e) {
            Log::error('Failed to send ledger email: ' . $e->getMessage());
            toast('Failed to queue ledger email', 'error');
        }

        return back();
    }

    /**
     * Ledger Report - Excel Export
     */
    public function ledgerExcel(Request $request)
    {
        $filters = $request->only(['customer_id', 'start_date', 'end_date', 'financial_year']);

        $filename = 'ledger-report-' . date('Y-m-d') . '.xlsx';
        return Excel::download(new LedgerReportExport($filters), $filename);
    }

    /**
     * Supplier Ledger Report - PDF Export
     */
    public function supplierLedgerPdf(Request $request)
    {
        try {
            $filters = $request->only(['supplier_id', 'start_date', 'end_date', 'financial_year']);

            // Default to current FY if dates not provided
            if (empty($filters['start_date']) || empty($filters['end_date'])) {
                $dates = LedgerService::getFinancialYearDates($filters['financial_year'] ?? null);
                $filters['start_date'] = $filters['start_date'] ?? $dates['start_date'];
                $filters['end_date'] = $filters['end_date'] ?? $dates['end_date'];
            }

            $data = LedgerService::buildSupplierLedgerData($filters);

            $pdf = app(\App\Services\PdfGenerator::class)->make('exports.supplier-ledger-report-pdf', [
                'data' => $data,
                'filters' => $filters,
            ]);

            $filename = 'supplier-ledger-report-' . date('Y-m-d') . '.pdf';
            return $pdf->download($filename);
        } catch (\Throwable $e) {
            Log::error('Supplier ledger PDF export failed: ' . $e->getMessage());
            return $this->pdfExportErrorResponse('An error occurred while generating the Supplier Ledger PDF.');
        }
    }

    /**
     * Supplier Ledger Report - Send by Email
     */
    public function supplierLedgerSendEmail(Request $request)
    {
        $data = $request->validate([
            'to' => 'nullable|email',
            'supplier_id' => 'nullable|integer',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);

        $filters = [
            'supplier_id' => $data['supplier_id'] ?? null,
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
        ];

        if (empty($filters['start_date']) || empty($filters['end_date'])) {
            $dates = \App\Services\LedgerService::getFinancialYearDates();
            $filters['start_date'] = $filters['start_date'] ?? $dates['start_date'];
            $filters['end_date'] = $filters['end_date'] ?? $dates['end_date'];
        }

        try {
            $ledgerData = \App\Services\LedgerService::buildSupplierLedgerData($filters);

            // Build PDF using PdfGenerator
            $pdf = app(\App\Services\PdfGenerator::class)->make('exports.supplier-ledger-report-pdf', [
                'data' => $ledgerData,
                'filters' => $filters,
            ]);

            $pdfContent = $pdf->output();
            $filename = 'supplier-ledger-report-' . date('Y-m-d') . '.pdf';

            $relativePath = 'exports/' . $filename;
            Storage::put($relativePath, $pdfContent);
            $fullPath = storage_path('app/' . $relativePath);

            // Determine recipient: prefer supplier's email when supplier_id provided
            $recipient = $data['to'] ?? null;
            if (!empty($filters['supplier_id'])) {
                $sup = Supplier::find($filters['supplier_id']);
                if ($sup && !empty($sup->supplier_email)) {
                    $recipient = $sup->supplier_email;
                }
            }

            if (empty($recipient)) {
                toast('No recipient email found for this supplier', 'error');
                return back();
            }

            // create EmailLog
            $emailLog = \App\Models\EmailLog::create([
                'emailable_type' => 'reports.supplier_ledger',
                'emailable_id' => 0,
                'recipient' => $recipient,
                'subject' => 'Supplier Ledger Report - ' . settings()->company_name,
                'status' => 'queued',
            ]);

            // prepare mailable with file path
            $mailable = new \App\Mail\LedgerReportMail($fullPath, $filename, 'Supplier Ledger Report', 'Please find the supplier ledger report attached.', $recipient);
            $mailable->emailLogId = $emailLog->id;

            Mail::to($recipient)->queue($mailable);

            toast('Supplier ledger report queued for sending to ' . $recipient, 'success');
        } catch (\Exception $e) {
            Log::error('Failed to send supplier ledger email: ' . $e->getMessage());
            toast('Failed to queue supplier ledger email', 'error');
        }

        return back();
    }

    /**
     * Supplier Ledger Report - Excel Export
     */
    public function supplierLedgerExcel(Request $request)
    {
        $filters = $request->only(['supplier_id', 'start_date', 'end_date', 'financial_year']);

        $filename = 'supplier-ledger-report-' . date('Y-m-d') . '.xlsx';
        return Excel::download(new SupplierLedgerReportExport($filters), $filename);
    }

    // Ledger data building moved to App\Services\LedgerService

    // Query building moved to App\Services\ReportQueryService

    /**
     * Daily Operations Report - PDF Export
     */
    public function dailyOperationsPdf(Request $request)
    {
        try {
            $date = $request->get('date', today()->format('Y-m-d'));
            $data = DailyOperationsService::getReportData($date);

            $pdf = app(\App\Services\PdfGenerator::class)->make('exports.daily-operations-pdf', [
                'data' => $data,
                'date' => $date,
            ]);

            $filename = 'daily-operations-report-' . $date . '.pdf';
            return $pdf->download($filename);
        } catch (\Throwable $e) {
            Log::error('Daily operations PDF export failed: ' . $e->getMessage());
            return $this->pdfExportErrorResponse('An error occurred while generating the Daily Operations PDF.');
        }
    }

    /**
     * Daily Operations Report - Excel Export
     */
    public function dailyOperationsExcel(Request $request)
    {
        $date = $request->get('date', today()->format('Y-m-d'));

        $filename = 'daily-operations-report-' . $date . '.xlsx';
        return ExcelFacade::download(new DailyOperationsReportExport($date), $filename);
    }

    /**
     * Monthwise Summary Report - PDF Export
     */
    public function dailyOperationsMonthwisePdf(Request $request)
    {
        try {
            $year = $request->get('year', date('Y'));
            $month = $request->get('month', date('m'));
            $data = DailyOperationsService::getMonthwiseSummary($year, $month);

            $pdf = app(\App\Services\PdfGenerator::class)->make('exports.monthwise-summary-pdf', [
                'data' => $data,
                'year' => $year,
                'month' => $month,
            ], ['orientation' => 'landscape']);

            $filename = 'monthwise-summary-' . $year . '-' . $month . '.pdf';
            return $pdf->download($filename);
        } catch (\Throwable $e) {
            Log::error('Monthwise PDF export failed: ' . $e->getMessage());
            return $this->pdfExportErrorResponse('An error occurred while generating the Monthwise Summary PDF.');
        }
    }

    /**
     * Monthwise Summary Report - Excel Export
     */
    public function dailyOperationsMonthwiseExcel(Request $request)
    {
        $year = $request->get('year', date('Y'));
        $month = $request->get('month', date('m'));

        $filename = 'monthwise-summary-' . $year . '-' . $month . '.xlsx';
        return ExcelFacade::download(new MonthwiseSummaryExport($year, $month), $filename);
    }
}
