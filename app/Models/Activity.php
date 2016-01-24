<?php namespace App\Models;

use Auth;
use Eloquent;
use Utils;
use Session;
use Request;
use Carbon;

class Activity extends Eloquent
{
    public $timestamps = true;

    public function scopeScope($query)
    {
        return $query->whereAccountId(Auth::user()->account_id);
    }

    public function account()
    {
        return $this->belongsTo('App\Models\Account');
    }

    public function user()
    {
        return $this->belongsTo('App\Models\User')->withTrashed();
    }

    public function contact()
    {
        return $this->belongsTo('App\Models\Contact')->withTrashed();
    }

    public function client()
    {
        return $this->belongsTo('App\Models\Client')->withTrashed();
    }

    public function invoice()
    {
        return $this->belongsTo('App\Models\Invoice')->withTrashed();
    }

    public function credit()
    {
        return $this->belongsTo('App\Models\Credit')->withTrashed();
    }

    public function payment()
    {
        return $this->belongsTo('App\Models\Payment')->withTrashed();
    }

    public function getMessage()
    {
        $activityTypeId = $this->activity_type_id;
        $account = $this->account;
        $client = $this->client;
        $user = $this->user;
        $invoice = $this->invoice;
        $contactId = $this->contact_id;
        $payment = $this->payment;
        $credit = $this->credit;
        $isSystem = $this->is_system;

        $data = [
            'client' => link_to($client->getRoute(), $client->getDisplayName()),
            'user' => $isSystem ? '<i>' . trans('texts.system') . '</i>' : $user->getDisplayName(),
            'invoice' => $invoice ? link_to($invoice->getRoute(), $invoice->getDisplayName()) : null,
            'quote' => $invoice ? link_to($invoice->getRoute(), $invoice->getDisplayName()) : null,
            'contact' => $contactId ? $client->getDisplayName() : $user->getDisplayName(),
            'payment' => $payment ? $payment->transaction_reference : null,
            'credit' => $credit ? $account->formatMoney($credit->amount, $client) : null,
        ];

        return trans("texts.activity_{$activityTypeId}", $data);
    }
}
