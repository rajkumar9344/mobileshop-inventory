<?php

namespace Modules\Purchase\Emails;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Storage;

class PurchaseMail extends Mailable implements \Illuminate\Contracts\Queue\ShouldQueue
{
    use Queueable, SerializesModels;

    public $purchase;
    public $supplier;
    public $emailLogId;

    public function __construct($purchase, $supplier)
    {
        $this->purchase = $purchase;
        $this->supplier = $supplier;
    }

    public function build()
    {
        // If controller provided an email log id, attach it to the outgoing message headers
        if (!empty($this->emailLogId)) {
            if (method_exists($this, 'withSymfonyMessage')) {
                $id = $this->emailLogId;
                $this->withSymfonyMessage(function ($message) use ($id) {
                    $message->getHeaders()->addTextHeader('X-Email-Log-Id', $id);
                });
            }
        }
        $pdf = app(\App\Services\PdfGenerator::class)->make('purchase::print', [
            'purchase' => $this->purchase,
            'supplier' => $this->supplier,
            'is_pdf_request' => true,
        ], [
            'paper' => 'a4'
        ]);

        $safeRef = str_replace(["\\", '/'], '-', $this->purchase->reference);
        $filename = 'purchase-' . $safeRef . '.pdf';
        $relativePath = 'exports/' . $filename;
        Storage::put($relativePath, $pdf->output());
        $fullPath = storage_path('app/' . $relativePath);

        $m = $this->subject('Purchase Bill - ' . settings()->company_name)
            ->view('purchase::emails.purchase', [
                'settings' => settings(),
                'supplier' => $this->supplier,
                'purchase' => $this->purchase,
            ]);

        if (file_exists($fullPath)) {
            $m->attach($fullPath, ['as' => $filename, 'mime' => 'application/pdf']);
        }

        return $m;
    }
}
