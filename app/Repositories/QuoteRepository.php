<?php

namespace App\Repositories;

use App\Helpers\Invoice\InvoiceCalc;
use App\Models\Quote;
use Illuminate\Http\Request;

/**
 * QuoteRepository
 */
class QuoteRepository extends BaseRepository
{


    public function getClassName()
    {
        return Quote::class;
    }
    
	public function save(Request $request, Quote $quote) : ?Quote
	{
        $quote->fill($request->input());
        
        $quote->save();


        $invoice_calc = new InvoiceCalc($quote, $quote->settings);

        $quote = $invoice_calc->build()->getInvoice();

        //fire events here that cascading from the saving of an invoice
        //ie. client balance update...
        
        return $quote;
	}

}