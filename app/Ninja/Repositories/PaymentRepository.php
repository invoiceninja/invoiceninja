<?php

namespace App\Ninja\Repositories;

use App\Models\Credit;
use App\Models\Invoice;
use App\Models\Payment;
use DB;
use Utils;
use Auth;

class PaymentRepository extends BaseRepository
{
    public function getClassName()
    {
        return 'App\Models\Payment';
    }

    public function find($clientPublicId = null, $filter = null)
    {
        $query = DB::table('payments')
                    ->join('accounts', 'accounts.id', '=', 'payments.account_id')
                    ->join('clients', 'clients.id', '=', 'payments.client_id')
                    ->join('invoices', 'invoices.id', '=', 'payments.invoice_id')
                    ->join('contacts', 'contacts.client_id', '=', 'clients.id')
                    ->join('payment_statuses', 'payment_statuses.id', '=', 'payments.payment_status_id')
                    ->leftJoin('payment_types', 'payment_types.id', '=', 'payments.payment_type_id')
                    ->leftJoin('account_gateways', 'account_gateways.id', '=', 'payments.account_gateway_id')
                    ->leftJoin('gateways', 'gateways.id', '=', 'account_gateways.gateway_id')
                    ->where('payments.account_id', '=', \Auth::user()->account_id)
                    ->where('contacts.is_primary', '=', true)
                    ->where('contacts.deleted_at', '=', null)
                    ->where('invoices.is_deleted', '=', false)
                    ->select('payments.public_id',
                        DB::raw('COALESCE(clients.currency_id, accounts.currency_id) currency_id'),
                        DB::raw('COALESCE(clients.country_id, accounts.country_id) country_id'),
                        'payments.transaction_reference',
                        DB::raw("COALESCE(NULLIF(clients.name,''), NULLIF(CONCAT(contacts.first_name, ' ', contacts.last_name),''), NULLIF(contacts.email,'')) client_name"),
                        'clients.public_id as client_public_id',
                        'clients.user_id as client_user_id',
                        'payments.amount',
                        DB::raw("CONCAT(payments.payment_date, payments.created_at) as date"),
                        'payments.payment_date',
                        'payments.payment_status_id',
                        'payments.payment_type_id',
                        'payments.payment_type_id as source',
                        'invoices.public_id as invoice_public_id',
                        'invoices.user_id as invoice_user_id',
                        'invoices.invoice_number',
                        'invoices.invoice_number as invoice_name',
                        'contacts.first_name',
                        'contacts.last_name',
                        'contacts.email',
                        'payment_types.name as method',
                        'payment_types.name as payment_type',
                        'payments.account_gateway_id',
                        'payments.deleted_at',
                        'payments.is_deleted',
                        'payments.user_id',
                        'payments.refunded',
                        'payments.expiration',
                        'payments.last4',
                        'payments.email',
                        'payments.routing_number',
                        'payments.bank_name',
                        'invoices.is_deleted as invoice_is_deleted',
                        'gateways.name as gateway_name',
                        'gateways.id as gateway_id',
                        'payment_statuses.name as status'
                    );

        $this->applyFilters($query, ENTITY_PAYMENT);

        if ($clientPublicId) {
            $query->where('clients.public_id', '=', $clientPublicId);
        } else {
            $query->whereNull('clients.deleted_at');
        }

        if ($filter) {
            $query->where(function ($query) use ($filter) {
                $query->where('clients.name', 'like', '%'.$filter.'%')
                      ->orWhere('invoices.invoice_number', 'like', '%'.$filter.'%')
                      ->orWhere('payments.transaction_reference', 'like', '%'.$filter.'%')
                      ->orWhere('gateways.name', 'like', '%'.$filter.'%')
                      ->orWhere('payment_types.name', 'like', '%'.$filter.'%')
                      ->orWhere('contacts.first_name', 'like', '%'.$filter.'%')
                      ->orWhere('contacts.last_name', 'like', '%'.$filter.'%')
                      ->orWhere('contacts.email', 'like', '%'.$filter.'%');
            });
        }

        return $query;
    }

