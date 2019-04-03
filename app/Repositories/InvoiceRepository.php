<?php

namespace App\Repositories;

use App\Models\Invoice;
use Illuminate\Http\Request;

/**
 * 
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

        return $invoice;
	}

}