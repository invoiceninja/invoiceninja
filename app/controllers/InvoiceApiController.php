<?php

use ninja\repositories\InvoiceRepository;

class InvoiceApiController extends Controller
{
    protected $invoiceRepo;

    public function __construct(InvoiceRepository $invoiceRepo)
    {
        $this->invoiceRepo = $invoiceRepo;
    }

    public function index()
    {
        if (!Utils::isPro()) {
            return Redirect::to('/');
        }

        $invoices = Invoice::scope()->where('invoices.is_quote', '=', false)->orderBy('created_at', 'desc')->get();
        $invoices = Utils::remapPublicIds($invoices->toArray());

        $response = json_encode($invoices, JSON_PRETTY_PRINT);
        $headers = Utils::getApiHeaders(count($invoices));

        return Response::make($response, 200, $headers);
    }

    /*
    public function store()
    {
        if (!Utils::isPro()) {
            return Redirect::to('/');
        }

        $data = Input::all();
        $invoice = $this->invoiceRepo->save(false, $data, false);

        $response = json_encode($invoice, JSON_PRETTY_PRINT);
        $headers = Utils::getApiHeaders();

        return Response::make($response, 200, $headers);
    }
    */
}
