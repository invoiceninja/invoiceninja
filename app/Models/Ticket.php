<?php

namespace App\Models;

use App\Constants\Domain;
use App\Libraries\Utils;
use App\Services\TicketTemplateService;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laracasts\Presenter\PresentableTrait;

/**
 * Class Ticket
 * @package App\Models
 */
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
        'user_id',
    ];


    /**
     * @return array
     */
    public static function relationEntities()
    {
        return [
            'invoice' => trans('texts.invoice'),
            'quote' => trans('texts.quote'),
            'payment' => trans('texts.payment'),
            'credit' => trans('texts.credit'),
            'expense' => trans('texts.expense'),
            'task' => trans('texts.task'),
            'project' => trans('texts.project'),
        ];
    }

    /**
     * @return array
     */
    public static function clientRelationEntities()
    {
        return [
            'invoice' => trans('texts.invoice'),
            'quote' => trans('texts.quote'),
            'payment' => trans('texts.payment'),
        ];
    }

    /**
     * Used for ticket autocomplete
     *
     * @return string
     */
    public static function templateVariables()
    {

        $arr[]['description'] ='$ticketNumber';
        $arr[]['description'] ='$ticketStatus';
        $arr[]['description'] = '$client';
        $arr[]['description'] = '$contact';
        $arr[]['description'] = '$priority';
        $arr[]['description'] = '$dueDate';
        $arr[]['description'] = '$agent';
        $arr[]['description'] = '$status';
        $arr[]['description'] = '$subject';
        $arr[]['description'] = '$description';
        $arr[]['description'] = '$signature';


        return json_encode($arr);
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
    public function parent_ticket()
    {
        return $this->belongsTo(static::class, 'parent_ticket_id');
    }

    /**
     * @return mixed
     */
    public function child_tickets()
    {
        return $this->hasMany(static::class, 'parent_ticket_id');
    }

    /**
     * @return mixed
     */
    public function merged_ticket_parent()
    {
        return $this->belongsTo(static::class, 'merged_parent_ticket_id');
    }

    /**
     * @return mixed
     */
    public function merged_children()
    {
        return $this->hasMany(static::class, 'merged_parent_ticket_id');
    }

    /**
     * @return mixed
     */
    public function relations()
    {
        return $this->hasMany('App\Models\TicketRelation');
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
            case TICKET_PRIORITY_MEDIUM:
                return trans('texts.medium');
            case TICKET_PRIORITY_HIGH:
                return trans('texts.high');
        }
    }

    /**
     * @return array|\Illuminate\Contracts\Translation\Translator|null|string
     */
    public function getStatus()
    {
        switch($this->status_id)
        {
            case TICKET_STATUS_NEW:
                return trans('texts.new');
            case TICKET_STATUS_OPEN:
                return trans('texts.open');
            case TICKET_STATUS_CLOSED:
                return trans('texts.closed');
            case TICKET_STATUS_MERGED:
                return trans('texts.merged');
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

    /**
     * @return \DateTime|string
     */
    public function getMinDueDate()
    {
        return Utils::fromSqlDateTime($this->created_at);
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


    /**
     * @return mixed
     */
    public function getStatusName()
    {
        return $this->getStatus();
    }

    /**
     * @param bool $entityType
     * @return array
     */
    public static function getStatuses($entityType = false)
    {
        return [
            TICKET_STATUS_NEW => trans('texts.new'),
            TICKET_STATUS_OPEN => trans('texts.open'),
            TICKET_STATUS_CLOSED => trans('texts.closed'),
            TICKET_STATUS_MERGED => trans('texts.merged'),
        ];

    }

    /**
     * @param $statusId
     * @return array|\Illuminate\Contracts\Translation\Translator|null|string
     */
    public static function getStatusNameById($statusId)
    {
        switch($statusId)
        {
            case TICKET_STATUS_NEW:
                return trans('texts.new');
            case TICKET_STATUS_OPEN:
                return trans('texts.open');
            case TICKET_STATUS_CLOSED:
                return trans('texts.closed');
            case TICKET_STATUS_MERGED:
                return trans('texts.merged');
        }
    }

    /**
     * @return mixed
     */
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

    /**
     * @return mixed
     */
    public function getTicketFromName()
    {
        return config('ninja.tickets.ticket_support_email_name');
    }

    /**
     * @return mixed
     */
    public function getTicketFromEmail()
    {
        return config('ninja.tickets.ticket_support_email');
    }

    /**
     * @param $accountId
     * @return int|mixed
     */
    public static function getNextTicketNumber($accountId)
    {

        $ticket = Ticket::whereAccountId($accountId)->withTrashed()->orderBy('id', 'DESC')->first();


        if ($ticket)
            return str_pad($ticket->account->account_ticket_settings->ticket_number_start, $ticket->account->invoice_number_padding, '0', STR_PAD_LEFT);
        else
            return str_pad(1, $ticket->account->invoice_number_padding, '0', STR_PAD_LEFT);

    }

    /**
     * @param $templateId
     * @return mixed
     */
    public function getTicketTemplate($templateId)
    {
        return TicketTemplate::where('id', '=', $templateId)->first();
    }

    /**
     * @return string
     */
    public function getTicketEmailFormat()
    {
        if(!Utils::isNinjaProd())
            $domain = config('ninja.tickets.ticket_support_domain');
        else
            $domain = Domain::getSupportDomainFromId($this->account->domain_id);

        if($this->is_internal == true)
            return $this->account->account_ticket_settings->support_email_local_part.'+'.$this->ticket_number.'@'.$domain;
        else
            return $this->ticket_number.'+'.$this->getContactTicketHash().'@'.$domain;
    }

    /**
     * @return mixed
     */
    public function getContactTicketHash()
    {
        $ticketInvitation = TicketInvitation::whereTicketId($this->id)->whereContactId($this->contact->id)->first();
        return $ticketInvitation->ticket_hash;
    }

    /**
     * @return mixed
     */
    public function getClientMergeableTickets()
    {
        $getInternal = false;

        if($this->is_internal == true)
            $getInternal = true;


        return Ticket::scope()
            ->where('client_id', '=', $this->client_id)
            ->where('public_id', '!=', $this->public_id)
            ->where('merged_parent_ticket_id', '=', NULL)
            ->where('status_id', '!=', 3)
            ->where('is_internal', '=', $getInternal)
            ->get();
    }

    /**
     * @return bool
     */
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

    /**
     * @return mixed
     */
    public function getLastComment()
    {
        return $this->comments()->first();
    }

    public static function buildTicketBody(Ticket $ticket, string $response) : string
    {

        $ticketVariables = TicketTemplateService::getVariables($ticket);

        return str_replace(array_keys($ticketVariables), array_values($ticketVariables), $response);

    }

}



Ticket::creating(
/**
 * @param $ticket
 */
    function ($ticket) {
    });

Ticket::created(
/**
 * @param $ticket
 */
    function ($ticket) {
        $account_ticket_settings = $ticket->account->account_ticket_settings;
        $account_ticket_settings->increment('ticket_number_start', 1);
        $account_ticket_settings->save();
    });

Ticket::updating(
/**
 * @param $ticket
 */
    function ($ticket) {
    });

Ticket::updated(
/**
 * @param $ticket
 */
    function ($ticket) {
    });

Expense::deleting(
/**
 * @param $ticket
 */
    function ($ticket) {
    });