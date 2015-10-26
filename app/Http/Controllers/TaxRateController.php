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

class TaxRateController extends BaseController
{
    public function index()
    {
        return Redirect::to('settings/' . ACCOUNT_TAX_RATES);
    }

    public function getDatatable()
    {
        $query = DB::table('tax_rates')
                ->where('tax_rates.account_id', '=', Auth::user()->account_id)
                ->where('tax_rates.deleted_at', '=', null)
                ->select('tax_rates.public_id', 'tax_rates.name', 'tax_rates.rate');

        return Datatable::query($query)
                  ->addColumn('name', function ($model) { return link_to('tax_rates/'.$model->public_id.'/edit', $model->name); })
                  ->addColumn('rate', function ($model) { return $model->rate . '%'; })
                  ->addColumn('dropdown', function ($model) {
                    return '<div class="btn-group tr-action" style="visibility:hidden;">
                        <button type="button" class="btn btn-xs btn-default dropdown-toggle" data-toggle="dropdown">
                          '.trans('texts.select').' <span class="caret"></span>
                        </button>
                        <ul class="dropdown-menu" role="menu">
                        <li><a href="'.URL::to('tax_rates/'.$model->public_id).'/edit">'.uctrans('texts.edit_tax_rate').'</a></li>
                        <li class="divider"></li>
                        <li><a href="'.URL::to('tax_rates/'.$model->public_id).'/archive">'.uctrans('texts.archive_tax_rate').'</a></li>
                      </ul>
                    </div>';
                  })
                  ->orderColumns(['name', 'rate'])
                  ->make();
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

    public function archive($publicId)
    {
        $tax_rate = TaxRate::scope($publicId)->firstOrFail();
        $tax_rate->delete();

        Session::flash('message', trans('texts.archived_tax_rate'));

        return Redirect::to('settings/' . ACCOUNT_TAX_RATES);
    }
}
