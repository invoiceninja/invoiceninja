<?php

use ninja\repositories\PaymentRepository;
use Payment;

class PaymentApiController extends Controller {

  protected $paymentRepo;

  public function __construct(PaymentRepository $paymentRepo)
  {
    $this->paymentRepo = $paymentRepo;
  } 

  public function index()
  {    
    if (!Utils::isPro()) {
      return Redirect::to('/');
    }
    
    $payments = Payment::scope()->orderBy('created_at', 'desc')->get();
    $payments = Utils::remapPublicIds($payments->toArray());

    $response = json_encode($payments, JSON_PRETTY_PRINT);
    $headers = Utils::getApiHeaders(count($payments));
    return Response::make($response, 200, $headers);
  }

  /*  
  public function store()
  {
    $data = Input::all();
    $invoice = $this->invoiceRepo->save(false, $data, false);

    $response = json_encode($invoice, JSON_PRETTY_PRINT);
    $headers = Utils::getApiHeaders();
    return Response::make($response, 200, $headers);
  }
  */
}