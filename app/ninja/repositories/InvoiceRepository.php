<?php namespace ninja\repositories;

use Invoice;
use InvoiceItem;
use Invitation;
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
            ->where('clients.deleted_at', '=', null)
            ->where('contacts.deleted_at', '=', null)
    				->where('invoices.is_recurring', '=', false)    			
    				->where('contacts.is_primary', '=', true)	
  					->select('clients.public_id as client_public_id', 'invoice_number', 'invoice_status_id', 'clients.name as client_name', 'invoices.public_id', 'amount', 'invoices.balance', 'invoice_date', 'due_date', 'invoice_statuses.name as invoice_status_name', 'clients.currency_id', 'contacts.first_name', 'contacts.last_name', 'contacts.email', 'quote_id', 'quote_invoice_id');

      if (!\Session::get('show_trash:invoice'))
      {
        $query->where('invoices.deleted_at', '=', null);
      }

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
            		  ->orWhere('invoice_statuses.name', 'like', '%'.$filter.'%')
                  ->orWhere('contacts.first_name', 'like', '%'.$filter.'%')
                  ->orWhere('contacts.last_name', 'like', '%'.$filter.'%')
                  ->orWhere('contacts.email', 'like', '%'.$filter.'%');
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
            ->where('invoices.is_quote', '=', false)
            ->where('clients.deleted_at', '=', null)
    				->where('invoices.is_recurring', '=', true)
    				->where('contacts.is_primary', '=', true)	
			   		->select('clients.public_id as client_public_id', 'clients.name as client_name', 'invoices.public_id', 'amount', 'frequencies.name as frequency', 'start_date', 'end_date', 'clients.currency_id', 'contacts.first_name', 'contacts.last_name', 'contacts.email');

    	if ($clientPublicId) 
    	{
    		$query->where('clients.public_id', '=', $clientPublicId);
    	}
      
      if (!\Session::get('show_trash:invoice'))
      {
        $query->where('invoices.deleted_at', '=', null);
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

  public function getDatatable($accountId, $clientPublicId = null, $entityType, $search)
  {
    $query = $this->getInvoices($accountId, $clientPublicId, $search)
              ->where('invoices.is_quote', '=', $entityType == ENTITY_QUOTE ? true : false);

    $table = \Datatable::query($query);      

    if (!$clientPublicId) 
    {
      $table->addColumn('checkbox', function($model) { return '<input type="checkbox" name="ids[]" value="' . $model->public_id . '">'; });
    }
    
    $table->addColumn("invoice_number", function($model) use ($entityType) { return link_to("{$entityType}s/" . $model->public_id . '/edit', $model->invoice_number); });

    if (!$clientPublicId) 
    {
      $table->addColumn('client_name', function($model) { return link_to('clients/' . $model->client_public_id, Utils::getClientDisplayName($model)); });
    }
    
    $table->addColumn("invoice_date", function($model) { return Utils::fromSqlDate($model->invoice_date); })         
      ->addColumn('amount', function($model) { return Utils::formatMoney($model->amount, $model->currency_id); });

    if ($entityType == ENTITY_INVOICE)
    {
      $table->addColumn('balance', function($model) { return Utils::formatMoney($model->balance, $model->currency_id); });
    }

    return $table->addColumn('due_date', function($model) { return Utils::fromSqlDate($model->due_date); })
        ->addColumn('invoice_status_name', function($model) { return $model->invoice_status_name; })
        ->addColumn('dropdown', function($model) use ($entityType)
        { 
          $str = '<div class="btn-group tr-action" style="visibility:hidden;">
              <button type="button" class="btn btn-xs btn-default dropdown-toggle" data-toggle="dropdown">
                '.trans('texts.select').' <span class="caret"></span>
              </button>
              <ul class="dropdown-menu" role="menu">
              <li><a href="' . \URL::to("{$entityType}s/".$model->public_id.'/edit') . '">'.trans("texts.edit_{$entityType}").'</a></li>
              <li><a href="' . \URL::to("{$entityType}s/".$model->public_id.'/clone') . '">'.trans("texts.clone_{$entityType}").'</a></li>
              <li class="divider"></li>';

            if ($model->invoice_status_id < INVOICE_STATUS_SENT) 
            {
              $str .= '<li><a href="javascript:markEntity(' . $model->public_id . ', ' . INVOICE_STATUS_SENT . ')">'.trans("texts.mark_sent").'</a></li>';
            }              
                      
            if ($entityType == ENTITY_INVOICE)              
            {
              if ($model->invoice_status_id < INVOICE_STATUS_PAID)
              {
                $str .= '<li><a href="' . \URL::to('payments/create/' . $model->client_public_id . '/' . $model->public_id ) . '">'.trans('texts.enter_payment').'</a></li>';
              }

              if ($model->quote_id)
              {
                $str .= '<li><a href="' .  \URL::to("quotes/{$model->quote_id}/edit") . '">' . trans("texts.view_quote") . '</a></li>';
              }            
            }
            else if ($entityType == ENTITY_QUOTE)
            {
              if ($model->quote_invoice_id)
              {
                $str .= '<li><a href="' .  \URL::to("invoices/{$model->quote_invoice_id}/edit") . '">' . trans("texts.view_invoice") . '</a></li>';
              }
            }

            return $str . '<li class="divider"></li>
                <li><a href="javascript:archiveEntity(' . $model->public_id . ')">'.trans("texts.archive_{$entityType}").'</a></li>
                <li><a href="javascript:deleteEntity(' . $model->public_id . ')">'.trans("texts.delete_{$entityType}").'</a></li>               
              </ul>
            </div>';
          })                  
          ->make();
  }


	public function getErrors($input)
	{
		$contact = (array) $input->client->contacts[0];
		$rules = ['email' => 'required|email'];
  	$validator = \Validator::make($contact, $rules);

  	if ($validator->fails())
  	{
  		return $validator;
  	}

  	$invoice = (array) $input;
  	$invoiceId = isset($invoice['public_id']) && $invoice['public_id'] ? Invoice::getPrivateId($invoice['public_id']) : null;
  	$rules = ['invoice_number' => 'required|unique:invoices,invoice_number,' . $invoiceId . ',id,account_id,' . \Auth::user()->account_id];    	

  	if ($invoice['is_recurring'] && $invoice['start_date'] && $invoice['end_date'])
  	{
  		$rules['end_date'] = 'after:' . $invoice['start_date'];
  	}

  	$validator = \Validator::make($invoice, $rules);

  	if ($validator->fails())
  	{
  		return $validator;
  	}

  	return false;
	}

	public function save($publicId, $data, $entityType)
	{
		if ($publicId) 
		{
			$invoice = Invoice::scope($publicId)->firstOrFail();
		} 
		else 
		{				
			$invoice = Invoice::createNew();			

      if ($entityType == ENTITY_QUOTE)
      {
        $invoice->is_quote = true;
      }
		}			
		
		$invoice->client_id = $data['client_id'];
		$invoice->discount = Utils::parseFloat($data['discount']);
		$invoice->invoice_number = trim($data['invoice_number']);
		$invoice->is_recurring = $data['is_recurring'] ? true : false;
    $invoice->invoice_date = Utils::toSqlDate($data['invoice_date']);
      
    if ($invoice->is_recurring)
    {
      $invoice->frequency_id = $data['frequency_id'] ? $data['frequency_id'] : 0;
      $invoice->start_date = Utils::toSqlDate($data['start_date']);
      $invoice->end_date = Utils::toSqlDate($data['end_date']);
      $invoice->due_date = null;
    }
    else
    {
      $invoice->due_date = Utils::toSqlDate($data['due_date']);
      $invoice->frequency_id = 0;
      $invoice->start_date = null;
      $invoice->end_date = null;
    }

		$invoice->terms = trim($data['terms']);
		$invoice->public_notes = trim($data['public_notes']);
		$invoice->po_number = trim($data['po_number']);
    $invoice->invoice_design_id = $data['invoice_design_id'];

		if (isset($data['tax_name']) && isset($data['tax_rate']) && Utils::parseFloat($data['tax_rate']) > 0)
		{
			$invoice->tax_rate = Utils::parseFloat($data['tax_rate']);
			$invoice->tax_name = trim($data['tax_name']);
		} 
		else
		{
			$invoice->tax_rate = 0;
			$invoice->tax_name = '';
		}
		
		$total = 0;						
		
		foreach ($data['invoice_items'] as $item) 
		{
			if (!$item->cost && !$item->product_key && !$item->notes)
			{
				continue;
			}

			$invoiceItemCost = Utils::parseFloat($item->cost);
			$invoiceItemQty = Utils::parseFloat($item->qty);
			$invoiceItemTaxRate = 0;

			if (isset($item->tax_rate) && Utils::parseFloat($item->tax_rate) > 0)
			{
				$invoiceItemTaxRate = Utils::parseFloat($item->tax_rate);				
			}

			$lineTotal = $invoiceItemCost * $invoiceItemQty;
      
			$total += round($lineTotal + ($lineTotal * $invoiceItemTaxRate / 100), 2);
		}

		if ($invoice->discount > 0)
		{
			$total *= (100 - $invoice->discount) / 100;
		}

    $invoice->custom_value1 = round($data['custom_value1'], 2);
    $invoice->custom_value2 = round($data['custom_value2'], 2);
    $invoice->custom_taxes1 = $data['custom_taxes1'] ? true : false;
    $invoice->custom_taxes2 = $data['custom_taxes2'] ? true : false;

    // custom fields charged taxes
    if ($invoice->custom_value1 && $invoice->custom_taxes1) {
      $total += $invoice->custom_value1;
    }
    if ($invoice->custom_value2 && $invoice->custom_taxes2) {
      $total += $invoice->custom_value2;
    }

		$total += $total * $invoice->tax_rate / 100;
    $total = round($total, 2);

    // custom fields not charged taxes
    if ($invoice->custom_value1 && !$invoice->custom_taxes1) {
      $total += $invoice->custom_value1;
    }
    if ($invoice->custom_value2 && !$invoice->custom_taxes2) {
      $total += $invoice->custom_value2;
    }

    if ($publicId)    
    {
		  $invoice->balance = $total - ($invoice->amount - $invoice->balance);
    }
    else
    {
      $invoice->balance = $total; 
    }

    $invoice->amount = $total;
		$invoice->save();

    $invoice->invoice_items()->forceDelete();
    
    foreach ($data['invoice_items'] as $item) 
    {
      if (!$item->cost && !$item->product_key && !$item->notes)
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

        if (\Auth::user()->account->update_products)
        {
          $product->notes = $item->notes;
          $product->cost = $item->cost;
          //$product->qty = $item->qty;
        }
        
        $product->save();
      }

      $invoiceItem = InvoiceItem::createNew();
      $invoiceItem->product_id = isset($product) ? $product->id : null;
      $invoiceItem->product_key = trim($item->product_key);
      $invoiceItem->notes = trim($item->notes);
      $invoiceItem->cost = Utils::parseFloat($item->cost);
      $invoiceItem->qty = Utils::parseFloat($item->qty);
      $invoiceItem->tax_rate = 0;

      if (isset($item->tax_rate) && Utils::parseFloat($item->tax_rate) > 0)
      {
        $invoiceItem->tax_rate = Utils::parseFloat($item->tax_rate);
        $invoiceItem->tax_name = trim($item->tax_name);
      }

      $invoice->invoice_items()->save($invoiceItem);
    }

		if ($data['set_default_terms'])
		{
			$account = \Auth::user()->account;
			$account->invoice_terms = $invoice->terms;
			$account->save();
		}

		return $invoice;
	}

  public function cloneInvoice($invoice, $quotePublicId = null)
  {
    $invoice->load('invitations', 'invoice_items');

    $clone = Invoice::createNew($invoice);
    $clone->balance = $invoice->amount;
    $clone->invoice_number = $invoice->account->getNextInvoiceNumber();

    foreach ([
      'client_id',       
      'discount', 
      'invoice_date', 
      'po_number', 
      'due_date', 
      'is_recurring', 
      'frequency_id', 
      'start_date', 
      'end_date', 
      'terms', 
      'public_notes', 
      'invoice_design_id', 
      'tax_name', 
      'tax_rate', 
      'amount', 
      'is_quote',
      'custom_value1',
      'custom_value2',
      'custom_taxes1',
      'custom_taxes2'] as $field) 
    {
      $clone->$field = $invoice->$field;  
    }   

    if ($quotePublicId)
    {
      $clone->is_quote = false;
      $clone->quote_id = $quotePublicId;
    }    
    
    $clone->save();

    if ($quotePublicId)
    {
      $invoice->quote_invoice_id = $clone->public_id;
      $invoice->save();
    }
    
    foreach ($invoice->invoice_items as $item)
    {
      $cloneItem = InvoiceItem::createNew($invoice);
      
      foreach ([
        'product_id', 
        'product_key', 
        'notes', 
        'cost', 
        'qty', 
        'tax_name', 
        'tax_rate'] as $field) 
      {
        $cloneItem->$field = $item->$field;
      }

      $clone->invoice_items()->save($cloneItem);      
    }   

    foreach ($invoice->invitations as $invitation)
    {
      $cloneInvitation = Invitation::createNew($invoice);
      $cloneInvitation->contact_id = $invitation->contact_id;
      $cloneInvitation->invitation_key = str_random(RANDOM_KEY_LENGTH);
      $clone->invitations()->save($cloneInvitation);
    }

    return $clone;
  }


	public function bulk($ids, $action, $statusId = false)
	{
		if (!$ids)
		{
			return 0;
		}

		$invoices = Invoice::withTrashed()->scope($ids)->get();

		foreach ($invoices as $invoice) 
		{
      if ($action == 'mark')
      {
        $invoice->invoice_status_id = $statusId;
        $invoice->save();
      } 
      else 
      {
  			if ($action == 'delete') 
  			{
  				$invoice->is_deleted = true;
  				$invoice->save();
  			} 

  			$invoice->delete();
      }
		}

		return count($invoices);
	}
}
