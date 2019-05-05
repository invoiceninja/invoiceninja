<?php

namespace App\Http\Controllers;

use App\Factory\CloneRecurringQuoteFactory;
use App\Factory\CloneRecurringQuoteToQuoteFactory;
use App\Factory\RecurringQuoteFactory;
use App\Filters\RecurringQuoteFilters;
use App\Http\Requests\RecurringQuote\ActionRecurringQuoteRequest;
use App\Http\Requests\RecurringQuote\CreateRecurringQuoteRequest;
use App\Http\Requests\RecurringQuote\DestroyRecurringQuoteRequest;
use App\Http\Requests\RecurringQuote\EditRecurringQuoteRequest;
use App\Http\Requests\RecurringQuote\ShowRecurringQuoteRequest;
use App\Http\Requests\RecurringQuote\StoreRecurringQuoteRequest;
use App\Http\Requests\RecurringQuote\UpdateRecurringQuoteRequest;
use App\Jobs\Entity\ActionEntity;
use App\Models\RecurringQuote;
use App\Repositories\BaseRepository;
use App\Repositories\RecurringQuoteRepository;
use App\Transformers\RecurringQuoteTransformer;
use App\Utils\Traits\MakesHash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Class RecurringQuoteController
 * @package App\Http\Controllers\RecurringQuoteController
 */

class RecurringQuoteController extends BaseController
{

    use MakesHash;

    protected $entity_type = RecurringQuote::class;

    protected $entity_transformer = RecurringQuoteTransformer::class;

    /**
     * @var RecurringQuoteRepository
     */
    protected $recurring_quote_repo;

    protected $base_repo;

    /**
     * RecurringQuoteController constructor.
     *
     * @param      \App\Repositories\RecurringQuoteRepository  $recurring_quote_repo  The RecurringQuote repo
     */
    public function __construct(RecurringQuoteRepository $recurring_quote_repo)
    {

        parent::__construct();

        $this->recurring_quote_repo = $recurring_quote_repo;

    }

    /**
     * Show the list of recurring_invoices
     *
     * @param      \App\Filters\RecurringQuoteFilters  $filters  The filters
     *
     * @return \Illuminate\Http\Response
     */
    public function index(RecurringQuoteFilters $filters)
    {
        
        $recurring_quotes = RecurringQuote::filter($filters);
      
        return $this->listResponse($recurring_quotes);

    }

    /**
     * Show the form for creating a new resource.
     *
     * @param      \App\Http\Requests\RecurringQuote\CreateRecurringQuoteRequest  $request  The request
     *
     * @return \Illuminate\Http\Response
     */
    public function create(CreateRecurringQuoteRequest $request)
    {

        $recurring_quote = RecurringQuoteFactory::create(auth()->user()->company()->id, auth()->user()->id);

        return $this->itemResponse($recurring_quote);

    }


    /**
     * Store a newly created resource in storage.
     *
     * @param      \App\Http\Requests\RecurringQuote\StoreRecurringQuoteRequest  $request  The request
     *
     * @return \Illuminate\Http\Response
     */
    public function store(StoreRecurringQuoteRequest $request)
    {
        
        $recurring_quote = $this->recurring_quote_repo->save($request, RecurringQuoteFactory::create(auth()->user()->company()->id, auth()->user()->id));

        return $this->itemResponse($recurring_quote);

    }

    /**
     * Display the specified resource.
     *
     * @param      \App\Http\Requests\RecurringQuote\ShowRecurringQuoteRequest  $request  The request
     * @param      \App\Models\RecurringQuote                            $recurring_quote  The RecurringQuote
     *
     * @return \Illuminate\Http\Response
     */
    public function show(ShowRecurringQuoteRequest $request, RecurringQuote $recurring_quote)
    {

        return $this->itemResponse($recurring_quote);

    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param      \App\Http\Requests\RecurringQuote\EditRecurringQuoteRequest  $request  The request
     * @param      \App\Models\RecurringQuote                            $recurring_quote  The RecurringQuote
     *
     * @return \Illuminate\Http\Response
     */
    public function edit(EditRecurringQuoteRequest $request, RecurringQuote $recurring_quote)
    {

        return $this->itemResponse($recurring_quote);

    }
    
    /**
     * Update the specified resource in storage.
     *
     * @param      \App\Http\Requests\RecurringQuote\UpdateRecurringQuoteRequest  $request  The request
     * @param      \App\Models\RecurringQuote                              $recurring_quote  The RecurringQuote
     *
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateRecurringQuoteRequest $request, RecurringQuote $recurring_quote)
    {

        $recurring_quote = $this->recurring_quote_repo->save(request(), $recurring_quote);

        return $this->itemResponse($recurring_quote);

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param      \App\Http\Requests\RecurringQuote\DestroyRecurringQuoteRequest  $request  
     * @param      \App\Models\RecurringQuote                               $recurring_quote  
     *
     * @return     \Illuminate\Http\Response
     */
    public function destroy(DestroyRecurringQuoteRequest $request, RecurringQuote $recurring_quote)
    {

        $recurring_quote->delete();

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

        $recurring_quotes = RecurringQuote::withTrashed()->find($ids);

        $recurring_quotes->each(function ($recurring_quote, $key) use($action){

            if(auth()->user()->can('edit', $recurring_quote))
                $this->recurring_quote_repo->{$action}($recurring_quote);

        });

        //todo need to return the updated dataset
        return $this->listResponse(RecurringQuote::withTrashed()->whereIn('id', $ids));
        
    }

    public function action(ActionRecurringQuoteRequest $request, RecurringQuote $recurring_quote, $action)
    {
        
        switch ($action) {
            case 'clone_to_RecurringQuote':
          //      $recurring_invoice = CloneRecurringQuoteFactory::create($recurring_invoice, auth()->user()->id);
          //      return $this->itemResponse($recurring_invoice);
                break;
            case 'clone_to_quote':
            //    $quote = CloneRecurringQuoteToQuoteFactory::create($recurring_invoice, auth()->user()->id);
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
