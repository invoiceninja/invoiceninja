<?php

namespace App\Ninja\Tickets\Factory;

use App\Models\Ticket;
use App\Ninja\Tickets\Actions\TicketClientNew;
use App\Ninja\Tickets\Actions\TicketInboundNew;
use Illuminate\Support\Facades\Log;

/**
 * Class DeltaFactory
 * @package App\Ninja\Tickets\Deltas
 */
class TicketFactory
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

        $classEntity = "App\Ninja\Tickets\Actions\\" . ucfirst(camel_case($this->action));

        $handler = new $classEntity($this->updatedTicket);
        $handler->fire();
    }


    private function performSequenceFor($action)
    {




        return;
        /**
        switch ($action)
        {

        case TICKET_CLIENT_NEW:
        case INBOUND_CONTACT_REPLY:
        case TICKET_INBOUND_NEW:
        case TICKET_AGENT_NEW:
        case INBOUND_AGENT_REPLY:
        case INBOUND_ADMIN_REPLY:

            break;

            case TICKET_INBOUND_REPLY:
            case TICKET_CLIENT_UPDATE:
            case TICKET_AGENT_UPDATE:
            case TICKET_MERGE:
            case TICKET_ASSIGNED:
            case TICKET_OVERDUE:

        }
        */
    }

}