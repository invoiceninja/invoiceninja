<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateVendorRequest;
use App\Http\Requests\UpdateVendorRequest;
use App\Http\Requests\VendorRequest;
use App\Models\Account;
use App\Models\Vendor;
use App\Ninja\Datatables\VendorDatatable;
use App\Ninja\Repositories\VendorRepository;
use App\Services\VendorService;
use Auth;
use Cache;
use Input;
use Redirect;
use Session;
use URL;
use Utils;
use View;

class VendorController extends BaseController
{
    protected $vendorService;
    protected $vendorRepo;
    protected $entityType = ENTITY_VENDOR;

    public function __construct(VendorRepository $vendorRepo, VendorService $vendorService)
    {
        //parent::__construct();

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
        return View::make('list_wrapper', [
            'entityType' => 'vendor',
            'datatable' => new VendorDatatable(),
            'title' => trans('texts.vendors'),
        ]);
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
     * @param int $id
     *
     * @return Response
     */
    public function show(VendorRequest $request)
    {
        $vendor = $request->entity();

        $actionLinks = [
            ['label' => trans('texts.new_vendor'), 'url' => URL::to('/vendors/create/' . $vendor->public_id)],
        ];

        $data = [
            'actionLinks' => $actionLinks,
            'showBreadcrumbs' => false,
            'vendor' => $vendor,
            'title' => trans('texts.view_vendor'),
            'hasRecurringInvoices' => false,
            'hasQuotes' => false,
            'hasTasks' => false,
        ];

        return View::make('vendors.show', $data);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create(VendorRequest $request)
    {
        if (Vendor::scope()->count() > Auth::user()->getMaxNumVendors()) {
            return View::make('error', ['hideHeader' => true, 'error' => "Sorry, you've exceeded the limit of ".Auth::user()->getMaxNumVendors().' vendors']);
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
     * @param int $id
     *
     * @return Response
     */
    public function edit(VendorRequest $request)
    {
        $vendor = $request->entity();

        $data = [
            'vendor' => $vendor,
            'method' => 'PUT',
            'url' => 'vendors/'.$vendor->public_id,
            'title' => trans('texts.edit_vendor'),
        ];

        $data = array_merge($data, self::getViewModel());

        if (Auth::user()->account->isNinjaAccount()) {
            if ($account = Account::whereId($client->public_id)->first()) {
                $data['planDetails'] = $account->getPlanDetails(false, false);
            }
        }

        return View::make('vendors.edit', $data);
    }

    private static function getViewModel()
    {
        return [
            'data' => Input::old('data'),
            'account' => Auth::user()->account,
            'currencies' => Cache::get('currencies'),
        ];
    }

    /**
     * Update the specified resource in storage.
     *
     * @param int $id
     *
     * @return Response
     */
    public function update(UpdateVendorRequest $request)
    {
        $vendor = $this->vendorService->save($request->input(), $request->entity());

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

        return $this->returnBulk($this->entityType, $action, $ids);
    }
}
