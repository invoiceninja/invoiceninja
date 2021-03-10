<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Http\Controllers;

use App\Events\ClientSubscription\ClientSubscriptionWasCreated;
use App\Factory\ClientSubscriptionFactory;
use App\Http\Requests\ClientSubscription\CreateClientSubscriptionRequest;
use App\Http\Requests\ClientSubscription\DestroyClientSubscriptionRequest;
use App\Http\Requests\ClientSubscription\EditClientSubscriptionRequest;
use App\Http\Requests\ClientSubscription\ShowClientSubscriptionRequest;
use App\Http\Requests\ClientSubscription\StoreClientSubscriptionRequest;
use App\Http\Requests\ClientSubscription\UpdateClientSubscriptionRequest;
use App\Models\ClientSubscription;
use App\Repositories\ClientSubscriptionRepository;
use App\Transformers\ClientSubscriptionTransformer;
use App\Utils\Ninja;

class ClientSubscriptionController extends BaseController
{
    protected $entity_type = ClientSubscription::class;

    protected $entity_transformer = ClientSubscriptionTransformer::class;

    protected $client_subscription_repo;

    public function __construct(ClientSubscriptionRepository $client_subscription_repo)
    {
        parent::__construct();

        $this->client_subscription_repo = $client_subscription_repo;
    }

    /**
     * Show the list of ClientSubscriptions.
     *     
     * @return Response
     *
     * @OA\Get(
     *      path="/api/v1/client_subscriptions",
     *      operationId="getClientSubscriptions",
     *      tags={"client_subscriptions"},
     *      summary="Gets a list of client_subscriptions",
     *      description="Lists client_subscriptions.",
     *      
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="A list of client_subscriptions",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/ClientSubscription"),
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
    
    public function index(): \Illuminate\Http\Response
    {
        $client_subscriptions = ClientSubscription::query()->company();

        return $this->listResponse($client_subscriptions);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @param CreateClientSubscriptionRequest $request  The request
     *
     * @return Response
     *
     *
     * @OA\Get(
     *      path="/api/v1/client_subscriptions/create",
     *      operationId="getClientSubscriptionsCreate",
     *      tags={"client_subscriptions"},
     *      summary="Gets a new blank client_subscriptions object",
     *      description="Returns a blank object with default values",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="A blank client_subscriptions object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/ClientSubscription"),
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
    public function create(CreateClientSubscriptionRequest $request): \Illuminate\Http\Response
    {
        $client_subscription = ClientSubscriptionFactory::create(auth()->user()->company()->id, auth()->user()->id);

        return $this->itemResponse($client_subscription);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param StoreClientSubscriptionRequest $request  The request
     *
     * @return Response
     *
     *
     * @OA\Post(
     *      path="/api/v1/client_subscriptions",
     *      operationId="storeClientSubscription",
     *      tags={"client_subscriptions"},
     *      summary="Adds a client_subscriptions",
     *      description="Adds an client_subscriptions to the system",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the saved client_subscriptions object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/ClientSubscription"),
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
    public function store(StoreClientSubscriptionRequest $request): \Illuminate\Http\Response
    {
        $client_subscription = $this->client_subscription_repo->save($request->all(), ClientSubscriptionFactory::create(auth()->user()->company()->id, auth()->user()->id));

        event(new ClientsubscriptionWasCreated($client_subscription, $client_subscription->company, Ninja::eventVars()));

        return $this->itemResponse($client_subscription);
    }

    /**
     * Display the specified resource.
     *
     * @param ShowClientSubscriptionRequest $request  The request
     * @param Invoice $client_subscription  The invoice
     *
     * @return Response
     *
     *
     * @OA\Get(
     *      path="/api/v1/client_subscriptions/{id}",
     *      operationId="showClientSubscription",
     *      tags={"client_subscriptions"},
     *      summary="Shows an client_subscriptions",
     *      description="Displays an client_subscriptions by id",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The ClientSubscription Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the ClientSubscription object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/ClientSubscription"),
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
    public function show(ShowClientSubscriptionRequest $request, ClientSubscription $client_subscription): \Illuminate\Http\Response
    {
        return $this->itemResponse($client_subscription);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param EditClientSubscriptionRequest $request  The request
     * @param Invoice $client_subscription  The invoice
     *
     * @return Response
     *
     * @OA\Get(
     *      path="/api/v1/client_subscriptions/{id}/edit",
     *      operationId="editClientSubscription",
     *      tags={"client_subscriptions"},
     *      summary="Shows an client_subscriptions for editting",
     *      description="Displays an client_subscriptions by id",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The ClientSubscription Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the invoice object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/ClientSubscription"),
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
    public function edit(EditClientSubscriptionRequest $request, ClientSubscription $client_subscription): \Illuminate\Http\Response
    {
        return $this->itemResponse($client_subscription);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdateClientSubscriptionRequest $request  The request
     * @param ClientSubscription $client_subscription  The invoice
     *
     * @return Response
     *
     *
     * @OA\Put(
     *      path="/api/v1/client_subscriptions/{id}",
     *      operationId="updateClientSubscription",
     *      tags={"client_subscriptions"},
     *      summary="Updates an client_subscriptions",
     *      description="Handles the updating of an client_subscriptions by id",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The ClientSubscription Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the client_subscriptions object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/ClientSubscription"),
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
    public function update(UpdateClientSubscriptionRequest $request, ClientSubscription $client_subscription)
    {
        if ($request->entityIsDeleted($client_subscription)) {
            return $request->disallowUpdate();
        }

        $client_subscription = $this->client_subscription_repo->save($request->all(), $client_subscription);

        return $this->itemResponse($client_subscription);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param DestroyClientSubscriptionRequest $request
     * @param ClientSubscription $invoice
     *
     * @return     Response
     *
     * @throws \Exception
     * @OA\Delete(
     *      path="/api/v1/client_subscriptions/{id}",
     *      operationId="deleteClientSubscription",
     *      tags={"client_subscriptions"},
     *      summary="Deletes a client_subscriptions",
     *      description="Handles the deletion of an client_subscriptions by id",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The ClientSubscription Hashed ID",
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
    public function destroy(DestroyClientSubscriptionRequest $request, ClientSubscription $client_subscription): \Illuminate\Http\Response
    {
        $this->client_subscription_repo->delete($client_subscription);

        return $this->itemResponse($client_subscription->fresh());
    }
}
