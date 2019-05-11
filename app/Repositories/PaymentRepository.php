<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2019. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

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