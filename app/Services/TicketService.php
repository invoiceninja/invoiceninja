<?php
namespace App\Services;

use App\Jobs\Ticket\TicketSendNotificationEmail;
use App\Libraries\Utils;
use App\Models\Client;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Ninja\Datatables\TicketDatatable;
use App\Ninja\Repositories\TicketRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use DataTable;

/**
 * Class ticketService.
 */
class TicketService extends BaseService
{

    /**
     * @var TicketRepository
     */

    protected $ticketRepo;

    /**
     * @var DatatableService
     */

    protected $datatableService;

    /**
     * CreditService constructor.
     *
     * @param ticketRepository $ticketRepo
     * @param DatatableService  $datatableService
     */

    public function __construct(TicketRepository $ticketRepo, DatatableService $datatableService)
    {
        $this->ticketRepo = $ticketRepo;
        $this->datatableService = $datatableService;
    }

    /**
     * @return TicketRepository
     */

    protected function getRepo()
    {
        return $this->ticketRepo;
    }

    /**
     * @param $data
     * @param mixed $ticket
     *
     * @return mixed|null
     */

    public function save($data, $ticket = false)
    {
        $ticket = $this->ticketRepo->save($data, $ticket);

        return $ticket;
    }

    /**
     * @param $search
     * @return \Illuminate\Http\JsonResponse
     */

    public function getDatatable($search)
    {
        $datatable = new TicketDatatable();

        $query = $this->ticketRepo->find($search);

        return $this->datatableService->createDatatable($datatable, $query);
    }

    public function getClientDatatable($clientId)
    {
        $query = DB::table('tickets')
            ->leftjoin('ticket_statuses', 'tickets.status_id', '=', 'ticket_statuses.id')
            ->where('tickets.client_id', '=', $clientId)
            ->where('tickets.is_deleted', '=', false)
            ->where('tickets.is_internal', '=', false)
            ->select(
                'tickets.description',
                'tickets.public_id',
                'tickets.subject',
                'tickets.ticket_number',
                'tickets.created_at',
                'tickets.updated_at',
                'tickets.deleted_at',
                'tickets.is_deleted',
                'ticket_statuses.name as ticketStatus',
                'tickets.merged_parent_ticket_id'
            );

        $table = \Datatable::query($query)
            ->addColumn('ticket_number', function ($model) {
                return link_to('/client/tickets/'.$model->public_id, $model->ticket_number)->toHtml();
            })
            ->addColumn('subject', function ($model) {
                return $model->subject;
            })
            ->addColumn('created_at', function ($model) {
                return Utils::fromSqlDateTime($model->created_at);
            })
            ->addColumn('status', function ($model) {
                return $model->merged_parent_ticket_id ? trans('texts.merged') : $model->ticketStatus;
            });

        return $table->make();
    }

    /**
     * @param $data
     * @param $ticket
     */

    private function processTicket($data, $ticket)
    {
        /* If comment added to ticket fire notifications */

        if(strlen($data['comment']) >= 1)
            $this->dispatch(new TicketSendNotificationEmail($data, $ticket));

    }

    public function mergeTicket(Ticket $ticket, $data) {

        //Close ticket
        $data['merged_parent_ticket_id'] = Ticket::getPrivateId($data['updated_ticket_id']);
        $data['closed'] = \Carbon::now();

            $ticketComment = TicketComment::createNew($ticket);
            $ticketComment->description = $data['old_ticket_comment'];
        
        $ticket->comments()->save($ticketComment);
        $this->save($data, $ticket);

        //Update parent ticket
        $updatedTicket = Ticket::scope($data['updated_ticket_id'])->first();

        $updatedTicketComment = TicketComment::createNew($updatedTicket);
        $updatedTicketComment->description = $data['updated_ticket_comment'];

        $updatedTicket->comments()->save($updatedTicketComment);

        $updatedTicket->save();

    }

    public function findClientsByContactEmail($email){

        $clients = Client::scope()->with('contacts')->whereHas('contacts', function ($query) use($email){
            $query->where('contacts.email', '=', $email);
        });

        if (!Auth::user()->hasPermission('view_client'))
            $clients = $clients->where('clients.user_id', '=', Auth::user()->id);

        return $clients->get();
    }

}