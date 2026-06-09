<?php

namespace Modules\Quotation\Emails;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class QuotationMail extends Mailable implements \Illuminate\Contracts\Queue\ShouldQueue
{
    use Queueable, SerializesModels;

    public $quotation;
    public $customer;
    public $emailLogId;

    public function __construct($quotation, $customer)
    {
        $this->quotation = $quotation;
        $this->customer = $customer;
    }

    public function build()
    {
        if (!empty($this->emailLogId)) {
            if (method_exists($this, 'withSymfonyMessage')) {
                $id = $this->emailLogId;
                $this->withSymfonyMessage(function ($message) use ($id) {
                    $message->getHeaders()->addTextHeader('X-Email-Log-Id', $id);
                });
            }
        }

        $pdf = app(\App\Services\PdfGenerator::class)->make('quotation::print', [
            'quotation' => $this->quotation,
            'customer' => $this->customer,
            'is_pdf_request' => true,
        ], [
            'paper' => 'a4'
        ]);

        // sanitize reference by replacing any forward or backward slash characters
        // using simple str_replace avoids regex pitfalls observed in the logs
        $safeRef = str_replace(['/', '\\'], '-', $this->quotation->reference);
        $filename = 'quotation-' . $safeRef . '.pdf';
        $relativePath = 'exports/' . $filename;
        Storage::put($relativePath, $pdf->output());
        $fullPath = storage_path('app/' . $relativePath);

        $m = $this->subject('Quotation - ' . settings()->company_name)
            ->view('quotation::emails.quotation', [
                'settings' => settings(),
                'customer' => $this->customer,
                'quotation' => $this->quotation,
            ]);

        if (file_exists($fullPath)) {
            $m->attach($fullPath, ['as' => $filename, 'mime' => 'application/pdf']);
        }

        return $m;
    }
}
