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

    private function getVariables(Ticket $ticket)
    {
        return [
            '$ticket_number' => $ticket->ticket_number,
            '$ticket_status' => $ticket->status->name,
            '$client' => $ticket->client->getDisplayName(),
            '$contact' => $ticket->getContactName(),
            '$priority' => $ticket->getPriorityName(),
            '$due_date' => $ticket->getDueDate(),
            '$agent' => $ticket->agent(),
            '$status' => $ticket->status->name,
            '$subject' => $ticket->subject,
            '$description' => $ticket->description,
        ];
    }
}
