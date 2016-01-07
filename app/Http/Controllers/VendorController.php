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
use App\Models\Vendor;
use App\Models\Account;
use App\Models\VendorContact;
use App\Models\Size;
use App\Models\PaymentTerm;
use App\Models\Industry;
use App\Models\Currency;
use App\Models\Country;
use App\Ninja\Repositories\VendorRepository;
use App\Services\VendorService;

use App\Http\Requests\CreateVendorRequest;
use App\Http\Requests\UpdateVendorRequest;
// vendor
class VendorController extends BaseController
{
    protected $vendorService;
    protected $vendorRepo;

    public function __construct(VendorRepository $vendorRepo, VendorService $vendorService)
    {
        parent::__construct();

        $this->vendorRepo = $vendorRepo;
        $this->vendorService = $vendorService;
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        return View::make('list', array(
            'entityType' => 'vendor',
            'title' => trans('texts.vendors'),
            'sortCol' => '4',
            'columns' => Utils::trans([
              'checkbox',
              'vendor',
              'contact',
              'email',
              'date_created',
              //'last_login',
              'balance',
              ''
            ]),
        ));
    }

    public function getDatatable()
    {
        return $this->vendorService->getDatatable(Input::get('sSearch'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return Response
     */
    public function store(CreateVendorRequest $request)
    {
        $vendor = $this->vendorService->save($request->input());
        
        Session::flash('message', trans('texts.created_vendor'));
        
        return redirect()->to($vendor->getRoute());
    }

    /**
     * Display the specified resource.
     *
     * @param  int      $id
     * @return Response
     */
    public function show($publicId)
    {
        $vendor = Vendor::withTrashed()->scope($publicId)->with('vendorcontacts', 'size', 'industry')->firstOrFail();
        Utils::trackViewed($vendor->getDisplayName(), 'vendor');

        $actionLinks = [
            ['label' => trans('texts.new_expense'), 'url' => '/expenses/create/'.$vendor->public_id]
        ];

        $data = array(
            'actionLinks' => $actionLinks,
            'showBreadcrumbs' => false,
            'vendor' => $vendor,
            'credit' => $vendor->getTotalCredit(),
            'title' => trans('texts.view_vendor'),
            'hasRecurringInvoices' => false,
            'hasQuotes' => false,
            'hasTasks' => false,
            'gatewayLink' => $vendor->getGatewayLink(),
        );

        return View::make('vendors.show', $data);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        if (Vendor::scope()->count() > Auth::user()->getMaxNumVendors()) {
            return View::make('error', ['hideHeader' => true, 'error' => "Sorry, you've exceeded the limit of ".Auth::user()->getMaxNumVendors()." vendors"]);
        }

        $data = [
            'vendor' => null,
            'method' => 'POST',
            'url' => 'vendors',
            'title' => trans('texts.new_vendor'),
        ];

        $data = array_merge($data, self::getViewModel());

        return View::make('vendors.edit', $data);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int      $id
     * @return Response
     */
    public function edit($publicId)
    {
        $vendor = Vendor::scope($publicId)->with('vendorcontacts')->firstOrFail();
        $data = [
            'vendor' => $vendor,
            'method' => 'PUT',
            'url' => 'vendors/'.$publicId,
            'title' => trans('texts.edit_vendor'),
        ];

        $data = array_merge($data, self::getViewModel());

        if (Auth::user()->account->isNinjaAccount()) {
            if ($account = Account::whereId($vendor->public_id)->first()) {
                $data['proPlanPaid'] = $account['pro_plan_paid'];
            }
        }

        return View::make('vendors.edit', $data);
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
            'customLabel1' => Auth::user()->account->custom_vendor_label1,
            'customLabel2' => Auth::user()->account->custom_vendor_label2,
        ];
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int      $id
     * @return Response
     */
    public function update(UpdateVendorRequest $request)
    {
        $vendor = $this->vendorService->save($request->input());
        
        Session::flash('message', trans('texts.updated_vendor'));
        
        return redirect()->to($vendor->getRoute());
    }

    public function bulk()
    {
        $action = Input::get('action');
        $ids = Input::get('public_id') ? Input::get('public_id') : Input::get('ids');
        $count = $this->vendorService->bulk($ids, $action);

        $message = Utils::pluralize($action.'d_vendor', $count);
        Session::flash('message', $message);

        if ($action == 'restore' && $count == 1) {
            return Redirect::to('vendors/' . Utils::getFirst($ids));
        } else {
            return Redirect::to('vendors');
        }
    }
}
