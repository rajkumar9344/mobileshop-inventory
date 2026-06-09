<?php

namespace Modules\Quotation\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Modules\Quotation\Entities\Quotation;
use Modules\Quotation\Emails\QuotationMail;
use App\Models\EmailLog;

class SendQuotationEmailController extends Controller
{
    public function __invoke(Quotation $quotation)
    {
        // determine the recipient address; for "new" quotes the customer relation
        // will be null so fall back to the contact_email field stored on the quote
        $emailAddress = $quotation->customer->customer_email ?? $quotation->contact_email;

        if (!$emailAddress) {
            toast('Quotation does not have an email address!', 'error');
            return back();
        }

        $validator = Validator::make(['email' => $emailAddress], [
            'email' => 'required|email',
        ]);

        // load customer record only if there is an actual customer_id; otherwise leave it null
        $customer = $quotation->customer_id ? ($quotation->customer ?? \Modules\People\Entities\Customer::find($quotation->customer_id)) : null;

        if ($validator->fails()) {
            toast('Invalid customer email address!', 'error');
            return back();
        }

        try {
            $log = EmailLog::create([
                'emailable_type' => Quotation::class,
                'emailable_id' => $quotation->id,
                'recipient' => $emailAddress,
                'subject' => 'Quotation - ' . settings()->company_name,
                'status' => 'queued',
            ]);

            $mailable = new QuotationMail($quotation, $customer);
            $mailable->emailLogId = $log->id;

            Mail::to($emailAddress)->queue($mailable);

            toast('Quotation queued for sending to "' . $emailAddress . '"!', 'success');
        } catch (\Exception $e) {
            Log::error($e);
            // If we created the log but an exception occurred before queuing, mark it failed
            if (isset($log) && $log instanceof EmailLog) {
                try {
                    $log->update([
                        'status' => 'failed',
                        'error' => substr($e->getMessage(), 0, 1000),
                    ]);
                } catch (\Throwable $ex) {
                    Log::error('Failed to update EmailLog: ' . $ex->getMessage());
                }
            }
            toast('Something went wrong while sending email!', 'error');
        }

        return back();
    }
}
