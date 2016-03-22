<?php namespace App\Http\Controllers;

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

use App\Models\PaymentTerm;
use App\Ninja\Repositories\VendorRepository;
use App\Services\PaymentService;
use App\Services\PaymentTermService;

class PaymentTermController extends BaseController
{
    protected $paymentTermService;

    public function __construct(PaymentTermService $paymentTermService)
    {
        //parent::__construct();

        $this->paymentTermService = $paymentTermService;
    }

    public function index()
    {
        return Redirect::to('settings/' . ACCOUNT_PAYMENT_TERMS);
    }

    public function getDatatable()
    {
        return $this->paymentTermService->getDatatable();
    }

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

    public function store()
    {
        return $this->save();
    }

    public function update($publicId)
    {
        return $this->save($publicId);
    }

    private function save($publicId = false)
    {
        if ($publicId) {
            $paymentTerm = PaymentTerm::scope($publicId)->firstOrFail();
        } else {
            $paymentTerm = PaymentTerm::createNew();
        }

        $paymentTerm->name      = trim(Input::get('name'));
        $paymentTerm->num_days  = Utils::parseInt(Input::get('num_days'));
        $paymentTerm->save();

        $message = $publicId ? trans('texts.updated_payment_term') : trans('texts.created_payment_term');
        Session::flash('message', $message);

        return Redirect::to('settings/' . ACCOUNT_PAYMENT_TERMS);
    }

    public function bulk()
    {
        $action = Input::get('bulk_action');
        $ids    = Input::get('bulk_public_id');
        $count  = $this->paymentTermService->bulk($ids, $action);

        Session::flash('message', trans('texts.archived_payment_term'));

        return Redirect::to('settings/' . ACCOUNT_PAYMENT_TERMS);
    }

}
