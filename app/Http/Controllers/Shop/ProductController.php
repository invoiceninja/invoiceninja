<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\BaseController;
use App\Models\CompanyToken;
use App\Models\Product;
use App\Transformers\ProductTransformer;
use App\Utils\Traits\MakesHash;
use Illuminate\Http\Request;

class ProductController extends BaseController
{
    use MakesHash;
    
    protected $entity_type = Product::class;

    protected $entity_transformer = ProductTransformer::class;

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $company_token = CompanyToken::with(['company'])->whereRaw("BINARY `token`= ?", [$request->header('X-API-TOKEN')])->first();

        $products = Product::where('company_id', $company_token->company->id);

        return $this->listResponse($products);
    }

    public function show(string $product_key)
    {
        $company_token = CompanyToken::with(['company'])->whereRaw("BINARY `token`= ?", [$request->header('X-API-TOKEN')])->first();

        $product = Product::where('company_id', $company_token->company->id)
                            ->where('product_key', $product_key)
                            ->first();

        return $this->itemResponse($product);
    }
}
