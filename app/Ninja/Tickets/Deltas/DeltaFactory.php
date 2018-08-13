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
     * DeltaFactory constructor.
     */
    public function __construct($originalTicket, $changedAttributes, $updatedTicket)
    {
        $this->originalTicket = $originalTicket;
        $this->changedAttributes = $changedAttributes;
        $this->updatedTicket = $updatedTicket;
    }

    /**
     * Public entry point
     */
    public function process()
    {

        if(!$this->originalTicket) {
            $this->performDeltaAction('new_ticket');
        }
        elseif(count($this->changedAttributes) > 0) {
            foreach ($this->changedAttributes as $key => $value)
                Log::error($key);
                Log::error($value);
                $this->performDeltaAction($key);
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