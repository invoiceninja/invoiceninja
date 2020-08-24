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
use App\Models\CompanyGateway;
use App\Models\GatewayType;
use App\PaymentDrivers\AuthorizePaymentDriver;
use App\Utils\Ninja;
use App\Utils\Traits\MakesDates;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentMethodController extends Controller
{
    use MakesDates;

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function index()
    {
        return $this->render('payment_methods.index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(CreatePaymentMethodRequest $request)
    {
        $gateway = $this->getClientGateway();

        $data['gateway'] = $gateway;

        return $gateway
            ->driver(auth()->user()->client)
            ->setPaymentMethod($request->query('method'))
            ->authorizeView($data);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        return $request->all();

        $gateway = $this->getClientGateway();

        return $gateway
            ->driver(auth()->user()->client)
            ->setPaymentMethod($request->query('method'))
            ->authorizeResponse($request);
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

    public function verify(ClientGatewayToken $payment_method)
    {
        $gateway = $this->getClientGateway();

        return $gateway
            ->driver(auth()->user()->client)
            ->setPaymentMethod(request()->query('method'))
            ->verificationView($payment_method);
    }

    public function processVerification(ClientGatewaytoken $payment_method)
    {
        $gateway = $this->getClientGateway();

        return $gateway
            ->driver(auth()->user()->client)
            ->setPaymentMethod(request()->query('method'))
            ->processVerification($payment_method);
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
            event(new MethodDeleted($payment_method, auth('contact')->user()->company, Ninja::eventVars()));
            $payment_method->delete();
        } catch (\Exception $e) {
            Log::error(json_encode($e));
            return back();
        }

        return redirect()
            ->route('client.payment_methods.index')
            ->withSuccess('Payment method has been successfully removed.');
    }

    private function getClientGateway()
    {
        if (request()->query('method') == GatewayType::CREDIT_CARD) {
            return $gateway = auth()->user()->client->getCreditCardGateway();
        }

        if (request()->query('method') == GatewayType::BANK_TRANSFER) {
            return $gateway = auth()->user()->client->getBankTransferGateway();
        }

        return abort(404);
    }
}
