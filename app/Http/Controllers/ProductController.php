<?php

namespace App\Http\Controllers;

use App\Filters\ProductFilters;
use App\Http\Requests\Product\ShowProductRequest;
use App\Http\Requests\Product\EditProductRequest;
use App\Models\Product;
use App\Transformers\ProductTransformer;
use App\Utils\Traits\MakesHash;
use Illuminate\Http\Request;

class ProductController extends BaseController
{

    use MakesHash;

    protected $entityType = Product::class;

    protected $entityTransformer = ProductTransformer::class;

   /**
     * ProductController constructor.
     */
    public function __construct()
    {

        parent::__construct();

    }

    /**
     */
    public function index(ProductFilters $filters)
    {
        
        $products = Product::filter($filters);
        
        return $this->listResponse($products);

    }


    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(ShowProductRequest $request, Product $product)
    {
        return $this->itemResponse($product);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(EditProductRequest $request, Product $product)
    {
        return $this->itemResponse($product);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
