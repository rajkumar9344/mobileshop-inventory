<?php

namespace Modules\Sale\Http\Controllers;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Modules\Sale\Emails\SaleMail;
use Modules\Sale\Entities\Sale;
use App\Models\EmailLog;

class SendSaleEmailController extends Controller
{
    public function __invoke(Sale $sale) {
        abort_if(\Gate::denies('send_sale_mails'), 403);

        // Check if customer exists and has email
        if (!$sale->customer || !$sale->customer->customer_email) {
            toast('Customer does not have an email address!', 'error');
            return back();
        }

        // Validate email format
        $validator = Validator::make(['email' => $sale->customer->customer_email], [
            'email' => 'required|email:rfc,strict',
        ]);

        if ($validator->fails()) {
            toast('Invalid customer email address!', 'error');
            return back();
        }

        try {
            // Create initial email log (queued)
            $log = EmailLog::create([
                'emailable_type' => Sale::class,
                'emailable_id' => $sale->id,
                'recipient' => $sale->customer->customer_email,
                'subject' => 'Sale Invoice - ' . settings()->company_name,
                'status' => 'queued',
            ]);

            $mailable = new SaleMail($sale, $sale->customer);
            // Set the log id for the mailable; the header will be attached during build()
            $mailable->emailLogId = $log->id;

            Mail::to($sale->customer->customer_email)->queue($mailable);

            toast('Invoice queued for sending to "' . $sale->customer->customer_email . '"!', 'success');

        } catch (\Exception $exception) {
            Log::error($exception);
            toast('Something went wrong while sending email!', 'error');
        }

        return back();
    }
}