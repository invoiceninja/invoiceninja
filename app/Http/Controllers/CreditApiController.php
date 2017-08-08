<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreditRequest;
use App\Http\Requests\CreateCreditRequest;
use App\Http\Requests\UpdateCreditRequest;
use App\Models\Invoice;
use App\Models\Credit;
use App\Ninja\Repositories\CreditRepository;
use Input;
use Response;

class CreditApiController extends BaseAPIController
{
    protected $creditRepo;

    protected $entityType = ENTITY_CREDIT;

    public function __construct(CreditRepository $creditRepo)
    {
        parent::__construct();

        $this->creditRepo = $creditRepo;
    }

    /**
     * @SWG\Get(
     *   path="/credits",
     *   summary="List credits",
     *   operationId="listCredits",
     *   tags={"credit"},
     *   @SWG\Response(
     *     response=200,
     *     description="A list of credits",
     *      @SWG\Schema(type="array", @SWG\Items(ref="#/definitions/Credit"))
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     */
    public function index()
    {
        $credits = Credit::scope()
                        ->withTrashed()
                        ->with(['client'])
                        ->orderBy('created_at', 'desc');

        return $this->listResponse($credits);
    }

    /**
     * @SWG\Get(
     *   path="/credits/{credit_id}",
     *   summary="Retrieve a credit",
     *   operationId="getCredit",
     *   tags={"credit"},
     *   @SWG\Parameter(
     *     in="path",
     *     name="credit_id",
     *     type="integer",
     *     required=true
     *   ),
     *   @SWG\Response(
     *     response=200,
     *     description="A single credit",
     *      @SWG\Schema(type="object", @SWG\Items(ref="#/definitions/Credit"))
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     */
    public function show(CreditRequest $request)
    {
        return $this->itemResponse($request->entity());
    }

    /**
     * @SWG\Post(
     *   path="/credits",
     *   summary="Create a credit",
     *   operationId="createCredit",
     *   tags={"credit"},
     *   @SWG\Parameter(
     *     in="body",
     *     name="credit",
     *     @SWG\Schema(ref="#/definitions/Credit")
     *   ),
     *   @SWG\Response(
     *     response=200,
     *     description="New credit",
     *      @SWG\Schema(type="object", @SWG\Items(ref="#/definitions/Credit"))
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     */
    public function store(CreateCreditRequest $request)
    {
        $credit = $this->creditRepo->save($request->input());

        return $this->itemResponse($credit);
    }

    /**
     * @SWG\Put(
     *   path="/credits/{credit_id}",
     *   summary="Update a credit",
     *   operationId="updateCredit",
     *   tags={"credit"},
     *   @SWG\Parameter(
     *     in="path",
     *     name="credit_id",
     *     type="integer",
     *     required=true
     *   ),
     *   @SWG\Parameter(
     *     in="body",
     *     name="credit",
     *     @SWG\Schema(ref="#/definitions/Credit")
     *   ),
     *   @SWG\Response(
     *     response=200,
     *     description="Updated credit",
     *      @SWG\Schema(type="object", @SWG\Items(ref="#/definitions/Credit"))
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     *
     * @param mixed $publicId
     */
    public function update(UpdateCreditRequest $request, $publicId)
    {
        if ($request->action) {
            return $this->handleAction($request);
        }

        $data = $request->input();
        $data['public_id'] = $publicId;
        $credit = $this->creditRepo->save($data, $request->entity());

        return $this->itemResponse($credit);
    }

    /**
     * @SWG\Delete(
     *   path="/credits/{credit_id}",
     *   summary="Delete a credit",
     *   operationId="deleteCredit",
     *   tags={"credit"},
     *   @SWG\Parameter(
     *     in="path",
     *     name="credit_id",
     *     type="integer",
     *     required=true
     *   ),
     *   @SWG\Response(
     *     response=200,
     *     description="Deleted credit",
     *      @SWG\Schema(type="object", @SWG\Items(ref="#/definitions/Credit"))
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     */
    public function destroy(UpdateCreditRequest $request)
    {
        $credit = $request->entity();

        $this->creditRepo->delete($credit);

        return $this->itemResponse($credit);
    }
}
