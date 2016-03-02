<?php namespace App\Http\Controllers;

use App\Ninja\Repositories\ProductRepository;
use App\Ninja\Transformers\ProductTransformer;
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
use App\Models\TaxRate;
use App\Services\ProductService;

class ProductApiController extends BaseAPIController
{
    protected $productService;

    protected  $productRepo;

    public function __construct(ProductService $productService, ProductRepository $productRepo)
    {
        //parent::__construct();

        $this->productService = $productService;
        $this->productRepo = $productRepo;
    }

    public function index()
    {

        $products = Product::scope()->withTrashed();
        $products = $products->paginate();

        $paginator = Product::scope()->withTrashed()->paginate();

        $transformer = new ProductTransformer(\Auth::user()->account, $this->serializer);
        $data = $this->createCollection($products, $transformer, 'products', $paginator);

        return $this->response($data);

    }

    public function getDatatable()
    {
        return $this->productService->getDatatable(Auth::user()->account_id);
    }

    public function store()
    {
        return $this->save();
    }

    public function update(\Illuminate\Http\Request $request, $publicId)
    {

        if ($request->action == ACTION_ARCHIVE) {
            $product = Product::scope($publicId)->withTrashed()->firstOrFail();
            $this->productRepo->archive($product);

            $transformer = new ProductTransformer(\Auth::user()->account, Input::get('serializer'));
            $data = $this->createItem($product, $transformer, 'products');

            return $this->response($data);
        }
        else
            return $this->save($publicId);
    }

    public function destroy($publicId)
    {
       //stub
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
        //$product->default_tax_rate_id = Input::get('default_tax_rate_id');

        $product->save();

        $transformer = new ProductTransformer(\Auth::user()->account, Input::get('serializer'));
        $data = $this->createItem($product, $transformer, 'products');

        return $this->response($data);

    }


}