    public function findForContact($contactId = null, $filter = null)
    {
        $query = DB::table('payments')
                    ->join('accounts', 'accounts.id', '=', 'payments.account_id')
                    ->join('clients', 'clients.id', '=', 'payments.client_id')
                    ->join('invoices', 'invoices.id', '=', 'payments.invoice_id')
                    ->join('contacts', 'contacts.client_id', '=', 'clients.id')
                    ->join('payment_statuses', 'payment_statuses.id', '=', 'payments.payment_status_id')
                    ->leftJoin('invitations', function ($join) {
                        $join->on('invitations.invoice_id', '=', 'invoices.id')
                             ->on('invitations.contact_id', '=', 'contacts.id');
                    })
                    ->leftJoin('payment_types', 'payment_types.id', '=', 'payments.payment_type_id')
                    ->where('clients.is_deleted', '=', false)
                    ->where('payments.is_deleted', '=', false)
                    ->where('invitations.deleted_at', '=', null)
                    ->where('invoices.is_deleted', '=', false)
                    ->where('invoices.is_public', '=', true)
                    ->where('invitations.contact_id', '=', $contactId)
                    ->select(
                        DB::raw('COALESCE(clients.currency_id, accounts.currency_id) currency_id'),
                        DB::raw('COALESCE(clients.country_id, accounts.country_id) country_id'),
                        'invitations.invitation_key',
                        'payments.public_id',
                        'payments.transaction_reference',
                        DB::raw("COALESCE(NULLIF(clients.name,''), NULLIF(CONCAT(contacts.first_name, ' ', contacts.last_name),''), NULLIF(contacts.email,'')) client_name"),
                        'clients.public_id as client_public_id',
                        'payments.amount',
                        'payments.payment_date',
                        'payments.payment_type_id',
                        'invoices.public_id as invoice_public_id',
                        'invoices.invoice_number',
                        'contacts.first_name',
                        'contacts.last_name',
                        'contacts.email',
                        'payment_types.name as payment_type',
                        'payments.account_gateway_id',
                        'payments.refunded',
                        'payments.expiration',
                        'payments.last4',
                        'payments.email',
                        'payments.routing_number',
                        'payments.bank_name',
                        'payments.payment_status_id',
                        'payment_statuses.name as payment_status_name'
                    );

        if ($filter) {
            $query->where(function ($query) use ($filter) {
                $query->where('clients.name', 'like', '%'.$filter.'%');
            });
        }

        return $query;
    }

    public function save($input, $payment = null)
    {
        $publicId = isset($input['public_id']) ? $input['public_id'] : false;

        if ($payment) {
            // do nothing
        } elseif ($publicId) {
            $payment = Payment::scope($publicId)->firstOrFail();
            if (Utils::isNinjaDev()) {
                \Log::warning('Entity not set in payment repo save');
            }
        } else {
            $payment = Payment::createNew();

            if (Auth::check() && Auth::user()->account->payment_type_id) {
                $payment->payment_type_id = Auth::user()->account->payment_type_id;
            }
        }

        if ($payment->is_deleted) {
            return $payment;
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

        if (isset($input['transaction_reference'])) {
            $payment->transaction_reference = trim($input['transaction_reference']);
        }
        if (isset($input['private_notes'])) {
            $payment->private_notes = trim($input['private_notes']);
        }

        if (! $publicId) {
            $clientId = $input['client_id'];
            $amount = Utils::parseFloat($input['amount']);

            if ($paymentTypeId == PAYMENT_TYPE_CREDIT) {
                $credits = Credit::scope()->where('client_id', '=', $clientId)
                            ->where('balance', '>', 0)->orderBy('created_at')->get();

                $remaining = $amount;
                foreach ($credits as $credit) {
                    $remaining -= $credit->apply($remaining);
                    if (! $remaining) {
                        break;
                    }
                }
            }

            $payment->invoice_id = $input['invoice_id'];
            $payment->client_id = $clientId;
            $payment->amount = $amount;
        }

        $payment->save();

        return $payment;
    }

    public function delete($payment)
    {
        if ($payment->invoice->is_deleted) {
            return false;
        }

        parent::delete($payment);
    }

    public function restore($payment)
    {
        if ($payment->invoice->is_deleted) {
            return false;
        }

        parent::restore($payment);
    }
}
