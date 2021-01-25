<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Http\Controllers\ClientPortal;

use App\Events\Payment\Methods\MethodDeleted;
use App\Http\Controllers\Controller;
use App\Http\Requests\ClientPortal\CreatePaymentMethodRequest;
use App\Http\Requests\Request;
use App\Models\ClientGatewayToken;
use App\Models\GatewayType;
use App\Utils\Ninja;
use App\Utils\Traits\MakesDates;
use Exception;
use Illuminate\Contracts\View\Factory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class PaymentMethodController extends Controller
{
    use MakesDates;

    /**
     * Display a listing of the resource.
     *
     * @return Factory|View
     */
    public function index()
    {
        return $this->render('payment_methods.index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @param CreatePaymentMethodRequest $request
     * @return Response
     */
    public function create(CreatePaymentMethodRequest $request)
    {
        $gateway = $this->getClientGateway();

        $data['gateway'] = $gateway;

        return $gateway
            ->driver(auth()->user()->client)
            ->setPaymentMethod($request->query('method'))
            ->checkRequirements()
            ->authorizeView($data);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param Request $request
     * @return Response
     */
    public function store(Request $request)
    {
        $gateway = $this->getClientGateway();

        return $gateway
            ->driver(auth()->user()->client)
            ->setPaymentMethod($request->query('method'))
            ->checkRequirements()
            ->authorizeResponse($request);
    }

    /**
     * Display the specified resource.
     *
     * @param ClientGatewayToken $payment_method
     * @return Factory|View
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
     * @return void
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param Request $request
     * @param int $id
     * @return void
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

    public function processVerification(Request $request, ClientGatewaytoken $payment_method)
    {
        $gateway = $this->getClientGateway();

        return $gateway
            ->driver(auth()->user()->client)
            ->setPaymentMethod(request()->query('method'))
            ->processVerification($request, $payment_method);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param ClientGatewayToken $payment_method
     * @return RedirectResponse
     */
    public function destroy(ClientGatewayToken $payment_method)
    {
        $gateway = $this->getClientGateway();

        $gateway
            ->driver(auth()->user()->client)
            ->setPaymentMethod(request()->query('method'))
            ->detach($payment_method);

        try {
            event(new MethodDeleted($payment_method, auth('contact')->user()->company, Ninja::eventVars()));
            $payment_method->delete();
        } catch (Exception $e) {
            
            nlog($e->getMessage());

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
