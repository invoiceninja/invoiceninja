<?php namespace App\Http\Controllers;

use Utils;
use Response;
use App\Models\Invoice;
use App\Ninja\Repositories\InvoiceRepository;

class QuoteApiController extends Controller
{
    protected $invoiceRepo;

    public function __construct(InvoiceRepository $invoiceRepo)
    {
        $this->invoiceRepo = $invoiceRepo;
    }

    /**
     * @SWG\Get(
     *   path="/quotes",
     *   tags={"quote"},
     *   summary="List of quotes",
     *   @SWG\Response(
     *     response=200,
     *     description="A list with quotes",
     *      @SWG\Schema(type="array", @SWG\Items(ref="#/definitions/Invoice"))
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     */
    public function index($clientPublicId = false)
    {
        $invoices = Invoice::scope()
                        ->with('client', 'user')
                        ->where('invoices.is_quote', '=', true);

        if ($clientPublicId) {
            $invoices->whereHas('client', function($query) use ($clientPublicId) {
                $query->where('public_id', '=', $clientPublicId);
            });
        }

        $invoices = $invoices->orderBy('created_at', 'desc')->get();
        $invoices = Utils::remapPublicIds($invoices);

        $response = json_encode($invoices, JSON_PRETTY_PRINT);
        $headers = Utils::getApiHeaders(count($invoices));

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
