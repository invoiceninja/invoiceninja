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

use App\Events\BillingSubscription\BillingSubscriptionWasCreated;
use App\Factory\BillingSubscriptionFactory;
use App\Http\Requests\BillingSubscription\CreateBillingSubscriptionRequest;
use App\Http\Requests\BillingSubscription\DestroyBillingSubscriptionRequest;
use App\Http\Requests\BillingSubscription\EditBillingSubscriptionRequest;
use App\Http\Requests\BillingSubscription\ShowBillingSubscriptionRequest;
use App\Http\Requests\BillingSubscription\StoreBillingSubscriptionRequest;
use App\Http\Requests\BillingSubscription\UpdateBillingSubscriptionRequest;
use App\Models\BillingSubscription;
use App\Repositories\BillingSubscriptionRepository;
use App\Transformers\BillingSubscriptionTransformer;
use App\Utils\Ninja;

class BillingSubscriptionController extends BaseController
{
    protected $entity_type = BillingSubscription::class;

    protected $entity_transformer = BillingSubscriptionTransformer::class;

    protected $billing_subscription_repo;

    public function __construct(BillingSubscriptionRepository $billing_subscription_repo)
    {
        parent::__construct();

        $this->billing_subscription_repo = $billing_subscription_repo;
    }

    /**
     * Show the list of BillingSubscriptions.
     *     
     * @return Response
     *
     * @OA\Get(
     *      path="/api/v1/billing_subscriptions",
     *      operationId="getBillingSubscriptions",
     *      tags={"billing_subscriptions"},
     *      summary="Gets a list of billing_subscriptions",
     *      description="Lists billing_subscriptions.",
     *      
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="A list of billing_subscriptions",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/BillingSubscription"),
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
        $billing_subscriptions = BillingSubscription::query()->company();

        return $this->listResponse($billing_subscriptions);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @param CreateBillingSubscriptionRequest $request  The request
     *
     * @return Response
     *
     *
     * @OA\Get(
     *      path="/api/v1/billing_subscriptions/create",
     *      operationId="getBillingSubscriptionsCreate",
     *      tags={"billing_subscriptions"},
     *      summary="Gets a new blank billing_subscriptions object",
     *      description="Returns a blank object with default values",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="A blank billing_subscriptions object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/BillingSubscription"),
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
    public function create(CreateBillingSubscriptionRequest $request): \Illuminate\Http\Response
    {
        $billing_subscription = BillingSubscriptionFactory::create(auth()->user()->company()->id, auth()->user()->id);

        return $this->itemResponse($billing_subscription);
    }

    public function store(StoreBillingSubscriptionRequest $request): \Illuminate\Http\Response
    {
        $billing_subscription = $this->billing_subscription_repo->save($request->all(), BillingSubscriptionFactory::create(auth()->user()->company()->id, auth()->user()->id));

        event(new BillingsubscriptionWasCreated($billing_subscription, $billing_subscription->company, Ninja::eventVars()));

        return $this->itemResponse($billing_subscription);
    }

    public function show(ShowBillingSubscriptionRequest $request, BillingSubscription $billing_subscription): \Illuminate\Http\Response
    {
        return $this->itemResponse($billing_subscription);
    }

    public function edit(EditBillingSubscriptionRequest $request, BillingSubscription $billing_subscription): \Illuminate\Http\Response
    {
        return $this->itemResponse($billing_subscription);
    }

    public function update(UpdateBillingSubscriptionRequest $request, BillingSubscription $billing_subscription)
    {
        if ($request->entityIsDeleted($billing_subscription)) {
            return $request->disallowUpdate();
        }

        $billing_subscription = $this->billing_subscription_repo->save($request->all(), $billing_subscription);

        return $this->itemResponse($billing_subscription);
    }

    public function destroy(DestroyBillingSubscriptionRequest $request, BillingSubscription $billing_subscription): \Illuminate\Http\Response
    {
        $this->billing_subscription_repo->delete($billing_subscription);

        return $this->listResponse($billing_subscription->fresh());
    }
}
