<?php namespace ninja\repositories;

use Payment;
use Invoice;
use Client;
use Utils;

class PaymentRepository
{
	public function find($clientPublicId = null, $filter = null)
	{
        $query = \DB::table('payments')
                    ->join('clients', 'clients.id', '=','payments.client_id')
                    ->join('invoices', 'invoices.id', '=','payments.invoice_id')
                    ->join('contacts', 'contacts.client_id', '=', 'clients.id')
                    ->where('payments.account_id', '=', \Auth::user()->account_id)
                    ->where('payments.deleted_at', '=', null)
                    ->where('clients.deleted_at', '=', null)
                    ->where('contacts.is_primary', '=', true)   
                    ->select('payments.public_id', 'payments.transaction_reference', 'clients.name as client_name', 'clients.public_id as client_public_id', 'payments.amount', 'payments.payment_date', 'invoices.public_id as invoice_public_id', 'invoices.invoice_number', 'payments.currency_id', 'contacts.first_name', 'contacts.last_name', 'contacts.email');        

        if ($clientPublicId) 
        {
            $query->where('clients.public_id', '=', $clientPublicId);
        }

        if ($filter)
        {
            $query->where(function($query) use ($filter)
            {
                $query->where('clients.name', 'like', '%'.$filter.'%');
            });
        }

        return $query;
	}

	public function save($publicId = null, $input)
	{
        if ($publicId) 
        {
            $payment = Payment::scope($publicId)->firstOrFail();
        } 
        else 
        {
            $payment = Payment::createNew();
        }

        $payment->client_id = Client::getPrivateId($input['client']);
        $payment->invoice_id = isset($input['invoice']) && $input['invoice'] != "-1" ? Invoice::getPrivateId($input['invoice']) : null;
        $payment->currency_id = $input['currency_id'] ? $input['currency_id'] : null;
        $payment->payment_type_id = $input['payment_type_id'] ? $input['payment_type_id'] : null;
        $payment->payment_date = Utils::toSqlDate($input['payment_date']);
        $payment->amount = floatval($input['amount']);
        $payment->save();
	
		return $payment;		
	}

	public function bulk($ids, $action)
	{
        if (!$ids)
        {
            return 0;
        }

        $payments = Payment::scope($ids)->get();

        foreach ($payments as $payment) 
        {            
            if ($action == 'delete') 
            {
                $payment->is_deleted = true;
                $payment->save();
            } 

            $payment->delete();
        }
	
		return count($payments);
	}
}