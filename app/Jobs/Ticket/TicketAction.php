<?php

namespace App\Jobs\Ticket;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Auth;
use App;
use App\Jobs\Job;
use App\Models\Ticket;
use Illuminate\Support\Facades\Log;

/**
 * Class TicketAction.
 *
 * Queues the processing of ticket actions's
 */

class TicketAction extends Job implements ShouldQueue
{
    use InteractsWithQueue, SerializesModels;

    /**
     * @var Ticket_attributes
     */
    protected $deltaAttributes;

    /**
     * @var Ticket
     */
    protected $originalTicket;

    /**
     * @var Ticket
     */
    protected $updatedTicket;

    /**
     * @var mixed
     */
    protected $server;

    /**
     * @var mixed
     */
    protected $action;
    /**
     * TicketAction constructor.
     * @param array $deltaAttributes
     * @param array $originalTicket
     * @param  $updatedTicket
     * @param  $action
     */
    public function __construct($deltaAttributes, $originalTicket, $updatedTicket, $action)
    {

        $this->deltaAttributes = $deltaAttributes;
        $this->originalTicket = $originalTicket;
        $this->updatedTicket = $updatedTicket;
        $this->server = config('database.default');
        $this->action = $action;

    }

    /**
     * process action
     */
    public function handle()
    {

        $ticketHandler = new App\Ninja\Tickets\Factory\TicketFactory($this->originalTicket, $this->deltaAttributes, $this->updatedTicket, $this->action);
        $ticketHandler->process();

    }
}