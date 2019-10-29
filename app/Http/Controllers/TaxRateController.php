<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2019. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Http\Controllers;

use App\Factory\TaxRateFactory;
use App\Http\Requests\TaxRate\DestroyTaxRateRequest;
use App\Http\Requests\TaxRate\EditTaxRateRequest;
use App\Http\Requests\TaxRate\ShowTaxRateRequest;
use App\Http\Requests\TaxRate\StoreTaxRateRequest;
use App\Http\Requests\TaxRate\UpdateTaxRateRequest;
use App\Models\TaxRate;
use App\Transformers\TaxRateTransformer;
use Illuminate\Http\Request;

/**
 * Class TaxRateController
 * @package App\Http\Controllers
 */
class TaxRateController extends BaseController
{

    protected $entity_type = TaxRate::class;

    protected $entity_transformer = TaxRateTransformer::class;

    public function __construct()
    {
        parent::__construct();

    }
    
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $tax_rates = TaxRate::all();

        return $this->listResponse($tax_rates);
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
    public function store(StoreTaxRateRequest $request)
    {
        $tax_rate = TaxRateFactory::create(auth()->user()->company()->id, auth()->user()->id);
        $tax_rate->fill($request->all());
        $tax_rate->save();
        
        return $this->itemResponse($tax_rate);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(ShowTaxRateRequest $request, TaxRate $tax_rate)
    {
        return $this->itemResponse($tax_rate);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(EditTaxRateRequest $request, TaxRate $tax_rate)
    {
        return $this->itemResponse($tax_rate);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateTaxRateRequest $request, TaxRate $tax_rate)
    {
        $tax_rate->fill($request->all());
        $tax_rate->save();

        return $this->itemResponse($tax_rate);

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(DestroyTaxRateRequest $request, TaxRate $tax_rate)
    {
        $tax_rate->delete();

        return response()->json([], 200);

    }
}
