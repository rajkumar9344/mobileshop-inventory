<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class LedgerReportMail extends Mailable implements \Illuminate\Contracts\Queue\ShouldQueue
{
    use Queueable, SerializesModels;

    public $filePath;
    public $filename;
    public $recipient;
    public $bodyMessage;
    public $subjectLine;
    public $emailLogId;

    public function __construct($filePath, $filename, $subjectLine = 'Ledger Report', $bodyMessage = '', $recipient = null)
    {
        $this->filePath = $filePath;
        $this->filename = $filename;
        $this->bodyMessage = $bodyMessage;
        $this->subjectLine = $subjectLine;
        $this->recipient = $recipient;
    }

    public function build()
    {
        if (!empty($this->emailLogId) && method_exists($this, 'withSymfonyMessage')) {
            $id = $this->emailLogId;
            $this->withSymfonyMessage(function ($message) use ($id) {
                $message->getHeaders()->addTextHeader('X-Email-Log-Id', $id);
            });
        }

        $m = $this->subject($this->subjectLine)
            ->view('emails.report-mail', ['messageBody' => $this->bodyMessage, 'recipient' => $this->recipient]);

        if (!empty($this->filePath) && file_exists($this->filePath)) {
            $m->attach($this->filePath, ['as' => $this->filename, 'mime' => 'application/pdf']);
        }

        return $m;
    }
}
