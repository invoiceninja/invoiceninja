<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateTaxRateRequest;
use App\Http\Requests\UpdateTaxRateRequest;
use App\Models\TaxRate;
use App\Ninja\Repositories\TaxRateRepository;
use App\Services\TaxRateService;
use Auth;
use Input;
use Redirect;
use Session;
use URL;
use View;

class TaxRateController extends BaseController
{
    protected $taxRateService;
    protected $taxRateRepo;

    public function __construct(TaxRateService $taxRateService, TaxRateRepository $taxRateRepo)
    {
        //parent::__construct();

        $this->taxRateService = $taxRateService;
        $this->taxRateRepo = $taxRateRepo;
    }

    public function index()
    {
        return Redirect::to('settings/' . ACCOUNT_TAX_RATES);
    }

    public function getDatatable()
    {
        return $this->taxRateService->getDatatable(Auth::user()->account_id);
    }

    public function edit($publicId)
    {
        $data = [
          'taxRate' => TaxRate::scope($publicId)->firstOrFail(),
          'method' => 'PUT',
          'url' => 'tax_rates/'.$publicId,
          'title' => trans('texts.edit_tax_rate'),
        ];

        return View::make('accounts.tax_rate', $data);
    }

    public function create()
    {
        $data = [
          'taxRate' => null,
          'method' => 'POST',
          'url' => 'tax_rates',
          'title' => trans('texts.create_tax_rate'),
        ];

        return View::make('accounts.tax_rate', $data);
    }

    public function store(CreateTaxRateRequest $request)
    {
        $this->taxRateRepo->save($request->input());

        Session::flash('message', trans('texts.created_tax_rate'));

        return Redirect::to('settings/' . ACCOUNT_TAX_RATES);
    }

    public function update(UpdateTaxRateRequest $request, $publicId)
    {
        $this->taxRateRepo->save($request->input(), $request->entity());

        Session::flash('message', trans('texts.updated_tax_rate'));

        return Redirect::to('settings/' . ACCOUNT_TAX_RATES);
    }

    public function bulk()
    {
        $action = Input::get('bulk_action');
        $ids = Input::get('bulk_public_id');
        $count = $this->taxRateService->bulk($ids, $action);

        Session::flash('message', trans('texts.archived_tax_rate'));

        return Redirect::to('settings/' . ACCOUNT_TAX_RATES);
    }
}
