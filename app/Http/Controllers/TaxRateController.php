<?php namespace App\Http\Controllers;

use Auth;
use Str;
use DB;
use Datatable;
use Utils;
use URL;
use View;
use Input;
use Session;
use Redirect;

use App\Models\TaxRate;
use App\Services\TaxRateService;

class TaxRateController extends BaseController
{
    protected $taxRateService;

    public function __construct(TaxRateService $taxRateService)
    {
        parent::__construct();

        $this->taxRateService = $taxRateService;
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

    public function store()
    {
        return $this->save();
    }

    public function update($publicId)
    {
        return $this->save($publicId);
    }

    private function save($publicId = false)
    {
        if ($publicId) {
            $taxRate = TaxRate::scope($publicId)->firstOrFail();
        } else {
            $taxRate = TaxRate::createNew();
        }

        $taxRate->name = trim(Input::get('name'));
        $taxRate->rate = Utils::parseFloat(Input::get('rate'));
        $taxRate->save();

        $message = $publicId ? trans('texts.updated_tax_rate') : trans('texts.created_tax_rate');
        Session::flash('message', $message);

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
