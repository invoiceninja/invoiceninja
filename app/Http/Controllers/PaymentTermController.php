<?php

namespace App\Http\Controllers;

use App\Models\PaymentTerm;
use App\Services\PaymentTermService;
use Auth;
use Input;
use Redirect;
use Session;
use URL;
use Utils;
use View;

class PaymentTermController extends BaseController
{
    /**
     * @var PaymentTermService
     */
    protected $paymentTermService;

    /**
     * PaymentTermController constructor.
     *
     * @param PaymentTermService $paymentTermService
     */
    public function __construct(PaymentTermService $paymentTermService)
    {
        //parent::__construct();

        $this->paymentTermService = $paymentTermService;
    }

    /**
     * @return \Illuminate\Http\RedirectResponse
     */
    public function index()
    {
        return Redirect::to('settings/' . ACCOUNT_PAYMENT_TERMS);
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDatatable()
    {
        $accountId = Auth::user()->account_id;

        return $this->paymentTermService->getDatatable($accountId);
    }

    /**
     * @param $publicId
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function edit($publicId)
    {
        $data = [
          'paymentTerm' => PaymentTerm::scope($publicId)->firstOrFail(),
          'method' => 'PUT',
          'url' => 'payment_terms/'.$publicId,
          'title' => trans('texts.edit_payment_term'),
        ];

        return View::make('accounts.payment_term', $data);
    }

    /**
     * @return \Illuminate\Contracts\View\View
     */
    public function create()
    {
        $data = [
          'paymentTerm' => null,
          'method' => 'POST',
          'url' => 'payment_terms',
          'title' => trans('texts.create_payment_term'),
        ];

        return View::make('accounts.payment_term', $data);
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
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update($publicId)
    {
        return $this->save($publicId);
    }

    /**
     * @param bool $publicId
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    private function save($publicId = false)
    {
        if ($publicId) {
            $paymentTerm = PaymentTerm::scope($publicId)->firstOrFail();
        } else {
            $paymentTerm = PaymentTerm::createNew();
        }

        $paymentTerm->num_days = Utils::parseInt(Input::get('num_days'));
        $paymentTerm->name = 'Net ' . $paymentTerm->num_days;
        $paymentTerm->save();

        $message = $publicId ? trans('texts.updated_payment_term') : trans('texts.created_payment_term');
        Session::flash('message', $message);

        return Redirect::to('settings/' . ACCOUNT_PAYMENT_TERMS);
    }

    /**
     * @return \Illuminate\Http\RedirectResponse
     */
    public function bulk()
    {
        $action = Input::get('bulk_action');
        $ids = Input::get('bulk_public_id');
        $count = $this->paymentTermService->bulk($ids, $action);

        Session::flash('message', trans('texts.archived_payment_term'));

        return Redirect::to('settings/' . ACCOUNT_PAYMENT_TERMS);
    }
}
