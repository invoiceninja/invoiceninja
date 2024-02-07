<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCustomizePriceRequest;
use App\Models\CustomizePrice;

class CustomizePriceController extends BaseController{

    public function create(StoreCustomizePriceRequest $request){
        $customizePrice = CustomizePrice::firstOrCreate($request->all());
        $customizePrice->save();
        return $customizePrice;
    }
}
