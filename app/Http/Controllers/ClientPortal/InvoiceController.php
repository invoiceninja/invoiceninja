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

namespace App\Http\Controllers\ClientPortal;

use App\Filters\InvoiceFilters;
use App\Http\Controllers\Controller;
use App\Jobs\Entity\ActionEntity;
use App\Models\Invoice;
use App\Repositories\BaseRepository;
use App\Utils\Number;
use App\Utils\Traits\MakesDates;
use App\Utils\Traits\MakesHash;
use Barracuda\ArchiveStream\Archive;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Yajra\DataTables\Facades\DataTables;
use Yajra\DataTables\Html\Builder;

/**
 * Class InvoiceController
 * @package App\Http\Controllers\ClientPortal\InvoiceController
 */

class InvoiceController extends Controller
{

    use MakesHash;
    use MakesDates;
    /**
     * Show the list of Invoices
     *
     * @param      \App\Filters\InvoiceFilters  $filters  The filters
     *
     * @return \Illuminate\Http\Response
     */
    public function index(InvoiceFilters $filters, Builder $builder)
    {//
        $invoices = Invoice::filter($filters)->with('client', 'client.country');

        if (request()->ajax()) {

            return DataTables::of($invoices)->addColumn('action', function ($invoice) {
                    return '<a href="/client/invoices/'. $invoice->hashed_id .'/edit" class="btn btn-xs btn-primary"><i class="glyphicon glyphicon-edit"></i>'.ctrans('texts.view').'</a>';
                })
                ->addColumn('checkbox', function ($invoice){
                    return '<input type="checkbox" name="hashed_ids[]" value="'. $invoice->hashed_id .'"/>';
                })
                ->editColumn('status_id', function ($invoice){
                    return Invoice::badgeForStatus($invoice->status);
                })->editColumn('invoice_date', function ($invoice){
                    return $this->formatDate($invoice->invoice_date, $invoice->client->date_format());
                })->editColumn('due_date', function ($invoice){
                    return $this->formatDate($invoice->due_date, $invoice->client->date_format());
                })->editColumn('balance', function ($invoice) {
                    return Number::formatMoney($invoice->balance, $invoice->client->currency(), $invoice->client->country, $invoice->client->getMergedSettings());
                })->editColumn('amount', function ($invoice) {
                    return Number::formatMoney($invoice->amount, $invoice->client->currency(), $invoice->client->country, $invoice->client->getMergedSettings());
                })
                ->rawColumns(['checkbox', 'action', 'status_id'])
                ->make(true);
        
        }

        $data['html'] = $builder;
      
        return view('portal.default.invoices.index', $data);

    }

    /**
     * Display the specified resource.
     *
     * @param      \App\Models\Invoice $invoice  The invoice
     *
     * @return \Illuminate\Http\Response
     */
    public function show(Invoice $invoice)
    {


    }

    /**
     * Pay one or more invoices
     * 
     * @return View
     */
    public function bulk()
    {

        $transformed_ids = $this->transformKeys(explode(",",request()->input('hashed_ids')));

        if(request()->input('action') == 'payment')
            return $this->makePayment($transformed_ids);
        else if(request()->input('action') == 'download')
            return $this->downloadInvoicePDF($transformed_ids);
                
    }


    private function makePayment(array $ids)
    {

        $invoices = Invoice::whereIn('id', $ids)
                            ->whereClientId(auth()->user()->client->id)
                            ->get()
                            ->filter(function ($invoice){
                                return $invoice->isPayable();
                            });

        $data = [
            'invoices' => $invoices,
        ];

        return view('portal.default.invoices.payment', $data);

    }

    private function downloadInvoicePDF(array $ids)
    {
        $invoices = Invoice::whereIn('id', $ids)
                            ->whereClientId(auth()->user()->client->id)
                            ->get()
                            ->filter(function ($invoice){
                                return $invoice->isPayable();
                            });

        //generate pdf's of invoices locally
        

        //if only 1 pdf, output to buffer for download
        
        
        //if multiple pdf's, output to zip stream using Barracuda lib
        

/*       
    $zip = Archive::instance_by_useragent(date('Y-m-d') . '_' . str_replace(' ', '_', trans('texts.invoices')));
    $zip->add_file($name, $document->getRaw());
    $zip->finish();
*/ 
    }
    

}
