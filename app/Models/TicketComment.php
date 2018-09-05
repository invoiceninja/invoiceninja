<?php

namespace App\Models;


use App\Libraries\Utils;

class TicketComment extends EntityModel
{
    protected $touches = ['ticket'];

    /**
     * @return mixed
     */
    public function getEntityType()
    {
        return ENTITY_TICKET_COMMENT;
    }

    /**
     * @return mixed
     */
    public function ticket()
    {
        return $this->belongsTo('App\Models\Ticket');
    }

    /*
     * @return string
     */
    public function agent()
    {
        return $this->hasOne('App\Models\User', 'id', 'agent_id');
    }

    public function user()
    {
        return $this->hasOne('App\Models\User', 'id', 'user_id');
    }

    /**
     * @return string
     */
    public function getCommentHeader()
    {
        if(filter_var($this->contact_key, FILTER_VALIDATE_EMAIL))
            return $this->ticket->contact_key. ' @ '. Utils::fromSqlDateTime($this->updated_at); // some kind of contact
        elseif($this->contact_key)
            return $this->ticket->getContactName(). ' @ ' . Utils::fromSqlDateTime($this->updated_at); //client replied
        elseif($this->agent_id)
            return $this->agent->getName(). ' @ ' . Utils::fromSqlDateTime($this->updated_at); //staff replied
        else
            return $this->user->getName(). ' @ ' . Utils::fromSqlDateTime($this->updated_at); //ticket master replied


    }
}
