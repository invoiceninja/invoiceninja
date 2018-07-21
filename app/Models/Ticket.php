<?php

namespace App\Models;

use App\Libraries\Utils;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laracasts\Presenter\PresentableTrait;

class Ticket extends EntityModel
{
    use PresentableTrait;
    use SoftDeletes;

    /**
     * @var string
     */
    protected $presenter = 'App\Ninja\Presenters\TicketPresenter';

    /**
     * @var array
     */
    protected $dates = ['deleted_at'];

    /**
     * @var array
     */
    protected $fillable = [
        'client_id',
        'subject',
        'description',
        'private_notes',
        'due_date',
        'ccs',
        'priority_id',
        'agent_id',
        'category_id',
        'is_deleted',
        'is_internal',
        'status_id',
        'contact_key',
        'ticket_number',
        'reopened',
        'closed',
    ];

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
    public function client()
    {
        return $this->belongsTo('App\Models\Client')->withTrashed();
    }

    /**
     * @return mixed
     */
    public function category()
    {
        return $this->belongsTo('App\Models\TicketCategory');
    }

    /**
     * @return mixed
     */
    public function comments()
    {
        return $this->hasMany('App\Models\TicketComment')->orderBy('created_at', 'DESC');
    }

    /**
     * @return mixed
     */
    public function templates()
    {
        return $this->hasMany('App\Models\TicketTemplate');
    }

    /**
     * @return mixed
     */
    public function status()
    {
        return $this->belongsTo('App\Models\TicketStatus');
    }

    /**
     * @return mixed
     */
    public function documents()
    {
        return $this->hasMany('App\Models\Document')->orderBy('id');
    }

    /**
     * @return mixed
     */
    public function contact()
    {
        return $this->belongsTo('App\Models\Contact', 'contact_key', 'contact_key');
    }

    /**
     * @return mixed
     */
    public function invitations()
    {
        return $this->hasMany('App\Models\TicketInvitation')->orderBy('ticket_invitations.contact_id');
    }

    /**
     * @return mixed
     */
    public function getEntityType()
    {
        return ENTITY_TICKET;
    }

    /**
     * @return string
     */
    public function getRoute()
    {
        return "/tickets/{$this->public_id}";
    }

    /**
     *
     * @return string
     */
    public function getContactName()
    {
        $contact = Contact::withTrashed()->where('contact_key', '=', $this->contact_key)->first();
        if ($contact && ! $contact->is_deleted) {
            return $contact->getFullName();
        } else {
            return null;
        }
    }

    /**
     *
     * @return string
     */
    public function getPriorityName()
    {
        switch($this->priority_id)
        {
            case TICKET_PRIORITY_LOW:
                return trans('texts.low');
                break;
            case TICKET_PRIORITY_MEDIUM:
                return trans('texts.medium');
                break;
            case TICKET_PRIORITY_HIGH:
                return trans('texts.high');
                break;
        }
    }

    /**
     *
     * @return string
     */
    public function getDueDate()
    {
        if($this->due_date)
            return Utils::fromSqlDateTime($this->due_date);
        else
            return trans('texts.no_due_date');
    }

    /**
     * @return array
     *
     * Ticket status can be client specific,
     * need to return statuses per account.
     */
    public function getAccountStatusArray()
    {
        return TicketStatus::where('account_id', '=', $this->account->id)->get();
    }

    /**
     * @return array
     */
    public function getPriorityArray()
    {
        return [
            ['id'=>TICKET_PRIORITY_LOW, 'name'=> trans('texts.low')],
            ['id'=>TICKET_PRIORITY_MEDIUM, 'name'=> trans('texts.medium')],
            ['id'=>TICKET_PRIORITY_HIGH, 'name'=> trans('texts.high')],
        ];
    }

    /**
     * @return string
     */
    public function agent()
    {
        $user = User::where('id', '=', $this->agent_id)->first();
        if($user)
            return $user->getFullName();
        else
            return trans('texts.unassigned');
    }


    /**
     * @return string
     */

    public function getCCs()
    {
        $ccEmailArray = [];
        $ccs = json_decode($this->ccs, true);

        if(!is_array($ccs))
            return null;

        foreach($ccs as $contact_key) {
            $c = Contact::where('contact_key', '=', $contact_key)->first();
            array_push($ccEmailArray, strtolower($c->email));
        }

        return implode(", ", $ccEmailArray);
    }

    public function getTicketReplyTo()
    {

    }

    public function getTicketFromName()
    {
        return env("TICKET_SUPPORT_EMAIL_NAME","");
    }

    public function getTicketFromEmail()
    {
        return env("TICKET_SUPPORT_EMAIL","");
    }

    public static function getNextTicketNumber($accountId)
    {

        $ticket = Ticket::whereAccountId($accountId)->withTrashed()->orderBy('ticket_number', 'DESC')->first();

        if ($ticket) 
            return max($ticket->ticket_number + 1, $ticket->account->account_ticket_settings->ticket_number_start);
        else
            return 1;

    }

}



Ticket::creating(function ($ticket) {
});

Ticket::created(function ($ticket) {
    $account_ticket_settings = $ticket->account->account_ticket_settings;
    $account_ticket_settings->ticket_number_start = $ticket->ticket_number+1;
    $account_ticket_settings->save();
});

Ticket::updating(function ($ticket) {
});

Ticket::updated(function ($ticket) {
});

Expense::deleting(function ($ticket) {
});
