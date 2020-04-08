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

namespace App\Http\Controllers\ClientPortal;

use App\Events\Payment\Methods\MethodDeleted;
use App\Http\Controllers\Controller;
use App\Http\Requests\ClientPortal\CreatePaymentMethodRequest;
use App\Models\ClientGatewayToken;
use App\Utils\Traits\MakesDates;
use Illuminate\Http\Request;

class PaymentMethodController extends Controller
{
    use MakesDates;

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     * @throws \Exception
     */
    public function index()
    {
        $payment_methods = ClientGatewayToken::with('gateway_type')
            ->whereClientId(auth()->user()->client->id)
            ->paginate(10);

        return $this->render('payment_methods.index', [
            'payment_methods' => $payment_methods,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(CreatePaymentMethodRequest $request)
    {
        $gateway = auth()->user()->client->getCreditCardGateway();

        $data = [
            'gateway' => $gateway,
            'gateway_type_id' => 1,
            'token' => false,
        ];

        return $gateway->driver(auth()->user()->client)->authorizeCreditCardView($data);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $gateway = auth()->user()->client->getCreditCardGateway();

        return $gateway->driver(auth()->user()->client)->authorizeCreditCardResponse($request);
    }

    /**
     * Display the specified resource.
     *
     * @param ClientGatewayToken $payment_method
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function show(ClientGatewayToken $payment_method)
    {
        return $this->render('payment_methods.show', [
            'payment_method' => $payment_method,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param ClientGatewayToken $payment_method
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(ClientGatewayToken $payment_method)
    {
        try {
            event(new MethodDeleted($payment_method));
            $payment_method->delete();
        } catch (\Exception $e) {
            Log::error(json_encode($e));
            return back();
        }

        return redirect()
            ->route('client.payment_methods.index')
            ->withSuccess('Payment method has been successfully removed.');
    }
}
