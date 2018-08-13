<?php

namespace App\Http\Controllers;

use App\Events\TicketUserViewed;
use App\Http\Requests\CreateTicketRequest;
use App\Http\Requests\TicketInboundRequest;
use App\Http\Requests\TicketMergeRequest;
use App\Http\Requests\TicketRequest;
use App\Http\Requests\UpdateTicketRequest;
use App\Libraries\Utils;
use App\Models\Client;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\TicketStatus;
use App\Models\User;
use App\Ninja\Datatables\TicketDatatable;
use App\Services\TicketService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Session;

class TicketController extends BaseController
{

    /**
     * TicketController constructor.
     * @param TicketService $ticketService
     */
    public function __construct(TicketService $ticketService)
    {
        $this->ticketService = $ticketService;
    }

    /**
     * @return \Illuminate\Contracts\View\View
     */
    public function index()
    {
        return View::make('list_wrapper', [
            'entityType' => ENTITY_TICKET,
            'datatable' => new TicketDatatable(),
            'title' => trans('texts.tickets'),
        ]);
    }

    /**
     * @param null $clientPublicId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDatatable($clientPublicId = null)
    {
        $search = Input::get('sSearch');

        return $this->ticketService->getDatatable($search);
    }

    /**
     * @param $publicId
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function show($publicId)
    {
        Session::reflash();

        return redirect("tickets/$publicId/edit");
    }

    /**
     * @param TicketRequest $request
     * @return mixed
     */
    public function edit(TicketRequest $request)
    {
        $ticket = $request->entity();
        $clients = false;

        //If we are missing a client from the ticket, load clients for assignment
        if(!$ticket->client_id)
            $clients = $this->ticketService->findClientsByContactEmail($ticket->contact_key);

        $data = array_merge(self::getViewModel($ticket, $clients));

        event(new TicketUserViewed($ticket));

        return View::make('tickets.edit', $data);
    }

    /**
     * @param UpdateTicketRequest $request
     *
     * Updating a ticket can change the following:
     *
     * Priority
     * Status
     * Ticket closed
     * Ticket reopened
     * Comment updated (agent / client)
     * Due Date
     *
     * We need to pass a action variable so we can handle the appropriate workflow
     *
     */

    public function update(UpdateTicketRequest $request)
    {
        $data = $request->input();
        $data['document_ids'] = $request->document_ids;
        $ticket = $request->entity();


        $ticket = $this->ticketService->save($data, $ticket);
        $ticket->load('documents');

        $entityType = $ticket->getEntityType();

        $message = trans("texts.updated_{$entityType}");
        Session::flash('message', $message);

        $data = array_merge($this->getViewmodel($ticket), $data);

        return View::make('tickets.edit', $data);

    }

    /**
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function bulk()
    {
        $action = Input::get('action');
        $ids = Input::get('public_id') ? Input::get('public_id') : Input::get('ids');

        if ($action == 'purge' && ! auth()->user()->is_admin) {
            return redirect('dashboard')->withError(trans('texts.not_authorized'));
        }

        $count = $this->ticketService->bulk($ids, $action);

        $message = Utils::pluralize($action.'d_ticket', $count);
        Session::flash('message', $message);

        if ($action == 'purge') {
            return redirect('dashboard')->withMessage($message);
        } else {
            return $this->returnBulk(ENTITY_TICKET, $action, $ids);
        }
    }

    public function create(TicketRequest $request, $parentTicketId = 0)
    {

        $parentTicket = Ticket::scope($parentTicketId)->first();

        $data = [
            'users' => User::whereAccountId(Auth::user()->account_id)->get(),
            'is_internal' => $request->parent_ticket_id ? true : false,
            'parent_ticket' => $parentTicket ?: false,
            'url' => 'tickets/',
            'parent_tickets' => Ticket::scope()->where('status_id', '!=', 3)->whereNull('parent_ticket_id')->OrderBy('public_id', 'DESC')->get(),
            'method' => 'POST',
            'title' => trans('texts.new_internal_ticket'),
            'account' => Auth::user()->account->load('clients.contacts', 'users'),
            'timezone' => Auth::user()->account->timezone ? Auth::user()->account->timezone->name : DEFAULT_TIMEZONE,
            'datetimeFormat' => Auth::user()->account->getMomentDateTimeFormat(),
        ];

        return View::make('tickets.new_ticket', $data);
    }

    public function store(CreateTicketRequest $request)
    {

    }

    /**
     * @return array
     */
    private static function getViewModel($ticket = false, $clients = false)
    {

        return [
            'clients' => $clients,
            'status' => $ticket->status(),
            'comments' => $ticket->comments(),
            'account' => Auth::user()->account,
            'url' => 'tickets/' . $ticket->public_id,
            'ticket' => $ticket,
            'entity' => $ticket,
            'title' => trans('texts.edit_ticket'),
            'timezone' => Auth::user()->account->timezone ? Auth::user()->account->timezone->name : DEFAULT_TIMEZONE,
            'datetimeFormat' => Auth::user()->account->getMomentDateTimeFormat(),
            'method' => 'PUT',
        ];
    }

    /**
     * @param Request $request
     */
    public function inbound(TicketInboundRequest $request)
    {
        $ticket = $request->entity();

        if(!$ticket) {
            //spam
        }
        else {

        }

    }

    public function merge($publicId)
    {
        $ticket = Ticket::scope($publicId)->first();

        $data = [
            'mergeableTickets' => $ticket->getClientMergeableTickets(),
            'ticket' => $ticket,
            'account' => Auth::user()->account,
            'title' => trans('texts.ticket_merge'),
            'method' => 'POST',
            'url' => 'tickets/merge/',
            'entity' => $ticket,
        ];

        return View::make('tickets.merge', $data);
    }

    public function actionMerge(TicketMergeRequest $request)
    {
        $ticket = $request->entity();

        $this->ticketService->mergeTicket($ticket, $request->input());

        Session::reflash();
        return redirect("tickets/$request->updated_ticket_id/edit");
    }






}
