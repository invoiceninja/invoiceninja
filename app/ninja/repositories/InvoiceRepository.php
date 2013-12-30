<?php namespace ninja\repositories;

use Invoice;
use InvoiceItem;
use Product;
use Utils;
use TaxRate;

class InvoiceRepository
{
	public function getInvoices($accountId, $clientPublicId = false, $filter = false)
	{
    	$query = \DB::table('invoices')
    				->join('clients', 'clients.id', '=','invoices.client_id')
					->join('invoice_statuses', 'invoice_statuses.id', '=', 'invoices.invoice_status_id')
					->join('contacts', 'contacts.client_id', '=', 'clients.id')
					->where('invoices.account_id', '=', $accountId)
    				->where('invoices.deleted_at', '=', null)
    				->where('clients.deleted_at', '=', null)
    				->where('invoices.is_recurring', '=', false)    			
    				->where('contacts.is_primary', '=', true)	
					->select('clients.public_id as client_public_id', 'invoice_number', 'clients.name as client_name', 'invoices.public_id', 'amount', 'invoices.balance', 'invoice_date', 'due_date', 'invoice_statuses.name as invoice_status_name', 'invoices.currency_id', 'contacts.first_name', 'contacts.last_name', 'contacts.email');

    	if ($clientPublicId) 
    	{
    		$query->where('clients.public_id', '=', $clientPublicId);
    	}

    	if ($filter)
    	{
    		$query->where(function($query) use ($filter)
            {
            	$query->where('clients.name', 'like', '%'.$filter.'%')
            		  ->orWhere('invoices.invoice_number', 'like', '%'.$filter.'%')
            		  ->orWhere('invoice_statuses.name', 'like', '%'.$filter.'%');
            });
    	}

    	return $query;
	}

	public function getRecurringInvoices($accountId, $clientPublicId = false, $filter = false)
	{
    	$query = \DB::table('invoices')
    				->join('clients', 'clients.id', '=','invoices.client_id')
					->join('frequencies', 'frequencies.id', '=', 'invoices.frequency_id')
					->join('contacts', 'contacts.client_id', '=', 'clients.id')
					->where('invoices.account_id', '=', $accountId)
    				->where('invoices.deleted_at', '=', null)
    				->where('invoices.is_recurring', '=', true)
    				->where('contacts.is_primary', '=', true)	
					->select('clients.public_id as client_public_id', 'clients.name as client_name', 'invoices.public_id', 'amount', 'frequencies.name as frequency', 'start_date', 'end_date', 'invoices.currency_id', 'contacts.first_name', 'contacts.last_name', 'contacts.email');

    	if ($clientPublicId) 
    	{
    		$query->where('clients.public_id', '=', $clientPublicId);
    	}

    	if ($filter)
    	{
    		$query->where(function($query) use ($filter)
            {
            	$query->where('clients.name', 'like', '%'.$filter.'%')
            		  ->orWhere('invoices.invoice_number', 'like', '%'.$filter.'%');
            });
    	}

    	return $query;
	}

	public function save($publicId, $data)
	{
		if ($publicId) 
		{
			$invoice = Invoice::scope($publicId)->firstOrFail();
			$invoice->invoice_items()->forceDelete();
		} 
		else 
		{				
			$invoice = Invoice::createNew();			
		}			
		
		$invoice->client_id = $data['client_id'];
		$invoice->discount = $data['discount'];
		$invoice->invoice_number = trim($data['invoice_number']);
		$invoice->invoice_date = Utils::toSqlDate($data['invoice_date']);
		$invoice->due_date = Utils::toSqlDate($data['due_date']);					

		$invoice->is_recurring = $data['is_recurring'];
		$invoice->frequency_id = $data['frequency_id'] ? $data['frequency_id'] : 0;
		$invoice->start_date = Utils::toSqlDate($data['start_date']);
		$invoice->end_date = Utils::toSqlDate($data['end_date']);
		$invoice->terms = trim($data['terms']);
		$invoice->public_notes = trim($data['public_notes']);
		$invoice->po_number = trim($data['po_number']);
		$invoice->currency_id = $data['currency_id'];

		$total = 0;						

		foreach ($data['invoice_items'] as $item) 
		{
			if (!isset($item->cost)) 
			{
				$item->cost = 0;
			}
		
			if (!isset($item->qty)) 
			{
				$item->qty = 0;
			}

			$total += floatval($item->qty) * floatval($item->cost);
		}

		$invoice->amount = $total;
		$invoice->balance = $total;
		$invoice->save();

		foreach ($data['invoice_items'] as $item) 
		{
			if (!$item->cost && !$item->qty && !$item->product_key && !$item->notes)
			{
				continue;
			}

			if ($item->product_key)
			{
				$product = Product::findProductByKey(trim($item->product_key));

				if (!$product)
				{
					$product = Product::createNew();						
					$product->product_key = trim($item->product_key);
				}

				/*
				$product->notes = $item->notes;
				$product->cost = $item->cost;
				$product->qty = $item->qty;
				*/
				
				$product->save();
			}

			$invoiceItem = InvoiceItem::createNew();
			$invoiceItem->product_id = isset($product) ? $product->id : null;
			$invoiceItem->product_key = trim($item->product_key);
			$invoiceItem->notes = trim($item->notes);
			$invoiceItem->cost = floatval($item->cost);
			$invoiceItem->qty = floatval($item->qty);

			if ($item->tax && isset($item->tax->rate) && isset($item->tax->name))
			{
				$invoiceItem->tax_rate = floatval($item->tax->rate);
				$invoiceItem->tax_name = trim($item->tax->name);
			}

			$invoice->invoice_items()->save($invoiceItem);
			$total += floatval($item->qty) * floatval($item->cost);
		}

		return $invoice;
	}
}
