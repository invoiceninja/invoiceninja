<?php

namespace App\Jobs\Ticket;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Auth;
use App;
use App\Jobs\Job;
use App\Models\Ticket;

/**
 * Class TicketDelta.
 *
 * Queues the processing of ticket delta's
 */

class TicketDelta extends Job implements ShouldQueue
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
     * TicketDelta constructor.
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
     *
     */
    public function handle()
    {

        $deltaHandler = new App\Ninja\Tickets\Deltas\DeltaFactory($this->originalTicket, $this->deltaAttributes, $this->updatedTicket, $this->action);

        $deltaHandler->process();

    }
}
