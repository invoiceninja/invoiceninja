<?php

namespace App\Models;

use App\Constants\Domain;
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
        'merged_parent_ticket_id',
        'parent_ticket_id',
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

    public function parent_ticket()
    {
        return $this->belongsTo(static::class, 'parent_ticket_id');
    }

    public function child_tickets()
    {
        return $this->hasMany(static::class, 'parent_ticket_id');
    }

    public function merged_ticket_parent()
    {
        return $this->belongsTo(static::class, 'merged_parent_ticket_id');
    }

    public function merged_children()
    {
        return $this->hasMany(static::class, 'merged_parent_ticket_id');
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
        if (! $this->due_date || $this->due_date == '0000-00-00 00:00:00')
            return trans('texts.no_due_date');
        else
            return Utils::fromSqlDateTime($this->due_date);

    }

    public function getMinDueDate()
    {
        return Utils::fromSqlDateTime($this->created_at);
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
    public static function getPriorityArray()
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
    public function agentName()
    {
        if($this->agent && $this->agent->getName())
            return $this->agent->getName();
        else
            return trans('texts.unassigned');
    }


    public function getStatusName()
    {
        if($this->merged_parent_ticket_id)
            return trans('texts.merged');
        else
            return $this->status->name;

    }

    public function agent()
    {
        return $this->hasOne('App\Models\User', 'id', 'agent_id');
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
        return config('ninja.tickets.ticket_support_email_name');
    }

    public function getTicketFromEmail()
    {
        return config('ninja.tickets.ticket_support_email');
    }

    public static function getNextTicketNumber($accountId)
    {

        $ticket = Ticket::whereAccountId($accountId)->withTrashed()->orderBy('ticket_number', 'DESC')->first();

        if ($ticket) 
            return max($ticket->ticket_number + 1, $ticket->account->account_ticket_settings->ticket_number_start);
        else
            return 1;

    }

    public function getTicketTemplate($templateId)
    {
        return TicketTemplate::where('id', '=', $templateId)->first();
    }

    public function getTicketEmailFormat()
    {
        if(!Utils::isNinjaProd())
            $domain = config('ninja.tickets.ticket_support_domain');
        else
            $domain = Domain::getSupportDomainFromId($this->account->domain_id);

        return $this->ticket_number.'+'.$this->getContactTicketHash().'@'.$domain;
    }

    public function getContactTicketHash()
    {
        $ticketInvitation = TicketInvitation::whereTicketId($this->id)->whereContactId($this->contact->id)->first();
        return $ticketInvitation->ticket_hash;
    }

    public function getClientMergeableTickets()
    {
        return Ticket::scope()
            ->where('client_id', '=', $this->client_id)
            ->where('public_id', '!=', $this->public_id)
            ->where('merged_parent_ticket_id', '=', NULL)
            ->where('status_id', '!=', 3)
            ->get();
    }

    public function isMergeAble()
    {
        if($this->status_id == 3)
            return false;
        elseif($this->is_deleted)
            return false;
        elseif($this->merged_parent_ticket_id != null)
            return false;
        else
            return true;
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
