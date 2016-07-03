<?php namespace App\Http\Controllers;

use Auth;
use URL;
use View;
use Input;
use Session;
use Redirect;
use App\Models\Product;
use App\Models\TaxRate;
use App\Services\ProductService;

/**
 * Class ProductController
 */
class ProductController extends BaseController
{
    /**
     * @var ProductService
     */
    protected $productService;

    /**
     * ProductController constructor.
     * 
     * @param ProductService $productService
     */
    public function __construct(ProductService $productService)
    {
        //parent::__construct();

        $this->productService = $productService;
    }

    /**
     * @return \Illuminate\Http\RedirectResponse
     */
    public function index()
    {
        return Redirect::to('settings/' . ACCOUNT_PRODUCTS);
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDatatable()
    {
        return $this->productService->getDatatable(Auth::user()->account_id);
    }

    /**
     * @param $publicId
     * @return \Illuminate\Contracts\View\View
     */
    public function edit($publicId)
    {
        $account = Auth::user()->account;

        $data = [
          'account' => $account,
          'taxRates' => $account->invoice_item_taxes ? TaxRate::scope()->get(['id', 'name', 'rate']) : null,
          'product' => Product::scope($publicId)->firstOrFail(),
          'method' => 'PUT',
          'url' => 'products/'.$publicId,
          'title' => trans('texts.edit_product'),
        ];

        return View::make('accounts.product', $data);
    }

    /**
     * @return \Illuminate\Contracts\View\View
     */
    public function create()
    {
        $account = Auth::user()->account;

        $data = [
          'account' => $account,
          'taxRates' => $account->invoice_item_taxes ? TaxRate::scope()->get(['id', 'name', 'rate']) : null,
          'product' => null,
          'method' => 'POST',
          'url' => 'products',
          'title' => trans('texts.create_product'),
        ];

        return View::make('accounts.product', $data);
    }

    /**
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store()
    {
        return $this->save();
    }

    /**
     * @param $publicId
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update($publicId)
    {
        return $this->save($publicId);
    }

    /**
     * @param bool $productPublicId
     * @return \Illuminate\Http\RedirectResponse
     */
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
        $product->default_tax_rate_id = Input::get('default_tax_rate_id');

        $product->save();

        $message = $productPublicId ? trans('texts.updated_product') : trans('texts.created_product');
        Session::flash('message', $message);

        return Redirect::to('settings/' . ACCOUNT_PRODUCTS);
    }

    /**
     * @return \Illuminate\Http\RedirectResponse
     */
    public function bulk()
    {
        $action = Input::get('bulk_action');
        $ids = Input::get('bulk_public_id');
        $count = $this->productService->bulk($ids, $action);

        Session::flash('message', trans('texts.archived_product'));

        return Redirect::to('settings/' . ACCOUNT_PRODUCTS);
    }
}
