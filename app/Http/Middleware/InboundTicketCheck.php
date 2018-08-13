<?php

namespace App\Http\Middleware;

use App\Models\LookupAccount;
use App\Models\LookupTicketInvitation;
use App\Ninja\Tickets\Inbound\InboundTicketFactory;
use Closure;
use Illuminate\Http\Request;



class InboundTicketCheck
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {

        if (! config('multi_db_enabled'))
            return $next($request);


        $inbound = new InboundTicketFactory($request->input());

        if($inbound->mailboxHash()){
            //check if we can find the ticket_hash
            LookupTicketInvitation::setServerByField('ticket_hash', $inbound->mailboxHash());

        }

        if($inbound->to()) {

            $parts = explode("@", $inbound->to());

            LookupAccount::setServerByField('support_email_local_part', $parts[0]);
        }

        return $next($request);
    }
}
