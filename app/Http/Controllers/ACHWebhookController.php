<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Controllers;

use App\Http\Requests\Payments\PaymentWebhookRequest;

class ACHWebhookController extends Controller
{
    public function __invoke(PaymentWebhookRequest $request)
    {

        if($request->event != 'charge.updated'){
            \Log::info('Invalid event.');
            return response()->json([], 404);
        }
        $payment = Payment::query()
                ->where('transaction_reference', $request->reference_number)
                ->first();
        if(!$payment){
            \Log::info('Charge ID is not found.');
            return response()->json([], 404);
        }

        $status = strtolower($request->status);
        
        $payment->status = ($status == 'paid') ? Payment::STATUS_COMPLETED : Payment::STATUS_FAILED;
        $payment->save();

        $payment_hash = PaymentHash::query()
                ->where('payment_id', $payment->id)
                ->first();
        if($payment_hash && isset($payment_hash->data->status) ){
            
            if($payment_hash->data->status == 'paid_unsettled'){
                $tmpdata = $payment_hash->data;
                $tmpdata->status = $status;
                $tmpdata->reason = $request->reason;
                $payment_hash->data = $tmpdata;
                $payment_hash->save();
            }
        }

        return response()->json([], 200);
    }
}
