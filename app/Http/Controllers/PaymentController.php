<?php

namespace App\Http\Controllers;


use App\Filters\PaymentFilters;
use App\Http\Requests\Payment\ActionPaymentRequest;
use App\Http\Requests\Payment\CreatePaymentRequest;
use App\Http\Requests\Payment\DestroyPaymentRequest;
use App\Http\Requests\Payment\EditPaymentRequest;
use App\Http\Requests\Payment\ShowPaymentRequest;
use App\Http\Requests\Payment\StorePaymentRequest;
use App\Http\Requests\Payment\UpdatePaymentRequest;
use App\Jobs\Entity\ActionEntity;
use App\Models\Payment;
use App\Repositories\BaseRepository;
use App\Transformers\PaymentTransformer;
use App\Utils\Traits\MakesHash;
use Illuminate\Http\Request;

/**
 * Class PaymentController
 * @package App\Http\Controllers\PaymentController
 */

class PaymentController extends BaseController
{

    use MakesHash;

    protected $entity_type = Payment::class;

    protected $entity_transformer = PaymentTransformer::class;

    /**
     * @var PaymentRepository
     */
    protected $payment_repo;


    /**
     * PaymentController constructor.
     *
     * @param      \App\Repositories\PaymentRepository  $payment_repo  The invoice repo
     */
    public function __construct(PaymentRepository $payment_repo)
    {

        parent::__construct();

        $this->payment_repo = $payment_repo;

    }

    /**
     * Show the list of Invoices
     *
     * @param      \App\Filters\PaymentFilters  $filters  The filters
     *
     * @return \Illuminate\Http\Response
     */
    public function index(PaymentFilters $filters)
    {
        
        $payments = Payment::filter($filters);
      
        return $this->listResponse($payments);

    }

    /**
     * Show the form for creating a new resource.
     *
     * @param      \App\Http\Requests\Payment\CreatePaymentRequest  $request  The request
     *
     * @return \Illuminate\Http\Response
     */
    public function create(CreatePaymentRequest $request)
    {

        $payment = PaymentFactory::create(auth()->user()->company()->id, auth()->user()->id);

        return $this->itemResponse($payment);

    }


    /**
     * Store a newly created resource in storage.
     *
     * @param      \App\Http\Requests\Payment\StorePaymentRequest  $request  The request
     *
     * @return \Illuminate\Http\Response
     */
    public function store(StorePaymentRequest $request)
    {
        
        $payment = $this->payment_repo->save($request, PaymentFactory::create(auth()->user()->company()->id, auth()->user()->id));

        return $this->itemResponse($payment);

    }

    /**
     * Display the specified resource.
     *
     * @param      \App\Http\Requests\Payment\ShowPaymentRequest  $request  The request
     * @param      \App\Models\Invoice                            $payment  The invoice
     *
     * @return \Illuminate\Http\Response
     */
    public function show(ShowPaymentRequest $request, Invoice $payment)
    {

        return $this->itemResponse($payment);

    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param      \App\Http\Requests\Payment\EditPaymentRequest  $request  The request
     * @param      \App\Models\Invoice                            $payment  The invoice
     *
     * @return \Illuminate\Http\Response
     */
    public function edit(EditPaymentRequest $request, Invoice $payment)
    {

        return $this->itemResponse($payment);

    }
    
    /**
     * Update the specified resource in storage.
     *
     * @param      \App\Http\Requests\Payment\UpdatePaymentRequest  $request  The request
     * @param      \App\Models\Invoice                              $payment  The invoice
     *
     * @return \Illuminate\Http\Response
     */
    public function update(UpdatePaymentRequest $request, Invoice $payment)
    {

        $payment = $this->payment_repo->save(request(), $payment);

        return $this->itemResponse($payment);

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param      \App\Http\Requests\Payment\DestroyPaymentRequest  $request  
     * @param      \App\Models\Invoice                               $payment  
     *
     * @return     \Illuminate\Http\Response
     */
    public function destroy(DestroyPaymentRequest $request, Invoice $payment)
    {

        $payment->delete();

        return response()->json([], 200);

    }

    /**
     * Perform bulk actions on the list view
     * 
     * @return Collection
     */
    public function bulk()
    {

        $action = request()->input('action');
        
        $ids = request()->input('ids');

        $payments = Payment::withTrashed()->find($ids);

        $payments->each(function ($payment, $key) use($action){

            if(auth()->user()->can('edit', $payment))
                $this->payment_repo->{$action}($payment);

        });

        //todo need to return the updated dataset
        return $this->listResponse(Payment::withTrashed()->whereIn('id', $ids));
        
    }

    public function action(ActionPaymentRequest $request, Invoice $payment, $action)
    {
        
        switch ($action) {
            case 'clone_to_invoice':
                $payment = CloneInvoiceFactory::create($payment, auth()->user()->id);
                return $this->itemResponse($payment);
                break;
            case 'clone_to_quote':
                $quote = CloneInvoiceToQuoteFactory::create($payment, auth()->user()->id);
                // todo build the quote transformer and return response here 
                break;
            case 'history':
                # code...
                break;
            case 'delivery_note':
                # code...
                break;
            case 'mark_paid':
                # code...
                break;
            case 'archive':
                # code...
                break;
            case 'delete':
                # code...
                break;
            case 'email':
                //dispatch email to queue
                break;

            default:
                # code...
                break;
        }
    }
    
}
