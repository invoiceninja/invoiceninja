<?php
namespace App\Services;

use App\Libraries\Utils;
use App\Models\Client;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\TicketRelation;
use App\Ninja\Datatables\TicketDatatable;
use App\Ninja\Repositories\TicketRepository;
use Chumper\Datatable\Datatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
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

    protected function getRepo() : TicketRepository
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
            ->where('tickets.client_id', '=', $clientId)
            ->where('tickets.is_deleted', '=', false)
            ->where('tickets.is_internal', '=', false)
            ->where('tickets.deleted_at', '=', null)
            ->select(
                'tickets.description',
                'tickets.public_id',
                'tickets.subject',
                'tickets.ticket_number',
                'tickets.created_at',
                'tickets.updated_at',
                'tickets.deleted_at',
                'tickets.is_deleted',
                'tickets.status_id',
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
                return Ticket::getStatusNameById($model->status_id);
            });

        return $table->make();
    }


    public function mergeTicket(Ticket $ticket, $data)
    {

        //Close ticket
        $data['merged_parent_ticket_id'] = Ticket::getPrivateId($data['updated_ticket_id']);
        $data['closed'] = \Carbon::now();
        $data['status_id'] = TICKET_STATUS_MERGED;

        $ticketComment = TicketComment::createNew($ticket);
        $ticketComment->description = $data['old_ticket_comment'];
        $ticket->comments()->save($ticketComment);

        $data['action'] = TICKET_MERGE;
        $this->ticketRepo->save($data, $ticket);

        //Update parent ticket

        $updatedTicket = Ticket::scope($data['updated_ticket_id'])->first();
        $updatedTicketComment = TicketComment::createNew($updatedTicket);
        $updatedTicketComment->description = $data['updated_ticket_comment'];
        $updatedTicket->comments()->save($updatedTicketComment);

        $data = [];
        $data['action'] = TICKET_SAVE_ONLY;
        $this->ticketRepo->save($data, $updatedTicket);

    }

    public function findClientsByContactEmail($email){

        $clients = Client::scope()->with('contacts')->whereHas('contacts', function ($query) use($email){
            $query->where('contacts.email', '=', $email);
        });

        if (!Auth::user()->hasPermission('view_client'))
            $clients = $clients->where('clients.user_id', '=', Auth::user()->id);

        return $clients->get();
    }

    /**
     * Returns a filtered collection of entities that can be attached
     * as linked objects to a ticket
     *
     * @param Request $request
     * @return mixed
     */
    public function getRelationCollection(Request $request)
    {

        $excludeIds = TicketRelation::where('ticket_id', '=', $request->ticket_id)
            ->where('entity', '=', $request->entity)
            ->pluck('entity_id')
            ->all();

        $isQuote = false;

        $entity = $request->entity;
        $client_public_id = null;

        if($entity == 'quote'){
            $entity = 'invoice';
            $isQuote = true;
        }

        $className = '\App\Models\\'.ucfirst($entity);
        $entityModel = new $className();

        $query = $entityModel::scope($client_public_id, $request->account_id);

        if($entity == 'invoice' && $isQuote)
            $query->where('invoice_type_id', '=', INVOICE_TYPE_QUOTE);
        else if($entity == 'invoice' && !$isQuote)
            $query->where('invoice_type_id', '=', INVOICE_TYPE_STANDARD);

        if($client_public_id > 0) {
            $clientPrivateId = Client::getPortalPrivateId($client_public_id, $request->account_id);
            $query->where('client_id', '=', $clientPrivateId);
        }

        if(count($excludeIds) > 0)
            $query->whereNotIn('id', array_values($excludeIds));

        return $query->orderBy('id', 'desc')->get();
    }

}