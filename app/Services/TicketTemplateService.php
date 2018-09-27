<?php

namespace App\Services;

use App\Models\Ticket;
use App\Ninja\Datatables\TicketTemplateDatatable;
use App\Ninja\Repositories\TicketTemplateRepository;

class TicketTemplateService extends BaseService
{
    /**
     * @var TicketTemplateRepository
     */

    protected $ticketTemplateRepo;

    /**
     * @var DatatableService
     */

    protected $datatableService;

    /**
     * TicketTemplateService constructor.
     * @param TicketTemplateRepository $ticketTemplateRepository
     * @param DatatableService $datatableService
     */

    public function __construct(TicketTemplateRepository $ticketTemplateRepository, DatatableService $datatableService)
    {

        $this->datatableService = $datatableService;
        $this->ticketTemplateRepo = $ticketTemplateRepository;

    }

    /**
     * @return TicketTemplateRepository
     */

    protected function getRepo()
    {

        return $this->ticketTemplateRepo;

    }

    /**
     * @return \Illuminate\Http\JsonResponse
     */

    public function getDatatable()
    {

        $datatable = new TicketTemplateDatatable(false);

        $query = $this->ticketTemplateRepo->find();

            return $this->datatableService->createDatatable($datatable, $query);

    }

    public function processVariables($template, array $data)
    {

    }

    public static function getVariables(Ticket $ticket)
    {
        if ($ticket->agent)
        {

            $agent = $ticket->agent->getName();
            $signature = $ticket->agent->signature;

        }
        else
        {

            $agent = trans('texts.unassigned');
            $signature = '';

        }

        if($ticket->client)
        {

            $client = $ticket->client->getDisplayName();
            $contact = $ticket->getContactName();

        }
        else
        {

            $client = 'No client';
            $contact = 'No Contact';

        }

        return [
            '$ticket_number' => $ticket->ticket_number,
            '$ticket_status' => Ticket::getStatusNameById($ticket->status_id),
            '$client' => $client,
            '$contact' => $contact,
            '$priority' => $ticket->getPriorityName(),
            '$due_date' => $ticket->getDueDate(),
            '$agent' => $agent,
            '$status' => Ticket::getStatusNameById($ticket->status_id),
            '$subject' => $ticket->subject,
            '$description' => $ticket->getLastComment()->description ?? '',
            '$signature' => $signature,
        ];
    }
}
