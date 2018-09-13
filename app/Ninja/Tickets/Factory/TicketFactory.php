<?php

namespace App\Ninja\Tickets\Factory;

use App\Models\Ticket;
use App\Ninja\Tickets\Actions\TicketClientNew;
use App\Ninja\Tickets\Actions\TicketInboundNew;
use Illuminate\Support\Facades\Log;

/**
 * Class TicketFactory
 * @package App\Ninja\Tickets\Factory
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
    protected $action;

    /**
     * TicketFactory constructor.
     */
    public function __construct($originalTicket, $changedAttributes, Ticket $updatedTicket, $action)
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

        $handler = new $classEntity();
        $handler->fire($this->updatedTicket);
    }


}