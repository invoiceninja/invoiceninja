<?php namespace App\Http\Controllers;

use Auth;
use Datatable;
use Utils;
use View;
use URL;
use Validator;
use Input;
use Session;
use Redirect;
use Cache;

use App\Models\Activity;
use App\Models\Client;
use App\Models\Account;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Size;
use App\Models\PaymentTerm;
use App\Models\Industry;
use App\Models\Currency;
use App\Models\Country;
use App\Models\Task;
use App\Ninja\Repositories\ClientRepository;
use App\Services\ClientService;

use App\Http\Requests\CreateClientRequest;
use App\Http\Requests\UpdateClientRequest;

class ClientController extends BaseController
{
    protected $clientService;
    protected $clientRepo;

    public function __construct(ClientRepository $clientRepo, ClientService $clientService)
    {
        parent::__construct();

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
        return View::make('list', array(
            'entityType' => ENTITY_CLIENT,
            'title' => trans('texts.clients'),
            'sortCol' => '4',
            'columns' => Utils::trans([
              'checkbox',
              'client',
              'contact',
              'email',
              'date_created',
              'last_login',
              'balance',
              'action'
            ]),
        ));
    }

    public function getDatatable()
    {
        return $this->clientService->getDatatable(Input::get('sSearch'));
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
     * @param  int      $id
     * @return Response
     */
    public function show($publicId)
    {
        $client = Client::withTrashed()->scope($publicId)->with('contacts', 'size', 'industry')->firstOrFail();
        Utils::trackViewed($client->getDisplayName(), ENTITY_CLIENT);

        $actionLinks = [
            ['label' => trans('texts.new_task'), 'url' => '/tasks/create/'.$client->public_id]
        ];

        if (Utils::isPro()) {
            array_push($actionLinks, ['label' => trans('texts.new_quote'), 'url' => '/quotes/create/'.$client->public_id]);
        }

        array_push($actionLinks,
            ['label' => trans('texts.enter_payment'), 'url' => '/payments/create/'.$client->public_id],
            ['label' => trans('texts.enter_credit'), 'url' => '/credits/create/'.$client->public_id]
        );
        
        $data = array(
            'actionLinks' => $actionLinks,
            'showBreadcrumbs' => false,
            'client' => $client,
            'credit' => $client->getTotalCredit(),
            'title' => trans('texts.view_client'),
            'hasRecurringInvoices' => Invoice::scope()->where('is_recurring', '=', true)->whereClientId($client->id)->count() > 0,
            'hasQuotes' => Invoice::scope()->where('is_quote', '=', true)->whereClientId($client->id)->count() > 0,
            'hasTasks' => Task::scope()->whereClientId($client->id)->count() > 0,
            'gatewayLink' => $client->getGatewayLink(),
        );

        return View::make('clients.show', $data);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        if (Client::scope()->count() > Auth::user()->getMaxNumClients()) {
            return View::make('error', ['hideHeader' => true, 'error' => "Sorry, you've exceeded the limit of ".Auth::user()->getMaxNumClients()." clients"]);
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
     * @param  int      $id
     * @return Response
     */
    public function edit($publicId)
    {
        $client = Client::scope($publicId)->with('contacts')->firstOrFail();
        $data = [
            'client' => $client,
            'method' => 'PUT',
            'url' => 'clients/'.$publicId,
            'title' => trans('texts.edit_client'),
        ];

        $data = array_merge($data, self::getViewModel());

        if (Auth::user()->account->isNinjaAccount()) {
            if ($account = Account::whereId($client->public_id)->first()) {
                $data['proPlanPaid'] = $account['pro_plan_paid'];
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
            'paymentTerms' => Cache::get('paymentTerms'),
            'industries' => Cache::get('industries'),
            'currencies' => Cache::get('currencies'),
            'languages' => Cache::get('languages'),
            'countries' => Cache::get('countries'),
            'customLabel1' => Auth::user()->account->custom_client_label1,
            'customLabel2' => Auth::user()->account->custom_client_label2,
        ];
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int      $id
     * @return Response
     */
    public function update(UpdateClientRequest $request)
    {
        $client = $this->clientService->save($request->input());
        
        Session::flash('message', trans('texts.updated_client'));
        
        return redirect()->to($client->getRoute());
    }

    public function bulk()
    {
        $action = Input::get('action');
        $ids = Input::get('public_id') ? Input::get('public_id') : Input::get('ids');
        $count = $this->clientService->bulk($ids, $action);

        $message = Utils::pluralize($action.'d_client', $count);
        Session::flash('message', $message);

        if ($action == 'restore' && $count == 1) {
            return Redirect::to('clients/'.Utils::getFirst($ids));
        } else {
            return Redirect::to('clients');
        }
    }
}
