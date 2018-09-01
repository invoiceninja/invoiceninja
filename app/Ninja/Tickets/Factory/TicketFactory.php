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

        $this->performSequenceFor($this->action);

    }


    private function performSequenceFor($action)
    {
        switch ($action)
        {

            case TICKET_CLIENT_NEW:

                $handler = new TicketClientNew($this->updatedTicket);

                $handler->fire();

            break;

            case TICKET_INBOUND_NEW:

                $handler = new TicketInboundNew($this->updatedTicket);

                $handler->fire();

            break;

            case TICKET_INBOUND_REPLY:

            break;

            case TICKET_CLIENT_UPDATE:
                /* Check if new comment_template is configured and fire notification to agent*/
            break;

            case TICKET_AGENT_NEW:
                /* Check if new_ticket_template exists - fire notifications that apply*/
            break;

            case TICKET_AGENT_UPDATE:
                /* Check if updated_template exists - fire notifications that apply*/
            break;

            case TICKET_MERGE:
                /* Check if updated_template exists - fire notification that apply*/
            break;

            case TICKET_ASSIGNED:
                /* Check if ticket assignment template exists - fire notifications that apply */
            break;

            case TICKET_OVERDUE:
                /* Check if overdue template exists - fire notifications that apply */
            break;

        }

    }

}