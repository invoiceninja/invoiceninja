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

    public static function calcMessage($activityTypeId, $client, $user, $invoice, $contactId, $payment, $credit, $isSystem)
    {
        $data = [
            'client' => link_to($client->getRoute(), $client->getDisplayName()),
            'user' => $isSystem ? '<i>' . trans('texts.system') . '</i>' : $user->getDisplayName(),
            'invoice' => $invoice ? link_to($invoice->getRoute(), $invoice->getDisplayName()) : null,
            'quote' => $invoice ? link_to($invoice->getRoute(), $invoice->getDisplayName()) : null,
            'contact' => $contactId ? $client->getDisplayName() : $user->getDisplayName(),
            'payment' => $payment ? $payment->transaction_reference : null,
            'credit' => $credit ? Utils::formatMoney($credit->amount, $client->currency_id) : null,
        ];

        return trans("texts.activity_{$activityTypeId}", $data);
    }

    public function getMessage()
    {
        return static::calcMessage(
            $this->activity_type_id,
            $this->client,
            $this->user,
            $this->invoice,
            $this->contact_id,
            $this->payment,
            $this->credit,
            $this->is_system
        );
    }
}
