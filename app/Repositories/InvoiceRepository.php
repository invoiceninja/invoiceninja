<?php

namespace App\Repositories;

use App\Helpers\Invoice\InvoiceCalc;
use App\Models\Invoice;
use Illuminate\Http\Request;

/**
 * InvoiceRepository
 */
class InvoiceRepository extends BaseRepository
{


    public function getClassName()
    {
        return Invoice::class;
    }
    
	public function save(Request $request, Invoice $invoice) : ?Invoice
	{
        $invoice->fill($request->input());
        $invoice->save();


        $invoice_calc = new InvoiceCalc($invoice, $invoice->settings);
        return $invoice;
	}

}