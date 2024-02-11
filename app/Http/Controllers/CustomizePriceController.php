<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomizePrice\CustomizePriceRequest;
use App\Http\Requests\Request;
use App\Models\CustomizePrice;
use App\Models\Product;
use App\Transformers\ProductTransformer;
use App\Utils\Traits\MakesHash;
use League\Fractal\Resource\Collection;

class CustomizePriceController extends BaseController
{

    use MakesHash;

    protected $entity_transformer = ProductTransformer::class;

    public function create(CustomizePriceRequest $request)
    {
        $customizePrice = CustomizePrice::updateOrCreate(
            ['client_id' => $request["client_id"], 'product_id' => $request["product_id"]],
            ['price' => $request["price"]]
        );

        $customizePrice->client_id = $this->encodePrimaryKey($customizePrice->client_id);
        $customizePrice->product_id = $this->encodePrimaryKey($customizePrice->product_id);

        return response()->json(['data' => [$customizePrice]]);
    }

    public function get(Request $request)
    {

        $client_id = $this->decodePrimaryKey($request->client_id);

        $customizePrices = CustomizePrice::where("client_id", $client_id)->get();

        $resource = collect([]);

        foreach ($customizePrices as $customizePrice) {
            $product = $customizePrice->product()->first();
            $product->price = $customizePrice->price;
            $resource->push($product);
        }

        $data = new Collection($resource, new ProductTransformer(), Product::class);
        $resource = $this->manager->createData($data)->toArray();

        foreach($resource["data"] as &$entity){
            $entity["documents"] = $entity["documents"]["data"];
        }

        return $resource;
    }

    public function delete(CustomizePriceRequest $request)
    {
        CustomizePrice::where("client_id", $request["client_id"])
            ->where("product_id", $request["product_id"])->delete();

        $customizePrices = CustomizePrice::where("client_id", $request["client_id"])->get();

        foreach ($customizePrices as $customizePrice) {
            $customizePrice->client_id = $this->encodePrimaryKey($customizePrice->client_id);
            $customizePrice->product_id = $this->encodePrimaryKey($customizePrice->product_id);
        }

        return response()->json(['data' => $customizePrices]);
    }
}
