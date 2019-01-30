<?php

namespace App\Ninja\Repositories;

use App\Models\TicketTemplate;
use Auth;
use DB;
use Utils;

class TicketTemplateRepository extends BaseRepository
{

    /**
     * @return string
     */

    public function getClassName()
    {

        return 'App\Models\TicketTemplate';

    }

    /**
     * @return mixed
     */

    public function all()
    {

        return TicketTemplate::scope()->get();

    }

    /**
     * @param null $filter
     * @param bool $userId
     * @return mixed
     */

    public function find($filter = null, $userId = false)
    {

        $query = DB::table('ticket_templates')
            ->where('ticket_templates.account_id', '=', Auth::user()->account_id)
            ->select(
                'ticket_templates.name',
                'ticket_templates.public_id',
                'ticket_templates.description',
                'ticket_templates.deleted_at',
                'ticket_templates.created_at'
            );

        if ($userId)
            $query->where('ticket_templates.user_id', '=', $userId);

            return $query;

    }

    /**
     * @param $input
     * @param bool $ticketTemplate
     * @return bool|mixed
     */

    public function save($input, $ticketTemplate = false)
    {
        if (! $ticketTemplate)
            $ticketTemplate = TicketTemplate::createNew();


        $ticketTemplate->fill($input);

        $ticketTemplate->save();

            return $ticketTemplate;
    }

}
