<?php

namespace App\Http\Controllers;

use App\Factory\QuoteFactory;
use App\Filters\QuoteFilters;
use App\Http\Requests\Quote\ActionQuoteRequest;
use App\Http\Requests\Quote\CreateQuoteRequest;
use App\Http\Requests\Quote\DestroyQuoteRequest;
use App\Http\Requests\Quote\EditQuoteRequest;
use App\Http\Requests\Quote\ShowQuoteRequest;
use App\Http\Requests\Quote\StoreQuoteRequest;
use App\Http\Requests\Quote\UpdateQuoteRequest;
use App\Models\Quote;
use App\Repositories\QuoteRepository;
use App\Transformers\QuoteTransformer;
use App\Utils\Traits\MakesHash;
use Illuminate\Http\Request;

/**
 * Class QuoteController
 * @package App\Http\Controllers\QuoteController
 */

class QuoteController extends BaseController
{

    use MakesHash;

    protected $entity_type = Quote::class;

    protected $entity_transformer = QuoteTransformer::class;

    /**
     * @var QuoteRepository
     */
    protected $quote_repo;

    protected $base_repo;

    /**
     * QuoteController constructor.
     *
     * @param      \App\Repositories\QuoteRepository  $Quote_repo  The Quote repo
     */
    public function __construct(QuoteRepository $quote_repo)
    {

        parent::__construct();

        $this->quote_repo = $quote_repo;

    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(QuoteFilters $filters)
    {
        
        $quotes = Quote::filter($filters);
      
        return $this->listResponse($quotes);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(CreateQuoteRequest $request)
    {

        $quote = QuoteFactory::create(auth()->user()->company()->id, auth()->user()->id);

        return $this->itemResponse($quote);

    }

    /**
     * Store a newly created resource in storage.
     *
     * @param      \App\Http\Requests\Quote\StoreQuoteRequest  $request  The request
     *
     * @return \Illuminate\Http\Response
     */
    public function store(StoreQuoteRequest $request)
    {
        
        $quote = $this->quote_repo->save($request, QuoteFactory::create(auth()->user()->company()->id, auth()->user()->id));

        return $this->itemResponse($quote);

    }

    /**
     * Display the specified resource.
     *
     * @param      \App\Http\Requests\Quote\ShowQuoteRequest  $request  The request
     * @param      \App\Models\Quote                            $quote  The quote
     *
     * @return \Illuminate\Http\Response
     */
    public function show(ShowQuoteRequest $request, Quote $quote)
    {

        return $this->itemResponse($quote);

    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param      \App\Http\Requests\Quote\EditQuoteRequest  $request  The request
     * @param      \App\Models\Quote                            $quote  The quote
     *
     * @return \Illuminate\Http\Response
     */
    public function edit(EditQuoteRequest $request, Quote $quote)
    {

        return $this->itemResponse($quote);

    }
    
    /**
     * Update the specified resource in storage.
     *
     * @param      \App\Http\Requests\Quote\UpdateQuoteRequest  $request  The request
     * @param      \App\Models\Quote                              $quote  The quote
     *
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateQuoteRequest $request, Quote $quote)
    {

        $quote = $this->quote_repo->save(request(), $quote);

        return $this->itemResponse($quote);

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param      \App\Http\Requests\Quote\DestroyQuoteRequest  $request  
     * @param      \App\Models\Quote                               $quote  
     *
     * @return     \Illuminate\Http\Response
     */
    public function destroy(DestroyQuoteRequest $request, Quote $quote)
    {

        $quote->delete();

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

        $quotes = Quote::withTrashed()->find($ids);

        $quotes->each(function ($quote, $key) use($action){

            if(auth()->user()->can('edit', $quote))
                $this->quote_repo->{$action}($quote);

        });

        //todo need to return the updated dataset
        return $this->listResponse(Quote::withTrashed()->whereIn('id', $ids));
        
    }

    public function action(ActionQuoteRequest $request, Quote $quote, $action)
    {
        
        switch ($action) {
            case 'clone_to_invoice':
                //$quote = CloneInvoiceFactory::create($quote, auth()->user()->id);
                return $this->itemResponse($quote);
                break;
            case 'clone_to_quote':
                //$quote = CloneInvoiceToQuoteFactory::create($quote, auth()->user()->id);
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