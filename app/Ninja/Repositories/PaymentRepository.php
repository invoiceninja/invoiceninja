<?php namespace App\Ninja\Repositories;

use App\Models\Payment;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\Client;
use Utils;

class PaymentRepository
{
    public function find($clientPublicId = null, $filter = null)
    {
        $query = \DB::table('payments')
                    ->join('clients', 'clients.id', '=', 'payments.client_id')
                    ->join('invoices', 'invoices.id', '=', 'payments.invoice_id')
                    ->join('contacts', 'contacts.client_id', '=', 'clients.id')
                    ->leftJoin('payment_types', 'payment_types.id', '=', 'payments.payment_type_id')
                    ->leftJoin('account_gateways', 'account_gateways.id', '=', 'payments.account_gateway_id')
                    ->leftJoin('gateways', 'gateways.id', '=', 'account_gateways.gateway_id')
                    ->where('payments.account_id', '=', \Auth::user()->account_id)
                    ->where('clients.deleted_at', '=', null)
                    ->where('contacts.is_primary', '=', true)
                    ->where('contacts.deleted_at', '=', null)
                    ->select('payments.public_id', 'payments.transaction_reference', 'clients.name as client_name', 'clients.public_id as client_public_id', 'payments.amount', 'payments.payment_date', 'invoices.public_id as invoice_public_id', 'invoices.invoice_number', 'clients.currency_id', 'contacts.first_name', 'contacts.last_name', 'contacts.email', 'payment_types.name as payment_type', 'payments.account_gateway_id', 'payments.deleted_at', 'payments.is_deleted', 'invoices.is_deleted as invoice_is_deleted', 'gateways.name as gateway_name');

        if (!\Session::get('show_trash:payment')) {
            $query->where('payments.deleted_at', '=', null)
                    ->where('invoices.deleted_at', '=', null);
        }

        if ($clientPublicId) {
            $query->where('clients.public_id', '=', $clientPublicId);
        }

        if ($filter) {
            $query->where(function ($query) use ($filter) {
                $query->where('clients.name', 'like', '%'.$filter.'%');
            });
        }

        return $query;
    }

    public function findForContact($contactId = null, $filter = null)
    {
        $query = \DB::table('payments')
                    ->join('clients', 'clients.id', '=', 'payments.client_id')
                    ->join('invoices', 'invoices.id', '=', 'payments.invoice_id')
                    ->join('contacts', 'contacts.client_id', '=', 'clients.id')
                    ->leftJoin('invitations', function ($join) {
                        $join->on('invitations.invoice_id', '=', 'invoices.id')
                             ->on('invitations.contact_id', '=', 'contacts.id');
                    })
                    ->leftJoin('payment_types', 'payment_types.id', '=', 'payments.payment_type_id')
                    ->where('clients.is_deleted', '=', false)
                    ->where('payments.is_deleted', '=', false)
                    ->where('invitations.deleted_at', '=', null)
                    ->where('invoices.deleted_at', '=', null)
                    ->where('invitations.contact_id', '=', $contactId)
                    ->select('invitations.invitation_key', 'payments.public_id', 'payments.transaction_reference', 'clients.name as client_name', 'clients.public_id as client_public_id', 'payments.amount', 'payments.payment_date', 'invoices.public_id as invoice_public_id', 'invoices.invoice_number', 'clients.currency_id', 'contacts.first_name', 'contacts.last_name', 'contacts.email', 'payment_types.name as payment_type', 'payments.account_gateway_id');

        if ($filter) {
            $query->where(function ($query) use ($filter) {
                $query->where('clients.name', 'like', '%'.$filter.'%');
            });
        }

        return $query;
    }

    public function getErrors($input)
    {
        $rules = array(
            'client' => 'required',
            'invoice' => 'required',
            'amount' => 'required',
        );

        if ($input['payment_type_id'] == PAYMENT_TYPE_CREDIT) {
            $rules['payment_type_id'] = 'has_credit:'.$input['client'].','.$input['amount'];
        }

        if (isset($input['invoice']) && $input['invoice']) {
            $invoice = Invoice::scope($input['invoice'])->firstOrFail();
            $rules['amount'] .= "|less_than:{$invoice->balance}";
        }

        $validator = \Validator::make($input, $rules);

        if ($validator->fails()) {
            return $validator;
        }

        return false;
    }

    public function save($publicId = null, $input)
    {
        if ($publicId) {
            $payment = Payment::scope($publicId)->firstOrFail();
        } else {
            $payment = Payment::createNew();
        }

        $paymentTypeId = false;
        if (isset($input['payment_type_id'])) {
            $paymentTypeId = $input['payment_type_id'] ? $input['payment_type_id'] : null;
            $payment->payment_type_id = $paymentTypeId;
        }

        if (isset($input['payment_date_sql'])) {
            $payment->payment_date = $input['payment_date_sql'];
        } elseif (isset($input['payment_date'])) {
            $payment->payment_date = Utils::toSqlDate($input['payment_date']);
        } else {
            $payment->payment_date = date('Y-m-d');
        }
         
        $payment->transaction_reference = trim($input['transaction_reference']);

        if (!$publicId) {
            $clientId = Client::getPrivateId($input['client']);
            $amount = Utils::parseFloat($input['amount']);

            if ($paymentTypeId == PAYMENT_TYPE_CREDIT) {
                $credits = Credit::scope()->where('client_id', '=', $clientId)
                            ->where('balance', '>', 0)->orderBy('created_at')->get();
                $applied = 0;

                foreach ($credits as $credit) {
                    $applied += $credit->apply($amount);

                    if ($applied >= $amount) {
                        break;
                    }
                }
            }

            $payment->client_id = $clientId;
            $payment->invoice_id = isset($input['invoice']) && $input['invoice'] != "-1" ? Invoice::getPrivateId($input['invoice']) : null;
            $payment->amount = $amount;
        }

        $payment->save();

        return $payment;
    }

    public function bulk($ids, $action)
    {
        if (!$ids) {
            return 0;
        }

        $payments = Payment::withTrashed()->scope($ids)->get();

        foreach ($payments as $payment) {
            if ($action == 'restore') {
                $payment->restore();
            } else {
                if ($action == 'delete') {
                    $payment->is_deleted = true;
                    $payment->save();
                }

                $payment->delete();
            }
        }

        return count($payments);
    }
}
