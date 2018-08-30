<?php

namespace App\Ninja\Tickets\Deltas;

use App\Models\Ticket;
use Illuminate\Support\Facades\Log;

/**
 * Class DeltaFactory
 * @package App\Ninja\Tickets\Deltas
 */
class DeltaFactory
{

    /**
     * @var Ticket
     */
    protected $originalTicket;

    /**
     * @var Ticket
     */
    protected $updatedTicket;

    /**
     * @var array
     */
    protected $changedAttributes;

    /**
     * @var $action
     */

    /**
     * DeltaFactory constructor.
     */
    public function __construct($originalTicket, $changedAttributes, $updatedTicket, $action)
    {

        $this->originalTicket = $originalTicket;

        $this->changedAttributes = $changedAttributes;

        $this->updatedTicket = $updatedTicket;

        $this->action = $action;

    }

    /**
     * Public entry point
     */
    public function process()
    {
        /**
         * Need some thought here. the action will not necessary contain just one method to implement.
         * It may be a range of methods - probabaly best to split each action into its own sequence.
         */
        $this->performSequenceFor($this->action);
/*
        $this->performDeltaAction($this->action);
        //Log::error($this->updatedTicket);
        //Log::error($this->originalTicket);
        //Log::error($this->changedAttributes);

        if(!$this->originalTicket)
            $this->performDeltaAction('new_ticket');
        elseif(count($this->changedAttributes) > 0) {

            foreach ($this->changedAttributes as $key => $value)

                Log::error($key);

                Log::error($value);

                $this->performDeltaAction($key);

        }
*/
    }


    private function performSequenceFor($action)
    {
        switch ($action)
        {

            case TICKET_CLIENT_NEW:
                /* Check if a default agent to assign exists */

                /* Fire notification to agent / slack if configured */

                /* Fire notification to client if new_ticket_template exists */
            break;

            case TICKET_CLIENT_UPDATE:
                /* Check if new comment_template is configured and fire notification to agent*/
            break;

            case TICKET_AGENT_NEW:
                /* Check if new_ticket_template exists*/
            break;

            case TICKET_AGENT_UPDATE:
            break;

            case TICKET_MERGE:
            break;

        }

    }

    /**
     * @param $modelAttribute
     *
     * Possible actions.
     *
     * 1. New ticket (response to client)
     * 2. Updated ticket (to client)
     * 3. Assign ticket (to agent)
     * 4. Close ticket (to client)
     * 5. Ticket overdue (to agent)
     *
     */
    private function performDeltaAction($attribute)
    {

        switch($attribute)
        {
            case 'agent_id':
                AgentDelta::handle($this->updatedTicket, $this->originalTicket);
            break;

            case 'new_ticket':
                NewTicketDelta::handle($this->updatedTicket);
            break;

            case 'closed':

            break;

            case 'due_date':

            break;

            case 'priority_id':

            break;

        }

    }


}