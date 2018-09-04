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

        $classEntity = "App\Ninja\Tickets\Actions\\" . ucfirst(camel_case($this->action));

        $handler = new $classEntity($this->updatedTicket);
        $handler->fire();
    }


}