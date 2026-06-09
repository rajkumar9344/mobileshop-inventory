<?php

namespace Modules\Sale\Emails;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Storage;

class SaleMail extends Mailable implements \Illuminate\Contracts\Queue\ShouldQueue
{
    use Queueable, SerializesModels;

    public $sale;
    public $customer;
    public $emailLogId;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($sale, $customer)
    {
        $this->sale = $sale;
        $this->customer = $customer;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
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
        $pdf = app(\App\Services\PdfGenerator::class)->make('sale::print', [
            'sale' => $this->sale,
            'customer' => $this->customer,
            'is_pdf_request' => true,
        ], [
            'paper' => 'a4'
        ]);

        $safeRef = preg_replace('/[\/\\\\]+/', '-', $this->sale->reference);
        $filename = 'sale-' . $safeRef . '.pdf';
        $relativePath = 'exports/' . $filename;
        // persist PDF to storage so it is not serialized in the queued job
        Storage::put($relativePath, $pdf->output());
        $fullPath = storage_path('app/' . $relativePath);

        $m = $this->subject('Sale Invoice - ' . settings()->company_name)
            ->view('sale::emails.sale', [
                'settings' => settings(),
                'customer' => $this->customer,
                'sale' => $this->sale,
            ]);

        if (file_exists($fullPath)) {
            $m->attach($fullPath, ['as' => $filename, 'mime' => 'application/pdf']);
        }

        return $m;
    }
}