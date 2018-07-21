<?php

namespace App\Jobs\Ticket;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Auth;
use App;
use App\Models\Ticket;
use App\Ninja\Mailers\TicketMailer;
use App\Jobs\Job;
use Illuminate\Support\Facades\Request;

/**
 * Class TicketInboundNotification.
 */

class TicketInboundNotification extends Job implements ShouldQueue
{
    use InteractsWithQueue, SerializesModels;

    protected $request;

    protected $server;

    public function __construct(array $ticketData, Request $request)
    {
        $this->request = $request;
        $this->server = config('database.default');
    }

    public function handle()
    {
       //harvest ticket

        //update ticket

    }
}
