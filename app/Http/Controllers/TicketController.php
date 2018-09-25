<?php

namespace App\Http\Controllers;

use App\Events\TicketUserViewed;
use App\Http\Requests\CreateTicketRequest;
use App\Http\Requests\TicketAddEntityRequest;
use App\Http\Requests\TicketInboundRequest;
use App\Http\Requests\TicketMergeRequest;
use App\Http\Requests\TicketRemoveEntityRequest;
use App\Http\Requests\TicketRequest;
use App\Http\Requests\UpdateTicketRequest;
use App\Libraries\Utils;
use App\Models\Client;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\TicketRelation;
use App\Models\User;
use App\Ninja\Datatables\TicketDatatable;
use App\Ninja\Repositories\TicketRepository;
use App\Services\TicketService;
use Barryvdh\LaravelIdeHelper\Eloquent;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Session;
use DB;

/**
 * Class TicketController
 * @package App\Http\Controllers
 */
class TicketController extends BaseController
{

    /**
     * @var TicketService
     */
    protected $ticketService;

    /**
     * @var
     */
    protected $ticketRepository;

    /**
     * TicketController constructor.
     * @param TicketService $ticketService
     */
    public function __construct(TicketService $ticketService, TicketRepository $ticketRepository)
    {

        $this->ticketService = $ticketService;
        $this->ticketRepo = $ticketRepository;

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
     * @return Redirect
     */
    public function show($publicId)
    {

        Session::reflash();

        return redirect("tickets/$publicId/edit");

    }

    /**
     * @param TicketRequest $request
     * @return View
     */
    public function edit(TicketRequest $request)
    {

        $ticket = $request->entity();

        $clients = false;

        //If we are missing a client from the ticket, load clients for assignment
        if($ticket->is_internal == TRUE && !$ticket->client_id)
            $clients = Client::scope()->with('contacts')->get();
        else if(!$ticket->client_id)
            $clients = $this->ticketService->findClientsByContactEmail($ticket->contact_key);

        $data = array_merge(self::getViewModel($ticket, $clients));

        event(new TicketUserViewed($ticket));

        return View::make('tickets.edit', $data);

    }

    /**
     * @param UpdateTicketRequest $request
     * @return View
     */

    public function update(UpdateTicketRequest $request)
    {

        $data = $request->input();
        $data['document_ids'] = $request->document_ids;

        if($data['closed'] != '0000-00-00 00:00:00')
            $data['action'] = TICKET_AGENT_CLOSED;
        elseif(isset($data['description']) && strlen($data['description']) > 0)
            $data['action'] = TICKET_AGENT_UPDATE;
        else
            $data['action'] = TICKET_SAVE_ONLY;

        $ticket = $request->entity();
        $ticket = $this->ticketService->save($data, $ticket);
        $ticket->load('documents', 'relations');

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

        if ($action == 'purge' && ! auth()->user()->is_admin)
            return redirect('dashboard')->withError(trans('texts.not_authorized'));

        $count = $this->ticketService->bulk($ids, $action);

        $message = Utils::pluralize($action.'d_ticket', $count);

        Session::flash('message', $message);

        if ($action == 'purge')
            return redirect('dashboard')->withMessage($message);
        else
            return $this->returnBulk(ENTITY_TICKET, $action, $ids);

    }

    /**
     * @param TicketRequest $request
     * @param int $parentTicketId
     * @return View
     */
    public function create(TicketRequest $request, $parentTicketId = 0)
    {

        $parentTicket = Ticket::scope($parentTicketId)->first();

        $parentTicketClientExists = false;

        if ($parentTicket && method_exists($parentTicket, 'client')) {
            $parentTicket->load('client');
            $parentTicketClientExists = true;
        }

        //need to mock a ticket object or check if $request->old() exists and pass that in its place.
        $mockTicket = [
            'parent_ticket_id' => $parentTicketId ? $parentTicketId : null,
            'subject' => '',
            'description' => '',
            'due_date' => '',
            'client_public_id' => $parentTicketClientExists ? $parentTicket->client->public_id : null,
            'agent_id' => null,
            'is_internal' => $parentTicketClientExists ? true : false,
            'private_notes' => '',
            'priority_id' =>1,
        ];

        $data = [
            'users' => User::whereAccountId(Auth::user()->account_id)->get(),
            'is_internal' => $request->parent_ticket_id ? true : false,
            'parent_ticket' => $parentTicket ?: false,
            'url' => 'tickets/',
            'parent_tickets' => Ticket::scope()->where('status_id', '!=', 3)->whereNull('merged_parent_ticket_id')->OrderBy('public_id', 'DESC')->get(),
            'method' => 'POST',
            'title' => trans('texts.new_ticket'),
            'account' => Auth::user()->account->load('clients.contacts', 'users'),
            'timezone' => Auth::user()->account->timezone ? Auth::user()->account->timezone->name : DEFAULT_TIMEZONE,
            'datetimeFormat' => Auth::user()->account->getMomentDateTimeFormat(),
            'old' => $request->old() ? $request->old() : $mockTicket,
            'clients' => Client::scope()->with('contacts')->get(),
        ];

        return View::make('tickets.new_ticket', $data);
    }

    /**
     * @param CreateTicketRequest $request
     * @return Redirect
     */
    public function store(CreateTicketRequest $request)
    {
        $input = $request->input();
        $input['action'] = TICKET_AGENT_NEW;

        $ticket = $this->ticketService->save($input, $request->entity());

        return redirect("tickets/$ticket->public_id/edit");

    }

    /**
     * @return array
     */
    private static function getViewModel($ticket = false, $clients = false)
    {

        return [
            'clients' => $clients,
            //'status' => $ticket->status(),
            'comments' => $ticket->comments(),
            'account' => Auth::user()->account,
            'url' => 'tickets/' . $ticket->public_id,
            'ticket' => $ticket,
            'entity' => $ticket,
            'title' => trans('texts.edit_ticket'),
            'timezone' => Auth::user()->account->timezone ? Auth::user()->account->timezone->name : DEFAULT_TIMEZONE,
            'datetimeFormat' => Auth::user()->account->getMomentDateTimeFormat(),
            'method' => 'PUT',
            'isAdminUser' => Auth::user()->is_admin || Auth::user()->isTicketMaster() ? true : false,
        ];

    }

    /**
     * @param Request $request
     */
    public function inbound(TicketInboundRequest $request) : void
    {

        $ticket = $request->entity();

        if(!$ticket)
            Log::error('no ticket found - ? spam or new request?');
        else
            Log::error('ticket #'. $ticket->ticket_number .' found');


    }

    /**
     * @param $publicId
     * @return View
     */
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

    /**
     * @param TicketMergeRequest $request
     * @return Redirect
     */
    public function actionMerge(TicketMergeRequest $request)
    {

        $ticket = $request->entity();
        $this->ticketService->mergeTicket($ticket, $request->input());

        Session::reflash();

        return redirect("tickets/$request->updated_ticket_id/edit");

    }

    /**
     * @return Collection
     */
    public function getTicketRelationCollection(\Illuminate\Http\Request $request)
    {

        return $this->ticketService->getRelationCollection($request);

    }

    /**
     * Add ticket relation entity.
     * returns a formatted URL
     * @return string
     */
    public function addEntity(TicketAddEntityRequest $request)
    {

        return $request->addEntity();

    }

    /**
     * Remove ticket
     * @return primary ID
     */
    public function removeEntity(TicketRemoveEntityRequest $request)
    {
        TicketRelation::destroy(request()->id);
            return request()->id;
    }

    public function search()
    {
        
        if(env(SCOUT_DRIVER) != null) {

            $result = TicketComment::search(request()->term)->where('agent_id', Auth::user()->id)->get()->pluck('description');
            return response()->json($result);
        }

    }



}
