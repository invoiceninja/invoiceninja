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
        return $this->belongsTo('App\Models\User');
    }

    private static function getBlank($entity = false)
    {
        $activity = new Activity();

        if ($entity) {
            $activity->user_id = $entity instanceof User ? $entity->id : $entity->user_id;
            $activity->account_id = $entity->account_id;
        } elseif (Auth::check()) {
            $activity->user_id = Auth::user()->id;
            $activity->account_id = Auth::user()->account_id;
        } else {
            Utils::fatalError();
        }

        $activity->token_id = Session::get('token_id', null);
        $activity->ip = Request::getClientIp();

        return $activity;
    }

    public static function createClient($client, $notify = true)
    {
        $activity = Activity::getBlank();
        $activity->client_id = $client->id;
        $activity->activity_type_id = ACTIVITY_TYPE_CREATE_CLIENT;
        $activity->message = Utils::encodeActivity(Auth::user(), 'created', $client);
        $activity->save();

        if ($notify) {
            Activity::checkSubscriptions(EVENT_CREATE_CLIENT, $client);
        }
    }

    public static function updateClient($client)
    {
        if ($client->is_deleted && !$client->getOriginal('is_deleted')) {
            $activity = Activity::getBlank();
            $activity->client_id = $client->id;
            $activity->activity_type_id = ACTIVITY_TYPE_DELETE_CLIENT;
            $activity->message = Utils::encodeActivity(Auth::user(), 'deleted', $client);
            $activity->balance = $client->balance;
            $activity->save();
        }
    }

    public static function archiveClient($client)
    {
        if (!$client->is_deleted) {
            $activity = Activity::getBlank();
            $activity->client_id = $client->id;
            $activity->activity_type_id = ACTIVITY_TYPE_ARCHIVE_CLIENT;
            $activity->message = Utils::encodeActivity(Auth::user(), 'archived', $client);
            $activity->balance = $client->balance;
            $activity->save();
        }
    }

    public static function restoreClient($client)
    {
        $activity = Activity::getBlank();
        $activity->client_id = $client->id;
        $activity->activity_type_id = ACTIVITY_TYPE_RESTORE_CLIENT;
        $activity->message = Utils::encodeActivity(Auth::user(), 'restored', $client);
        $activity->balance = $client->balance;
        $activity->save();
    }

    public static function createInvoice($invoice)
    {
        if (Auth::check()) {
            $message = Utils::encodeActivity(Auth::user(), 'created', $invoice);
        } else {
            $message = Utils::encodeActivity(null, 'created', $invoice);
        }

        $adjustment = 0;
        $client = $invoice->client;
        if (!$invoice->is_quote && !$invoice->is_recurring) {
            $adjustment = $invoice->amount;
            $client->balance = $client->balance + $adjustment;
            $client->save();
        }

        $activity = Activity::getBlank($invoice);
        $activity->invoice_id = $invoice->id;
        $activity->client_id = $invoice->client_id;
        $activity->activity_type_id = $invoice->is_quote ? ACTIVITY_TYPE_CREATE_QUOTE : ACTIVITY_TYPE_CREATE_INVOICE;
        $activity->message = $message;
        $activity->balance = $client->balance;
        $activity->adjustment = $adjustment;
        $activity->save();

        Activity::checkSubscriptions($invoice->is_quote ? EVENT_CREATE_QUOTE : EVENT_CREATE_INVOICE, $invoice);
    }

    public static function archiveInvoice($invoice)
    {
        if (!$invoice->is_deleted) {
            $activity = Activity::getBlank();
            $activity->invoice_id = $invoice->id;
            $activity->client_id = $invoice->client_id;
            $activity->activity_type_id = $invoice->is_quote ? ACTIVITY_TYPE_ARCHIVE_QUOTE : ACTIVITY_TYPE_ARCHIVE_INVOICE;
            $activity->message = Utils::encodeActivity(Auth::user(), 'archived', $invoice);
            $activity->balance = $invoice->client->balance;

            $activity->save();
        }
    }

    public static function restoreInvoice($invoice)
    {
        $activity = Activity::getBlank();
        $activity->invoice_id = $invoice->id;
        $activity->client_id = $invoice->client_id;
        $activity->activity_type_id = $invoice->is_quote ? ACTIVITY_TYPE_RESTORE_QUOTE : ACTIVITY_TYPE_RESTORE_INVOICE;
        $activity->message = Utils::encodeActivity(Auth::user(), 'restored', $invoice);
        $activity->balance = $invoice->client->balance;

        $activity->save();
    }

    public static function emailInvoice($invitation)
    {
        $adjustment = 0;
        $client = $invitation->invoice->client;

        $activity = Activity::getBlank($invitation);
        $activity->client_id = $invitation->invoice->client_id;
        $activity->invoice_id = $invitation->invoice_id;
        $activity->contact_id = $invitation->contact_id;
        $activity->activity_type_id = $invitation->invoice ? ACTIVITY_TYPE_EMAIL_QUOTE : ACTIVITY_TYPE_EMAIL_INVOICE;
        $activity->message = Utils::encodeActivity(Auth::check() ? Auth::user() : null, 'emailed', $invitation->invoice, $invitation->contact);
        $activity->balance = $client->balance;
        $activity->save();
    }

    public static function updateInvoice($invoice)
    {
        $client = $invoice->client;

        if ($invoice->is_deleted && !$invoice->getOriginal('is_deleted')) {
            $adjustment = 0;
            if (!$invoice->is_quote && !$invoice->is_recurring) {
                $adjustment = $invoice->balance * -1;
                $client->balance = $client->balance - $invoice->balance;
                $client->paid_to_date = $client->paid_to_date - ($invoice->amount - $invoice->balance);
                $client->save();
            }

            $activity = Activity::getBlank();
            $activity->client_id = $invoice->client_id;
            $activity->invoice_id = $invoice->id;
            $activity->activity_type_id = $invoice->is_quote ? ACTIVITY_TYPE_DELETE_QUOTE : ACTIVITY_TYPE_DELETE_INVOICE;
            $activity->message = Utils::encodeActivity(Auth::user(), 'deleted', $invoice);
            $activity->balance = $invoice->client->balance;
            $activity->adjustment = $adjustment;
            $activity->save();

            // Release any tasks associated with the deleted invoice
            Task::where('invoice_id', '=', $invoice->id)
                    ->update(['invoice_id' => null]);
        } else {
            $diff = floatval($invoice->amount) - floatval($invoice->getOriginal('amount'));

            $fieldChanged = false;
            foreach (['invoice_number', 'po_number', 'invoice_date', 'due_date', 'terms', 'public_notes', 'invoice_footer', 'partial'] as $field) {
                if ($invoice->$field != $invoice->getOriginal($field)) {
                    $fieldChanged = true;
                    break;
                }
            }

            if ($diff != 0 || $fieldChanged) {
                $backupInvoice = Invoice::with('invoice_items', 'client.account', 'client.contacts')->find($invoice->id);

                if ($diff != 0 && !$invoice->is_quote && !$invoice->is_recurring) {
                    $client->balance = $client->balance + $diff;
                    $client->save();
                }

                $activity = Activity::getBlank($invoice);
                $activity->client_id = $invoice->client_id;
                $activity->invoice_id = $invoice->id;
                $activity->activity_type_id = $invoice->is_quote ? ACTIVITY_TYPE_UPDATE_QUOTE : ACTIVITY_TYPE_UPDATE_INVOICE;
                $activity->message = Utils::encodeActivity(Auth::user(), 'updated', $invoice);
                $activity->balance = $client->balance;
                $activity->adjustment = $invoice->is_quote || $invoice->is_recurring ? 0 : $diff;
                $activity->json_backup = $backupInvoice->hidePrivateFields()->toJSON();
                $activity->save();

                if ($invoice->isPaid() && $invoice->balance > 0) {
                    $invoice->invoice_status_id = INVOICE_STATUS_PARTIAL;
                } elseif ($invoice->invoice_status_id && $invoice->balance == 0) {
                    $invoice->invoice_status_id = INVOICE_STATUS_PAID;
                }
            }
        }
    }

    public static function viewInvoice($invitation)
    {
        if (Session::get($invitation->invitation_key)) {
            return;
        }

        Session::put($invitation->invitation_key, true);
        $invoice = $invitation->invoice;

        if (!$invoice->isViewed()) {
            $invoice->invoice_status_id = INVOICE_STATUS_VIEWED;
            $invoice->save();
        }

        $now = Carbon::now()->toDateTimeString();

        $invitation->viewed_date = $now;
        $invitation->save();

        $client = $invoice->client;
        $client->last_login = $now;
        $client->save();

        $activity = Activity::getBlank($invitation);
        $activity->client_id = $invitation->invoice->client_id;
        $activity->invitation_id = $invitation->id;
        $activity->contact_id = $invitation->contact_id;
        $activity->invoice_id = $invitation->invoice_id;
        $activity->activity_type_id = $invitation->invoice->is_quote ? ACTIVITY_TYPE_VIEW_QUOTE : ACTIVITY_TYPE_VIEW_INVOICE;
        $activity->message = Utils::encodeActivity($invitation->contact, 'viewed', $invitation->invoice);
        $activity->balance = $invitation->invoice->client->balance;
        $activity->save();
    }

    public static function approveQuote($invitation) {

        $activity = Activity::getBlank($invitation);
        $activity->client_id = $invitation->invoice->client_id;
        $activity->invitation_id = $invitation->id;
        $activity->contact_id = $invitation->contact_id;
        $activity->invoice_id = $invitation->invoice_id;
        $activity->activity_type_id = ACTIVITY_TYPE_APPROVE_QUOTE;
        $activity->message = Utils::encodeActivity($invitation->contact, 'approved', $invitation->invoice);
        $activity->balance = $invitation->invoice->client->balance;
        $activity->save();
    }

    public static function createPayment($payment)
    {
        $client = $payment->client;
        $client->balance = $client->balance - $payment->amount;
        $client->paid_to_date = $client->paid_to_date + $payment->amount;
        $client->save();

        if ($payment->contact_id) {
            $activity = Activity::getBlank($client);
            $activity->contact_id = $payment->contact_id;
            $activity->message = Utils::encodeActivity($payment->invitation->contact, 'entered '.$payment->getName().' for ', $payment->invoice);
        } else {
            $activity = Activity::getBlank($client);
            $message = $payment->payment_type_id == PAYMENT_TYPE_CREDIT ? 'applied credit for ' : 'entered '.$payment->getName().' for ';
            $activity->message = Utils::encodeActivity(Auth::user(), $message, $payment->invoice);
        }

        $activity->payment_id = $payment->id;

        if ($payment->invoice_id) {
            $activity->invoice_id = $payment->invoice_id;

            $invoice = $payment->invoice;
            $invoice->balance = $invoice->balance - $payment->amount;
            $invoice->invoice_status_id = ($invoice->balance > 0) ? INVOICE_STATUS_PARTIAL : INVOICE_STATUS_PAID;
            if ($invoice->partial > 0) {
                $invoice->partial = max(0, $invoice->partial - $payment->amount);
            }
            $invoice->save();
        }

        $activity->payment_id = $payment->id;
        $activity->client_id = $payment->client_id;
        $activity->activity_type_id = ACTIVITY_TYPE_CREATE_PAYMENT;
        $activity->balance = $client->balance;
        $activity->adjustment = $payment->amount * -1;
        $activity->save();

        Activity::checkSubscriptions(EVENT_CREATE_PAYMENT, $payment);
    }

    public static function updatePayment($payment)
    {
        if ($payment->is_deleted && !$payment->getOriginal('is_deleted')) {
            $client = $payment->client;
            $client->balance = $client->balance + $payment->amount;
            $client->paid_to_date = $client->paid_to_date - $payment->amount;
            $client->save();

            $invoice = $payment->invoice;
            $invoice->balance = $invoice->balance + $payment->amount;
            if ($invoice->isPaid() && $invoice->balance > 0) {
                $invoice->invoice_status_id = ($invoice->balance == $invoice->amount ? INVOICE_STATUS_DRAFT : INVOICE_STATUS_PARTIAL);
            }
            $invoice->save();

            // deleting a payment from credit creates a new credit
            if ($payment->payment_type_id == PAYMENT_TYPE_CREDIT) {
                $credit = Credit::createNew();
                $credit->client_id = $client->id;
                $credit->credit_date = Carbon::now()->toDateTimeString();
                $credit->balance = $credit->amount = $payment->amount;
                $credit->private_notes = $payment->transaction_reference;
                $credit->save();
            }

            $activity = Activity::getBlank();
            $activity->payment_id = $payment->id;
            $activity->client_id = $invoice->client_id;
            $activity->invoice_id = $invoice->id;
            $activity->activity_type_id = ACTIVITY_TYPE_DELETE_PAYMENT;
            $activity->message = Utils::encodeActivity(Auth::user(), 'deleted '.$payment->getName());
            $activity->balance = $client->balance;
            $activity->adjustment = $payment->amount;
            $activity->save();
        } else {
            /*
            $diff = floatval($invoice->amount) - floatval($invoice->getOriginal('amount'));

            if ($diff == 0)
            {
                return;
            }

            $client = $invoice->client;
            $client->balance = $client->balance + $diff;
            $client->save();

            $activity = Activity::getBlank($invoice);
            $activity->client_id = $invoice->client_id;
            $activity->invoice_id = $invoice->id;
            $activity->activity_type_id = ACTIVITY_TYPE_UPDATE_INVOICE;
            $activity->message = Utils::encodeActivity(Auth::user(), 'updated', $invoice);
            $activity->balance = $client->balance;
            $activity->adjustment = $diff;
            $activity->json_backup = $backupInvoice->hidePrivateFields()->toJSON();
            $activity->save();
            */
        }
    }

    public static function archivePayment($payment)
    {
        if ($payment->is_deleted) {
            return;
        }

        $client = $payment->client;
        $invoice = $payment->invoice;

        $activity = Activity::getBlank();
        $activity->payment_id = $payment->id;
        $activity->invoice_id = $invoice->id;
        $activity->client_id = $client->id;
        $activity->activity_type_id = ACTIVITY_TYPE_ARCHIVE_PAYMENT;
        $activity->message = Utils::encodeActivity(Auth::user(), 'archived '.$payment->getName());
        $activity->balance = $client->balance;
        $activity->adjustment = 0;
        $activity->save();
    }

    public static function restorePayment($payment)
    {
        $client = $payment->client;
        $invoice = $payment->invoice;

        $activity = Activity::getBlank();
        $activity->payment_id = $payment->id;
        $activity->invoice_id = $invoice->id;
        $activity->client_id = $client->id;
        $activity->activity_type_id = ACTIVITY_TYPE_RESTORE_PAYMENT;
        $activity->message = Utils::encodeActivity(Auth::user(), 'restored '.$payment->getName());
        $activity->balance = $client->balance;
        $activity->adjustment = 0;
        $activity->save();
    }

    public static function createCredit($credit)
    {
        $activity = Activity::getBlank();
        $activity->message = Utils::encodeActivity(Auth::user(), 'entered '.Utils::formatMoney($credit->amount, $credit->client->getCurrencyId()).' credit');
        $activity->credit_id = $credit->id;
        $activity->client_id = $credit->client_id;
        $activity->activity_type_id = ACTIVITY_TYPE_CREATE_CREDIT;
        $activity->balance = $credit->client->balance;
        $activity->save();
    }

    public static function updateCredit($credit)
    {
        if ($credit->is_deleted && !$credit->getOriginal('is_deleted')) {
            $activity = Activity::getBlank();
            $activity->credit_id = $credit->id;
            $activity->client_id = $credit->client_id;
            $activity->activity_type_id = ACTIVITY_TYPE_DELETE_CREDIT;
            $activity->message = Utils::encodeActivity(Auth::user(), 'deleted '.Utils::formatMoney($credit->balance, $credit->client->getCurrencyId()).' credit');
            $activity->balance = $credit->client->balance;
            $activity->save();
        } else {
            /*
            $diff = floatval($invoice->amount) - floatval($invoice->getOriginal('amount'));

            if ($diff == 0)
            {
                return;
            }

            $client = $invoice->client;
            $client->balance = $client->balance + $diff;
            $client->save();

            $activity = Activity::getBlank($invoice);
            $activity->client_id = $invoice->client_id;
            $activity->invoice_id = $invoice->id;
            $activity->activity_type_id = ACTIVITY_TYPE_UPDATE_INVOICE;
            $activity->message = Utils::encodeActivity(Auth::user(), 'updated', $invoice);
            $activity->balance = $client->balance;
            $activity->adjustment = $diff;
            $activity->json_backup = $backupInvoice->hidePrivateFields()->toJSON();
            $activity->save();
            */
        }
    }

    public static function archiveCredit($credit)
    {
        if ($credit->is_deleted) {
            return;
        }

        $activity = Activity::getBlank();
        $activity->client_id = $credit->client_id;
        $activity->credit_id = $credit->id;
        $activity->activity_type_id = ACTIVITY_TYPE_ARCHIVE_CREDIT;
        $activity->message = Utils::encodeActivity(Auth::user(), 'archived '.Utils::formatMoney($credit->balance, $credit->client->getCurrencyId()).' credit');
        $activity->balance = $credit->client->balance;
        $activity->save();
    }

    public static function restoreCredit($credit)
    {
        $activity = Activity::getBlank();
        $activity->client_id = $credit->client_id;
        $activity->credit_id = $credit->id;
        $activity->activity_type_id = ACTIVITY_TYPE_RESTORE_CREDIT;
        $activity->message = Utils::encodeActivity(Auth::user(), 'restored '.Utils::formatMoney($credit->balance, $credit->client->getCurrencyId()).' credit');
        $activity->balance = $credit->client->balance;
        $activity->save();
    }

    private static function checkSubscriptions($event, $data)
    {
        if (!Auth::check()) {
            return;
        }

        $subscription = Auth::user()->account->getSubscription($event);

        if ($subscription) {
            Utils::notifyZapier($subscription, $data);
        }
    }
}
