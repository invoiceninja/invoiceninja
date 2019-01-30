<?php

namespace App\Http\Requests;

use App\Ninja\Repositories\TicketRepository;
use App\Ninja\Tickets\Inbound\InboundTicketFactory;
use App\Ninja\Tickets\Inbound\InboundTicketService;
use Illuminate\Support\Facades\Log;

class TicketInboundRequest extends Request
{
    public function entity()
    {
        $inboundTicketService = new InboundTicketService(new InboundTicketFactory(request()->getContent()), new TicketRepository());
        return $inboundTicketService->process();

    }

    public function rules()
    {
        return [];
    }

    public function authorize()
    {
        return true;
    }
}
