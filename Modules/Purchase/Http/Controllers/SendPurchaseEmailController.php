<?php

namespace Modules\Purchase\Http\Controllers;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Modules\Purchase\Emails\PurchaseMail;
use Modules\Purchase\Entities\Purchase;
use Modules\People\Entities\Supplier;
use App\Models\EmailLog;

class SendPurchaseEmailController extends Controller
{
    public function __invoke(Purchase $purchase) {
        // Check if supplier exists and has email
        $supplier = Supplier::find($purchase->supplier_id);
        if (!$supplier || !$supplier->supplier_email) {
            toast('Supplier does not have an email address!', 'error');
            return back();
        }

        // Validate email format
        $validator = Validator::make(['email' => $supplier->supplier_email], [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            toast('Invalid supplier email address!', 'error');
            return back();
        }

        try {
            // Create initial email log (queued)
            $log = EmailLog::create([
                'emailable_type' => Purchase::class,
                'emailable_id' => $purchase->id,
                'recipient' => $supplier->supplier_email,
                'subject' => 'Purchase Bill - ' . settings()->company_name,
                'status' => 'queued',
            ]);

            $mailable = new PurchaseMail($purchase, $supplier);
            $mailable->emailLogId = $log->id;

            Mail::to($supplier->supplier_email)->queue($mailable);

            toast('Purchase bill queued for sending to "' . $supplier->supplier_email . '"!', 'success');

        } catch (\Exception $exception) {
            Log::error($exception);
            toast('Something went wrong while sending email!', 'error');
        }

        return back();
    }
}
