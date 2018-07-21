<?php
namespace App\Services;
use App\Jobs\Ticket\TicketSendNotificationEmail;
use App\Libraries\Utils;
use App\Ninja\Datatables\TicketDatatable;
use App\Ninja\Repositories\TicketRepository;
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

        //$this->processTicket($data, $ticket); //todo after CRUD

            return $this->ticketRepo->save($data, $ticket);

    }

    /**
     * @param $search
     * @return \Illuminate\Http\JsonResponse
     */

    public function getDatatable($search)
    {
        // we don't support bulk edit and hide the client on the individual client page
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
                'ticket_statuses.name as ticketStatus'
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
                return $model->ticketStatus;
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

}