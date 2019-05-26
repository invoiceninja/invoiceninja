<?php

namespace App\Models;

use Auth;
use Eloquent;
use Laracasts\Presenter\PresentableTrait;

/**
 * Class Activity.
 */
class Activity extends Eloquent
{
    use PresentableTrait;

    /**
     * @var string
     */
    protected $presenter = 'App\Ninja\Presenters\ActivityPresenter';

    /**
     * @var bool
     */
    public $timestamps = true;

    /**
     * @param $query
     *
     * @return mixed
     */
    public function scopeScope($query)
    {
        return $query->whereAccountId(Auth::user()->account_id);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function account()
    {
        return $this->belongsTo('App\Models\Account');
    }

    /**
     * @return mixed
     */
    public function user()
    {
        return $this->belongsTo('App\Models\User')->withTrashed();
    }

    /**
     * @return mixed
     */
    public function contact()
    {
        return $this->belongsTo('App\Models\Contact')->withTrashed();
    }

    /**
     * @return mixed
     */
    public function client()
    {
        return $this->belongsTo('App\Models\Client')->withTrashed();
    }

    /**
     * @return mixed
     */
    public function invoice()
    {
        return $this->belongsTo('App\Models\Invoice')->withTrashed();
    }

    /**
     * @return mixed
     */
    public function credit()
    {
        return $this->belongsTo('App\Models\Credit')->withTrashed();
    }

    /**
     * @return mixed
     */
    public function payment()
    {
        return $this->belongsTo('App\Models\Payment')->withTrashed();
    }

    public function task()
    {
        return $this->belongsTo('App\Models\Task')->withTrashed();
    }

    public function expense()
    {
        return $this->belongsTo('App\Models\Expense')->withTrashed();
    }

    public function key()
    {
        return sprintf('%s-%s-%s', $this->activity_type_id, $this->client_id, $this->created_at->timestamp);
    }

    /**
     * @return mixed
     */
    public function getMessage()
    {
        $activityTypeId = $this->activity_type_id;
        $account = $this->account;
        $client = $this->client;
        $user = $this->user;
        $invoice = $this->invoice;
        $contactId = $this->contact_id;
        $contact = $this->contact;
        $payment = $this->payment;
        $credit = $this->credit;
        $expense = $this->expense;
        $isSystem = $this->is_system;
        $task = $this->task;

        $data = [
            'client' => $client ? link_to($client->getRoute(), $client->getDisplayName()) : null,
            'user' => $isSystem ? '<i>' . trans('texts.system') . '</i>' : e($user->getDisplayName()),
            'invoice' => $invoice ? link_to($invoice->getRoute(), $invoice->getDisplayName()) : null,
            'quote' => $invoice ? link_to($invoice->getRoute(), $invoice->getDisplayName()) : null,
            'contact' => $contactId ? link_to($client->getRoute(), $contact->getDisplayName()) : e($user->getDisplayName()),
            'payment' => $payment ? e($payment->transaction_reference) : null,
            'payment_amount' => $payment ? $account->formatMoney($payment->amount, $payment) : null,
            'adjustment' => $this->adjustment ? $account->formatMoney($this->adjustment, $this) : null,
            'credit' => $credit ? $account->formatMoney($credit->amount, $client) : null,
            'task' => $task ? link_to($task->getRoute(), substr($task->description, 0, 30).'...') : null,
            'expense' => $expense ? link_to($expense->getRoute(), substr($expense->public_notes, 0, 30).'...') : null,
        ];

        return trans("texts.activity_{$activityTypeId}", $data);
    }

    public function relatedEntityType()
    {
        switch ($this->activity_type_id) {
            case ACTIVITY_TYPE_CREATE_CLIENT:
            case ACTIVITY_TYPE_ARCHIVE_CLIENT:
            case ACTIVITY_TYPE_DELETE_CLIENT:
            case ACTIVITY_TYPE_RESTORE_CLIENT:
            case ACTIVITY_TYPE_CREATE_CREDIT:
            case ACTIVITY_TYPE_ARCHIVE_CREDIT:
            case ACTIVITY_TYPE_DELETE_CREDIT:
            case ACTIVITY_TYPE_RESTORE_CREDIT:
                return ENTITY_CLIENT;
                break;

            case ACTIVITY_TYPE_CREATE_INVOICE:
            case ACTIVITY_TYPE_UPDATE_INVOICE:
            case ACTIVITY_TYPE_EMAIL_INVOICE:
            case ACTIVITY_TYPE_VIEW_INVOICE:
            case ACTIVITY_TYPE_ARCHIVE_INVOICE:
            case ACTIVITY_TYPE_DELETE_INVOICE:
            case ACTIVITY_TYPE_RESTORE_INVOICE:
                return ENTITY_INVOICE;
                break;

            case ACTIVITY_TYPE_CREATE_PAYMENT:
            case ACTIVITY_TYPE_ARCHIVE_PAYMENT:
            case ACTIVITY_TYPE_DELETE_PAYMENT:
            case ACTIVITY_TYPE_RESTORE_PAYMENT:
            case ACTIVITY_TYPE_VOIDED_PAYMENT:
            case ACTIVITY_TYPE_REFUNDED_PAYMENT:
            case ACTIVITY_TYPE_FAILED_PAYMENT:
                return ENTITY_PAYMENT;
                break;

            case ACTIVITY_TYPE_CREATE_QUOTE:
            case ACTIVITY_TYPE_UPDATE_QUOTE:
            case ACTIVITY_TYPE_EMAIL_QUOTE:
            case ACTIVITY_TYPE_VIEW_QUOTE:
            case ACTIVITY_TYPE_ARCHIVE_QUOTE:
            case ACTIVITY_TYPE_DELETE_QUOTE:
            case ACTIVITY_TYPE_RESTORE_QUOTE:
            case ACTIVITY_TYPE_APPROVE_QUOTE:
                return ENTITY_QUOTE;
                break;

            case ACTIVITY_TYPE_CREATE_VENDOR:
            case ACTIVITY_TYPE_ARCHIVE_VENDOR:
            case ACTIVITY_TYPE_DELETE_VENDOR:
            case ACTIVITY_TYPE_RESTORE_VENDOR:
            case ACTIVITY_TYPE_CREATE_EXPENSE:
            case ACTIVITY_TYPE_ARCHIVE_EXPENSE:
            case ACTIVITY_TYPE_DELETE_EXPENSE:
            case ACTIVITY_TYPE_RESTORE_EXPENSE:
            case ACTIVITY_TYPE_UPDATE_EXPENSE:
                return ENTITY_EXPENSE;
                break;

            case ACTIVITY_TYPE_CREATE_TASK:
            case ACTIVITY_TYPE_UPDATE_TASK:
            case ACTIVITY_TYPE_ARCHIVE_TASK:
            case ACTIVITY_TYPE_DELETE_TASK:
            case ACTIVITY_TYPE_RESTORE_TASK:
                return ENTITY_TASK;
                break;
        }
    }
}
