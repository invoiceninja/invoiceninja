<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Controllers;

use App\Factory\WebhookFactory;
use App\Filters\WebhookFilters;
use App\Http\Requests\Webhook\CreateWebhookRequest;
use App\Http\Requests\Webhook\DestroyWebhookRequest;
use App\Http\Requests\Webhook\EditWebhookRequest;
use App\Http\Requests\Webhook\RetryWebhookRequest;
use App\Http\Requests\Webhook\ShowWebhookRequest;
use App\Http\Requests\Webhook\StoreWebhookRequest;
use App\Http\Requests\Webhook\UpdateWebhookRequest;
use App\Jobs\Util\WebhookSingle;
use App\Models\Webhook;
use App\Repositories\BaseRepository;
use App\Transformers\WebhookTransformer;
use App\Utils\Traits\MakesHash;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class WebhookController extends BaseController
{
    use MakesHash;

    protected $entity_type = Webhook::class;

    protected $entity_transformer = WebhookTransformer::class;

    public $base_repo;

    public function __construct(BaseRepository $base_repo)
    {
        parent::__construct();

        $this->base_repo = $base_repo;
    }

    /**
     * @OA\Get(
     *      path="/api/v1/webhooks",
     *      operationId="getWebhooks",
     *      tags={"webhooks"},
     *      summary="Gets a list of Webhooks",
     *      description="Lists Webhooks, search and filters allow fine grained lists to be generated.
     *
     *      Query parameters can be added to performed more fine grained filtering of the Webhooks, these are handled by the WebhookFilters class which defines the methods available",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(ref="#/components/parameters/index"),
     *      @OA\Response(
     *          response=200,
     *          description="A list of Webhooks",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Webhook"),
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
     * @param WebhookFilters $filters
     * @return Response|mixed
     */
    public function index(WebhookFilters $filters)
    {
        $webhooks = Webhook::filter($filters);

        return $this->listResponse($webhooks);
    }

    /**
     * Display the specified resource.
     *
     * @param ShowWebhookRequest $request
     * @param Webhook $webhook
     * @return Response
     *
     *
     * @OA\Get(
     *      path="/api/v1/webhooks/{id}",
     *      operationId="showWebhook",
     *      tags={"webhooks"},
     *      summary="Shows a Webhook",
     *      description="Displays a Webhook by id",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The Webhook Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the Webhook object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Webhook"),
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
    public function show(ShowWebhookRequest $request, Webhook $webhook)
    {
        return $this->itemResponse($webhook);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param EditWebhookRequest $request
     * @param Webhook $webhook
     * @return Response
     *
     *
     * @OA\Get(
     *      path="/api/v1/webhooks/{id}/edit",
     *      operationId="editWebhook",
     *      tags={"webhooks"},
     *      summary="Shows a Webhook for editting",
     *      description="Displays a Webhook by id",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The Webhook Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the Webhook object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Webhook"),
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
    public function edit(EditWebhookRequest $request, Webhook $webhook)
    {
        return $this->itemResponse($webhook);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdateWebhookRequest $request
     * @param Webhook $webhook
     * @return Response
     *
     *
     *
     * @OA\Put(
     *      path="/api/v1/webhooks/{id}",
     *      operationId="updateWebhook",
     *      tags={"webhooks"},
     *      summary="Updates a Webhook",
     *      description="Handles the updating of a Webhook by id",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The Webhook Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the Webhook object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Webhook"),
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
    public function update(UpdateWebhookRequest $request, Webhook $webhook)
    {
        if ($request->entityIsDeleted($webhook)) {
            return $request->disallowUpdate();
        }

        $webhook->fill($request->all());
        $webhook->save();

        return $this->itemResponse($webhook);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @param CreateWebhookRequest $request
     * @return Response
     *
     *
     *
     * @OA\Get(
     *      path="/api/v1/webhooks/create",
     *      operationId="getWebhooksCreate",
     *      tags={"webhooks"},
     *      summary="Gets a new blank Webhook object",
     *      description="Returns a blank object with default values",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="A blank Webhook object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Webhook"),
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
    public function create(CreateWebhookRequest $request)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $webhook = WebhookFactory::create($user->company()->id, $user->id);
        $webhook->fill($request->all());
        $webhook->save();

        return $this->itemResponse($webhook);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param StoreWebhookRequest $request
     * @return Response
     *
     *
     *
     * @OA\Post(
     *      path="/api/v1/webhooks",
     *      operationId="storeWebhook",
     *      tags={"webhooks"},
     *      summary="Adds a Webhook",
     *      description="Adds an Webhook to a company",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the saved Webhook object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Webhook"),
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
    public function store(StoreWebhookRequest $request)
    {
        $event_id = $request->input('event_id');
        $target_url = $request->input('target_url');

        if (! in_array($event_id, Webhook::$valid_events)) {
            return response()->json('Invalid event', 400);
        }

        /** @var \App\Models\User $user */
        $user = auth()->user();

        $webhook = new Webhook();
        $webhook->company_id = $user->company()->id;
        $webhook->user_id = auth()->user()->id;
        $webhook->event_id = $event_id;
        $webhook->target_url = $target_url;
        $webhook->fill($request->all());
        $webhook->save();

        if (! $webhook->id) {
            return response()->json(ctrans('texts.create_webhook_failure'), 400);
        }

        return $this->itemResponse($webhook);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param DestroyWebhookRequest $request
     * @param Webhook $webhook
     * @return Response
     *
     *
     * @throws \Exception
     * @OA\Delete(
     *      path="/api/v1/webhooks/{id}",
     *      operationId="deleteWebhook",
     *      tags={"Webhooks"},
     *      summary="Deletes a Webhook",
     *      description="Handles the deletion of a Webhook by id",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The Webhook Hashed ID",
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
    public function destroy(DestroyWebhookRequest $request, Webhook $webhook)
    {
        //may not need these destroy routes as we are using actions to 'archive/delete'
        $webhook->delete();

        return $this->itemResponse($webhook);
    }

    /**
     * Perform bulk actions on the list view.
     *
     * @return Response
     *
     *
     * @OA\Post(
     *      path="/api/v1/webhooks/bulk",
     *      operationId="bulkWebhooks",
     *      tags={"webhooks"},
     *      summary="Performs bulk actions on an array of Webhooks",
     *      description="",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
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
     *          description="The Webhook User response",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Webhook"),
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

        $webhooks = Webhook::withTrashed()->find($this->transformKeys($ids));

        $webhooks->each(function ($webhook, $key) use ($action) {
            /** @var \App\Models\User $user */
            $user = auth()->user();

            if ($user->can('edit', $webhook)) {
                $this->base_repo->{$action}($webhook);
            }
        });

        return $this->listResponse(Webhook::withTrashed()->whereIn('id', $this->transformKeys($ids)));
    }

    public function retry(RetryWebhookRequest $request, Webhook $webhook)
    {
        $includes = '';

        match ($request->entity) {
            'invoice' => $includes = 'client',
            'payment' => $includes = 'invoices,client',
            'project' => $includes = 'client',
            'purchase_order' => $includes = 'vendor',
            'quote' => $includes = 'client',
            default => $includes = ''
        };

        $class = 'App\Models\\'.ucfirst(Str::camel($request->entity));

        $entity = $class::query()->withTrashed()->where('id', $this->decodePrimaryKey($request->entity_id))->company()->first();

        if (!$entity) {
            return response()->json(['message' => ctrans('texts.record_not_found')], 400);
        }

        /** @var \App\Models\User $user */
        $user = auth()->user();

        WebhookSingle::dispatchSync($webhook->id, $entity, $user->company()->db, $includes);

        return $this->itemResponse($webhook);
    }
}
