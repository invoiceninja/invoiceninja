<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClientRequest;
use App\Http\Requests\CreateClientRequest;
use App\Http\Requests\UpdateClientRequest;
use App\Jobs\LoadPostmarkHistory;
use App\Jobs\ReactivatePostmarkEmail;
use App\Jobs\Client\GenerateStatementData;
use App\Models\Account;
use App\Models\Client;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\Expense;
use App\Models\Task;
use App\Ninja\Datatables\ClientDatatable;
use App\Ninja\Repositories\ClientRepository;
use App\Services\ClientService;
use Auth;
use Cache;
use Input;
use Redirect;
use Session;
use URL;
use Utils;
use View;

class ClientController extends BaseController
{
    protected $clientService;
    protected $clientRepo;
    protected $entityType = ENTITY_CLIENT;

    public function __construct(ClientRepository $clientRepo, ClientService $clientService)
    {
        //parent::__construct();

        $this->clientRepo = $clientRepo;
        $this->clientService = $clientService;
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        return View::make('list_wrapper', [
            'entityType' => ENTITY_CLIENT,
            'datatable' => new ClientDatatable(),
            'title' => trans('texts.clients'),
            'statuses' => Client::getStatuses(),
        ]);
    }

    public function getDatatable()
    {
        $search = Input::get('sSearch');
        $userId = Auth::user()->filterIdByEntity(ENTITY_CLIENT);

        return $this->clientService->getDatatable($search, $userId);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return Response
     */
    public function store(CreateClientRequest $request)
    {
        $client = $this->clientService->save($request->input());

        Session::flash('message', trans('texts.created_client'));

        return redirect()->to($client->getRoute());
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     *
     * @return Response
     */
    public function show(ClientRequest $request)
    {

        $client = $request->entity();
        $user = Auth::user();
        $account = $user->account;

        //$user->can('view', [ENTITY_CLIENT, $client]);

        $actionLinks = [];
        if ($user->can('create', ENTITY_INVOICE)) {
            $actionLinks[] = ['label' => trans('texts.new_invoice'), 'url' => URL::to('/invoices/create/'.$client->public_id)];
        }
        if ($user->can('create', ENTITY_TASK)) {
            $actionLinks[] = ['label' => trans('texts.new_task'), 'url' => URL::to('/tasks/create/'.$client->public_id)];
        }
        if (Utils::hasFeature(FEATURE_QUOTES) && $user->can('create', ENTITY_QUOTE)) {
            $actionLinks[] = ['label' => trans('texts.new_quote'), 'url' => URL::to('/quotes/create/'.$client->public_id)];
        }
        if ($user->can('create', ENTITY_RECURRING_INVOICE)) {
            $actionLinks[] = ['label' => trans('texts.new_recurring_invoice'), 'url' => URL::to('/recurring_invoices/create/'.$client->public_id)];
        }

        if (! empty($actionLinks)) {
            $actionLinks[] = \DropdownButton::DIVIDER;
        }

        if ($user->can('create', ENTITY_PAYMENT)) {
            $actionLinks[] = ['label' => trans('texts.enter_payment'), 'url' => URL::to('/payments/create/'.$client->public_id)];
        }

        if ($user->can('create', ENTITY_CREDIT)) {
            $actionLinks[] = ['label' => trans('texts.enter_credit'), 'url' => URL::to('/credits/create/'.$client->public_id)];
        }

        if ($user->can('create', ENTITY_EXPENSE)) {
            $actionLinks[] = ['label' => trans('texts.enter_expense'), 'url' => URL::to('/expenses/create/'.$client->public_id)];
        }

        $token = $client->getGatewayToken();

        $data = [
            'account' => $account,
            'actionLinks' => $actionLinks,
            'showBreadcrumbs' => false,
            'client' => $client,
            'credit' => $client->getTotalCredit(),
            'title' => trans('texts.view_client'),
            'hasRecurringInvoices' => $account->isModuleEnabled(ENTITY_RECURRING_INVOICE) && Invoice::scope()->recurring()->withArchived()->whereClientId($client->id)->count() > 0,
            'hasQuotes' => $account->isModuleEnabled(ENTITY_QUOTE) && Invoice::scope()->quotes()->withArchived()->whereClientId($client->id)->count() > 0,
            'hasTasks' => $account->isModuleEnabled(ENTITY_TASK) && Task::scope()->withArchived()->whereClientId($client->id)->count() > 0,
            'hasExpenses' => $account->isModuleEnabled(ENTITY_EXPENSE) && Expense::scope()->withArchived()->whereClientId($client->id)->count() > 0,
            'gatewayLink' => $token ? $token->gatewayLink() : false,
            'gatewayName' => $token ? $token->gatewayName() : false,
        ];

        return View::make('clients.show', $data);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create(ClientRequest $request)
    {
        //Auth::user()->can('create', ENTITY_CLIENT);

        if (Client::scope()->withTrashed()->count() > Auth::user()->getMaxNumClients()) {
            return View::make('error', ['hideHeader' => true, 'error' => "Sorry, you've exceeded the limit of ".Auth::user()->getMaxNumClients().' clients']);
        }

        $data = [
            'client' => null,
            'method' => 'POST',
            'url' => 'clients',
            'title' => trans('texts.new_client'),
        ];

        $data = array_merge($data, self::getViewModel());

        return View::make('clients.edit', $data);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param int $id
     *
     * @return Response
     */
    public function edit(ClientRequest $request)
    {

        $client = $request->entity();

        $data = [
            'client' => $client,
            'method' => 'PUT',
            'url' => 'clients/'.$client->public_id,
            'title' => trans('texts.edit_client'),
        ];

        $data = array_merge($data, self::getViewModel());

        if (Auth::user()->account->isNinjaAccount()) {
            if ($account = Account::whereId($client->public_id)->first()) {
                $data['planDetails'] = $account->getPlanDetails(false, false);
            }
        }

        return View::make('clients.edit', $data);
    }

    private static function getViewModel()
    {
        return [
            'data' => Input::old('data'),
            'account' => Auth::user()->account,
            'sizes' => Cache::get('sizes'),
            'customLabel1' => Auth::user()->account->customLabel('client1'),
            'customLabel2' => Auth::user()->account->customLabel('client2'),
        ];
    }

    /**
     * Update the specified resource in storage.
     *
     * @param int $id
     *
     * @return Response
     */
    public function update(UpdateClientRequest $request)
    {
        $client = $this->clientService->save($request->input(), $request->entity());

        Session::flash('message', trans('texts.updated_client'));

        return redirect()->to($client->getRoute());
    }

    public function bulk()
    {
        $action = Input::get('action');
        $ids = Input::get('public_id') ? Input::get('public_id') : Input::get('ids');

        if ($action == 'purge' && ! auth()->user()->is_admin) {
            return redirect('dashboard')->withError(trans('texts.not_authorized'));
        }

        $count = $this->clientService->bulk($ids, $action);

        $message = Utils::pluralize($action.'d_client', $count);
        Session::flash('message', $message);

        if ($action == 'purge') {
            return redirect('dashboard')->withMessage($message);
        } else {
            return $this->returnBulk(ENTITY_CLIENT, $action, $ids);
        }
    }

    public function statement($clientPublicId)
    {
        $statusId = request()->status_id;
        $startDate = request()->start_date;
        $endDate = request()->end_date;
        $account = Auth::user()->account;
        $client = Client::scope(request()->client_id)->with('contacts')->firstOrFail();

        if (! $startDate) {
            $startDate = Utils::today(false)->modify('-6 month')->format('Y-m-d');
            $endDate = Utils::today(false)->format('Y-m-d');
        }

        if (request()->json) {
            return dispatch_now(new GenerateStatementData($client, request()->all()));
        }

        $data = [
            'showBreadcrumbs' => false,
            'client' => $client,
            'account' => $account,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ];

        return view('clients.statement', $data);
    }

    public function getEmailHistory()
    {
        $history = dispatch_now(new LoadPostmarkHistory(request()->email));

        return response()->json($history);
    }

    public function reactivateEmail()
    {
        $result = dispatch_now(new ReactivatePostmarkEmail(request()->bounce_id));

        return response()->json($result);
    }
}
