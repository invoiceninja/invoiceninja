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

use App\Models\Product;

class ProductController extends BaseController
{
    public function getDatatable()
    {
        $query = DB::table('products')
                ->where('products.account_id', '=', Auth::user()->account_id)
                ->where('products.deleted_at', '=', null)
                ->select('products.public_id', 'products.product_key', 'products.notes', 'products.cost');

        return Datatable::query($query)
      ->addColumn('product_key', function ($model) { return link_to('products/'.$model->public_id.'/edit', $model->product_key); })
      ->addColumn('notes', function ($model) { return nl2br(Str::limit($model->notes, 100)); })
      ->addColumn('cost', function ($model) { return Utils::formatMoney($model->cost); })
      ->addColumn('dropdown', function ($model) {
        return '<div class="btn-group tr-action" style="visibility:hidden;">
            <button type="button" class="btn btn-xs btn-default dropdown-toggle" data-toggle="dropdown">
              '.trans('texts.select').' <span class="caret"></span>
            </button>
            <ul class="dropdown-menu" role="menu">
            <li><a href="'.URL::to('products/'.$model->public_id).'/edit">'.uctrans('texts.edit_product').'</a></li>
            <li class="divider"></li>
            <li><a href="'.URL::to('products/'.$model->public_id).'/archive">'.uctrans('texts.archive_product').'</a></li>
          </ul>
        </div>';
      })
      ->orderColumns(['cost', 'product_key', 'cost'])
      ->make();
    }

    public function edit($publicId)
    {
        $data = [
      'showBreadcrumbs' => false,
      'product' => Product::scope($publicId)->firstOrFail(),
      'method' => 'PUT',
      'url' => 'products/'.$publicId,
      'title' => trans('texts.edit_product'),
    ];

        return View::make('accounts.product', $data);
    }

    public function create()
    {
        $data = [
      'showBreadcrumbs' => false,
      'product' => null,
      'method' => 'POST',
      'url' => 'products',
      'title' => trans('texts.create_product'),
    ];

        return View::make('accounts.product', $data);
    }

    public function store()
    {
        return $this->save();
    }

    public function update($publicId)
    {
        return $this->save($publicId);
    }

    private function save($productPublicId = false)
    {
        if ($productPublicId) {
            $product = Product::scope($productPublicId)->firstOrFail();
        } else {
            $product = Product::createNew();
        }

        $product->product_key = trim(Input::get('product_key'));
        $product->notes = trim(Input::get('notes'));
        $product->cost = trim(Input::get('cost'));
        $product->save();

        $message = $productPublicId ? trans('texts.updated_product') : trans('texts.created_product');
        Session::flash('message', $message);

        return Redirect::to('company/products');
    }

    public function archive($publicId)
    {
        $product = Product::scope($publicId)->firstOrFail();
        $product->delete();

        Session::flash('message', trans('texts.archived_product'));

        return Redirect::to('company/products');
    }
}
