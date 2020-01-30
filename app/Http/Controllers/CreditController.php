<?php

namespace App\Http\Controllers;

use App\Events\Credit\CreditWasCreated;
use App\Events\Credit\CreditWasUpdated;
use App\Factory\CloneCreditFactory;
use App\Factory\CloneCreditToQuoteFactory;
use App\Factory\CreditFactory;
use App\Filters\CreditFilters;
use App\Http\Requests\Credit\ActionCreditRequest;
use App\Http\Requests\Credit\CreateCreditRequest;
use App\Http\Requests\Credit\DestroyCreditRequest;
use App\Http\Requests\Credit\EditCreditRequest;
use App\Http\Requests\Credit\ShowCreditRequest;
use App\Http\Requests\Credit\StoreCreditRequest;
use App\Http\Requests\Credit\UpdateCreditRequest;
use App\Jobs\Credit\StoreCredit;
use App\Jobs\Invoice\EmailCredit;
use App\Jobs\Invoice\MarkInvoicePaid;
use App\Models\Credit;
use App\Repositories\CreditRepository;
use App\Transformers\CreditTransformer;
use App\Utils\Traits\MakesHash;

class CreditController extends BaseController
{
    use MakesHash;

    protected $entity_type = Credit::class;

    protected $entity_transformer = CreditTransformer::class;

    protected $credit_repository;

    public function __construct(CreditRepository $credit_repository)
    {
        parent::__construct();

        $this->credit_repository = $credit_repository;
    }

    public function index(CreditFilters $filters)
    {
        $credits = Credit::filter($filters);
      
        return $this->listResponse($credits);
    }

    public function create(CreateCreditRequest $request)
    {
        $credit = CreditFactory::create(auth()->user()->company()->id, auth()->user()->id);

        return $this->itemResponse($credit);
    }

    public function store(StoreCreditRequest $request)
    {
        $credit = $this->credit_repository->save($request->all(), CreditFactory::create(auth()->user()->company()->id, auth()->user()->id));

        $credit = StoreCredit::dispatchNow($credit, $request->all(), $credit->company);

        event(new CreditWasCreated($credit, $credit->company));

        return $this->itemResponse($credit);
    }

    public function show(ShowCreditRequest $request, Credit $credit)
    {
        return $this->itemResponse($credit);
    }

    public function edit(EditCreditRequest $request, Credit $credit)
    {
        return $this->itemResponse($credit);
    }

    public function update(UpdateCreditRequest $request, Credit $credit)
    {
        if($request->entityIsDeleted($credit))
            return $request->disallowUpdate();

        $credit = $this->credit_repo->save($request->all(), $credit);

        event(new CreditWasUpdated($credit, $credit->company));

        return $this->itemResponse($credit);
    }

    public function destroy(DestroyCreditRequest $request, Credit $credit)
    {
        $credit->delete();

        return response()->json([], 200);
    }

    public function bulk()
    {
        $action = request()->input('action');
        
        $ids = request()->input('ids');

        $credits = Credit::withTrashed()->whereIn('id', $this->transformKeys($ids));

        if (!$credits) {
            return response()->json(['message' => 'No Credits Found']);
        }

        $credits->each(function ($credit, $key) use ($action) {
            if (auth()->user()->can('edit', $credit)) {
                $this->performAction($credit, $action, true);
            }
        });

        return $this->listResponse(Credit::withTrashed()->whereIn('id', $this->transformKeys($ids)));
    }

    public function action(ActionCreditRequest $request, Credit $credit, $action)
    {
        return $this->performAction($credit, $action);
    }

    private function performAction(Credit $credit, $action, $bulk = false)
    {
        /*If we are using bulk actions, we don't want to return anything */
        switch ($action) {
            case 'clone_to_credit':
                $credit = CloneCreditFactory::create($credit, auth()->user()->id);
                return $this->itemResponse($credit);
                break;
            case 'clone_to_quote':
                $quote = CloneCreditToQuoteFactory::create($credit, auth()->user()->id);
                // todo build the quote transformer and return response here
                break;
            case 'history':
                # code...
                break;
            case 'delivery_note':
                # code...
                break;
            case 'mark_paid':
                if ($credit->balance < 0 || $credit->status_id == Credit::STATUS_PAID || $credit->is_deleted === true) {
                    return $this->errorResponse(['message' => 'Credit cannot be marked as paid'], 400);
                }

                $credit = MarkInvoicePaid::dispatchNow($credit, $credit->company);

                if (!$bulk) {
                    return $this->itemResponse($credit);
                }
                break;
            case 'mark_sent':
                $credit->markSent();

                if (!$bulk) {
                    return $this->itemResponse($credit);
                }
                break;
            case 'download':
                    return response()->download(public_path($credit->pdf_file_path()));
                break;
            case 'archive':
                $this->credit_repo->archive($credit);

                if (!$bulk) {
                    return $this->listResponse($credit);
                }
                break;
            case 'delete':
                $this->credit_repo->delete($credit);

                if (!$bulk) {
                    return $this->listResponse($credit);
                }
                break;
            case 'email':
                EmailCredit::dispatch($credit, $credit->company);
                if (!$bulk) {
                    return response()->json(['message'=>'email sent'], 200);
                }
                break;

            default:
                return response()->json(['message' => "The requested action `{$action}` is not available."], 400);
                break;
        }
    }
}
