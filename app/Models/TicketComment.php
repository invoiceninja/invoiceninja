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
        return $this->hasOne('App\Models\User', 'id', 'user_id');
    }

    /**
     * @return string
     */
    public function getCommentHeader()
    {
        if($this->contact_key)
            return $this->ticket->getContactName(). ' @ ' . Utils::fromSqlDateTime($this->updated_at); //client replied
        else
            return $this->agent->getName(). ' @ ' . Utils::fromSqlDateTime($this->updated_at); //staff replied

    }
}
