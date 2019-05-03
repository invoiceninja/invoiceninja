<?php

namespace App\Repositories;

use App\Models\Payment;
use Illuminate\Http\Request;

/**
 * PaymentRepository
 */
class PaymentRepository extends BaseRepository
{


    public function getClassName()
    {
        return Payment::class;
    }
    
	public function save(Request $request, Payment $payment) : ?Payment
	{
        $payment->fill($request->input());
        
        $payment->save();
        
        return $payment;
	}

}