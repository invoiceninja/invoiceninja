<?php

namespace App\Http\Controllers;

use App\Events\Credit\CreditWasCreated;
use App\Events\Credit\CreditWasUpdated;
use App\Factory\CloneCreditFactory;
use App\Factory\CreditFactory;
use App\Filters\CreditFilters;
use App\Http\Requests\Credit\ActionCreditRequest;
use App\Http\Requests\Credit\CreateCreditRequest;
use App\Http\Requests\Credit\DestroyCreditRequest;
use App\Http\Requests\Credit\EditCreditRequest;
use App\Http\Requests\Credit\ShowCreditRequest;
use App\Http\Requests\Credit\StoreCreditRequest;
use App\Http\Requests\Credit\UpdateCreditRequest;
use App\Jobs\Entity\EmailEntity;
use App\Jobs\Invoice\EmailCredit;
use App\Models\Client;
use App\Models\Credit;
use App\Models\Invoice;
use App\Repositories\CreditRepository;
use App\Transformers\CreditTransformer;
use App\Utils\Ninja;
use App\Utils\TempFile;
use App\Utils\Traits\MakesHash;
use Illuminate\Http\Response;

/**
 * Class CreditController.
 */
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

    /**
     * Show the list of Credits.
     *
     * @param CreditFilters $filters  The filters
     *
     * @return Response
     *
     * @OA\Get(
     *      path="/api/v1/credits",
     *      operationId="getCredits",
     *      tags={"invoices"},
     *      summary="Gets a list of credits",
     *      description="Lists credits, search and filters allow fine grained lists to be generated.
     *
     *      Query parameters can be added to performed more fine grained filtering of the credits, these are handled by the CreditFilters class which defines the methods available",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="A list of credits",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Credit"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),

     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     */
    public function index(CreditFilters $filters)
    {
        $credits = Credit::filter($filters);

        return $this->listResponse($credits);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @param CreateCreditRequest $request  The request
     *
     * @return Response
     *
     *
     * @OA\Get(
     *      path="/api/v1/credits/create",
     *      operationId="getCreditsCreate",
     *      tags={"credits"},
     *      summary="Gets a new blank credit object",
     *      description="Returns a blank object with default values",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="A blank credit object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Credit"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     */
    public function create(CreateCreditRequest $request)
    {
        $credit = CreditFactory::create(auth()->user()->company()->id, auth()->user()->id);

        return $this->itemResponse($credit);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param StoreCreditRequest $request  The request
     *
     * @return Response
     *
     *
     * @OA\Post(
     *      path="/api/v1/credits",
     *      operationId="storeCredit",
     *      tags={"credits"},
     *      summary="Adds a credit",
     *      description="Adds an credit to the system",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the saved credit object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Credit"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     */
    public function store(StoreCreditRequest $request)
    {
        $client = Client::find($request->input('client_id'));

        $credit = $this->credit_repository->save($request->all(), CreditFactory::create(auth()->user()->company()->id, auth()->user()->id));

        $credit = $credit->service()
                         ->fillDefaults()
                         ->save();

        event(new CreditWasCreated($credit, $credit->company, Ninja::eventVars()));

        return $this->itemResponse($credit);
    }

    /**
     * Display the specified resource.
     *
     * @param ShowCreditRequest $request  The request
     * @param Credit $credit  The credit
     *
     * @return Response
     *
     *
     * @OA\Get(
     *      path="/api/v1/credits/{id}",
     *      operationId="showCredit",
     *      tags={"credits"},
     *      summary="Shows an credit",
     *      description="Displays an credit by id",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The Credit Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the credit object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Credit"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     */
    public function show(ShowCreditRequest $request, Credit $credit)
    {
        return $this->itemResponse($credit);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param EditCreditRequest $request The request
     * @param Credit $credit The credit
     *
     * @return Response
     *
     * @OA\Get(
     *      path="/api/v1/credits/{id}/edit",
     *      operationId="editCredit",
     *      tags={"credits"},
     *      summary="Shows an credit for editting",
     *      description="Displays an credit by id",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The Invoice Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the credit object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Invoice"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     */
    public function edit(EditCreditRequest $request, Credit $credit)
    {
        return $this->itemResponse($credit);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdateCreditRequest $request The request
     * @param Credit $credit
     * @return Response
     *
     *
     * @throws \ReflectionException
     * @OA\Put(
     *      path="/api/v1/credits/{id}",
     *      operationId="updateCredit",
     *      tags={"Credits"},
     *      summary="Updates an Credit",
     *      description="Handles the updating of an Credit by id",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The Credit Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the Credit object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Credit"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     */
    public function update(UpdateCreditRequest $request, Credit $credit)
    {
        if ($request->entityIsDeleted($credit)) {
            return $request->disallowUpdate();
        }

        $credit = $this->credit_repository->save($request->all(), $credit);

        event(new CreditWasUpdated($credit, $credit->company, Ninja::eventVars()));

        return $this->itemResponse($credit);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param DestroyCreditRequest $request
     * @param Credit $credit
     *
     * @return     Response
     *
     * @throws \Exception
     * @OA\Delete(
     *      path="/api/v1/credits/{id}",
     *      operationId="deleteCredit",
     *      tags={"credits"},
     *      summary="Deletes a credit",
     *      description="Handles the deletion of an credit by id",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The Credit Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns a HTTP status",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     */
    public function destroy(DestroyCreditRequest $request, Credit $credit)
    {
        $this->credit_repository->delete($credit);

        return $this->itemResponse($credit->fresh());
    }

    /**
     * Perform bulk actions on the list view.
     *
     * @return Collection
     *
     * @OA\Post(
     *      path="/api/v1/credits/bulk",
     *      operationId="bulkCredits",
     *      tags={"credits"},
     *      summary="Performs bulk actions on an array of credits",
     *      description="",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/index"),
     *      @OA\RequestBody(
     *         description="User credentials",
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 type="array",
     *                 @OA\Items(
     *                     type="integer",
     *                     description="Array of hashed IDs to be bulk 'actioned",
     *                     example="[0,1,2,3]",
     *                 ),
     *             )
     *         )
     *     ),
     *      @OA\Response(
     *          response=200,
     *          description="The Bulk Action response",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),

     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     */
    public function bulk()
    {
        $action = request()->input('action');

        $ids = request()->input('ids');

        $credits = Credit::withTrashed()->whereIn('id', $this->transformKeys($ids));

        if (! $credits) {
            return response()->json(['message' => ctrans('texts.no_credits_found')]);
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
            case 'history':
                // code...
                break;
            case 'mark_sent':
                $credit->service()->markSent()->save();

                if (! $bulk) {
                    return $this->itemResponse($credit);
                }
                break;
            case 'download':
                    return response()->streamDownload(function () use ($credit) {
                        echo file_get_contents($credit->pdf_file_path());
                    }, basename($credit->pdf_file_path()));
                //return response()->download(TempFile::path($credit->pdf_file_path()), basename($credit->pdf_file_path()));
              break;
            case 'archive':
                $this->credit_repository->archive($credit);

                if (! $bulk) {
                    return $this->listResponse($credit);
                }
                break;
            case 'restore':
                $this->credit_repository->restore($credit);

                if (! $bulk) {
                    return $this->listResponse($credit);
                }
                break;
            case 'delete':
                $this->credit_repository->delete($credit);

                if (! $bulk) {
                    return $this->listResponse($credit);
                }
                break;
            case 'email':
                // EmailCredit::dispatch($credit, $credit->company);

                $credit->invitations->load('contact.client.country', 'credit.client.country', 'credit.company')->each(function ($invitation) use ($credit) {
                    EmailEntity::dispatch($invitation, $credit->company, 'credit');
                });


                if (! $bulk) {
                    return response()->json(['message'=>'email sent'], 200);
                }
                break;

            default:
                return response()->json(['message' => ctrans('texts.action_unavailable', ['action' => $action])], 400);
                break;
        }
    }

    public function downloadPdf($invitation_key)
    {
        $invitation = $this->credit_repository->getInvitationByKey($invitation_key);
        // $contact = $invitation->contact;
        $credit = $invitation->credit;

        $file_path = $credit->service()->getCreditPdf($invitation);

        return response()->download($file_path);
    }
}
