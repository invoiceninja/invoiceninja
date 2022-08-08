<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Company;
use App\Models\User;
use App\Transformers\ArraySerializer;
use App\Transformers\EntityTransformer;
use App\Utils\Ninja;
use App\Utils\Statics;
use App\Utils\Traits\AppSetup;
use App\Utils\TruthSource;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use League\Fractal\Manager;
use League\Fractal\Pagination\IlluminatePaginatorAdapter;
use League\Fractal\Resource\Collection;
use League\Fractal\Resource\Item;
use League\Fractal\Serializer\JsonApiSerializer;

/**
 * Class BaseController.
 */
class BaseController extends Controller
{
    use AppSetup;

    /**
     * Passed from the parent when we need to force
     * includes internally rather than externally via
     * the $_REQUEST 'include' variable.
     *
     * @var array
     */
    public $forced_includes;

    /**
     * Passed from the parent when we need to force
     * the key of the response object.
     * @var string
     */
    public $forced_index;

    /**
     * Fractal manager.
     * @var object
     */
    protected $manager;

    private $first_load = [
          'account',
          'user.company_user',
          'token.company_user',
          'company.activities',
          'company.designs.company',
          'company.task_statuses',
          'company.expense_categories',
          'company.documents',
          'company.users.company_user',
          'company.clients.contacts.company',
          'company.clients.gateway_tokens',
          'company.clients.documents',
          'company.company_gateways.gateway',
          'company.credits.invitations.contact',
          'company.credits.invitations.company',
          'company.credits.documents',
          'company.expenses.documents',
          'company.groups.documents',
          'company.invoices.invitations.contact',
          'company.invoices.invitations.company',
          'company.purchase_orders.invitations',
          'company.invoices.documents',
          'company.products',
          'company.products.documents',
          'company.payments.paymentables',
          'company.payments.documents',
          'company.purchase_orders.documents',
          'company.payment_terms.company',
          'company.projects.documents',
          'company.recurring_expenses',
          'company.recurring_invoices',
          'company.recurring_invoices.invitations.contact',
          'company.recurring_invoices.invitations.company',
          'company.recurring_invoices.documents',
          'company.quotes.invitations.contact',
          'company.quotes.invitations.company',
          'company.quotes.documents',
          'company.tasks.documents',
          'company.subscriptions',
          'company.tax_rates',
          'company.tokens_hashed',
          'company.vendors.contacts.company',
          'company.vendors.documents',
          'company.webhooks',
          'company.system_logs',
        ];

    private $mini_load = [
        'account',
        'user.company_user',
        'token',
        'company.activities',
        'company.tax_rates',
        'company.documents',
        'company.company_gateways.gateway',
        'company.users.company_user',
        'company.task_statuses',
        'company.payment_terms',
        'company.groups',
        'company.designs.company',
        'company.expense_categories',
        'company.subscriptions',
    ];

    public function __construct()
    {
        $this->manager = new Manager();

        $this->forced_includes = [];

        $this->forced_index = 'data';
    }

    private function buildManager()
    {
        $include = '';

        if (request()->has('first_load') && request()->input('first_load') == 'true') {
            $include = implode(',', array_merge($this->forced_includes, $this->getRequestIncludes([])));
        } elseif (request()->input('include') !== null) {
            $include = array_merge($this->forced_includes, explode(',', request()->input('include')));
            $include = implode(',', $include);
        } elseif (count($this->forced_includes) >= 1) {
            $include = implode(',', $this->forced_includes);
        }

        $this->manager->parseIncludes($include);

        $this->serializer = request()->input('serializer') ?: EntityTransformer::API_SERIALIZER_ARRAY;

        if ($this->serializer === EntityTransformer::API_SERIALIZER_JSON) {
            $this->manager->setSerializer(new JsonApiSerializer());
        } else {
            $this->manager->setSerializer(new ArraySerializer());
        }
    }

    /**
     * Catch all fallback route
     * for non-existant route.
     */
    public function notFound()
    {
        return response()->json(['message' => ctrans('texts.api_404')], 404)
                         ->header('X-API-VERSION', config('ninja.minimum_client_version'))
                         ->header('X-APP-VERSION', config('ninja.app_version'));
    }

    /**
     * 404 for the client portal.
     * @return Response 404 response
     */
    public function notFoundClient()
    {
        abort(404, 'Page not found in the client portal.');
    }

    public function notFoundVendor()
    {
        abort(404, 'Page not found in the vendor portal.');
    }

    /**
     * API Error response.
     * @param string $message The return error message
     * @param int $httpErrorCode 404/401/403 etc
     * @return Response               The JSON response
     * @throws BindingResolutionException
     */
    protected function errorResponse($message, $httpErrorCode = 400)
    {
        $error['error'] = $message;

        $error = json_encode($error, JSON_PRETTY_PRINT);

        $headers = self::getApiHeaders();

        return response()->make($error, $httpErrorCode, $headers);
    }

    protected function refreshResponse($query)
    {
        $user = auth()->user();

        $this->manager->parseIncludes($this->first_load);

        $this->serializer = request()->input('serializer') ?: EntityTransformer::API_SERIALIZER_ARRAY;

        if ($this->serializer === EntityTransformer::API_SERIALIZER_JSON) {
            $this->manager->setSerializer(new JsonApiSerializer());
        } else {
            $this->manager->setSerializer(new ArraySerializer());
        }

        $transformer = new $this->entity_transformer($this->serializer);
        $updated_at = request()->has('updated_at') ? request()->input('updated_at') : 0;

        if ($user->getCompany()->is_large && $updated_at == 0) {
            $updated_at = time();
        }

        $updated_at = date('Y-m-d H:i:s', $updated_at);

        $query->with(
            [
                'company' => function ($query) use ($updated_at, $user) {
                    $query->whereNotNull('updated_at')->with('documents', 'users');
                },
                'company.clients' => function ($query) use ($updated_at, $user) {
                    $query->where('clients.updated_at', '>=', $updated_at)->with('contacts.company', 'gateway_tokens', 'documents');

                    if (! $user->hasPermission('view_client')) {
                        $query->where('clients.user_id', $user->id)->orWhere('clients.assigned_user_id', $user->id);
                    }
                },
                'company.company_gateways' => function ($query) use ($user) {
                    $query->whereNotNull('updated_at')->with('gateway');

                    if (! $user->isAdmin()) {
                        $query->where('company_gateways.user_id', $user->id);
                    }
                },
                'company.credits'=> function ($query) use ($updated_at, $user) {
                    $query->where('updated_at', '>=', $updated_at)->with('invitations', 'documents');

                    if (! $user->hasPermission('view_credit')) {
                        $query->where('credits.user_id', $user->id)->orWhere('credits.assigned_user_id', $user->id);
                    }
                },
                'company.designs'=> function ($query) use ($updated_at, $user) {
                    $query->where('updated_at', '>=', $updated_at)->with('company');

                    if (! $user->isAdmin()) {
                        $query->where('designs.user_id', $user->id);
                    }
                },
                'company.documents'=> function ($query) use ($updated_at, $user) {
                    $query->where('updated_at', '>=', $updated_at);
                },
                'company.expenses'=> function ($query) use ($updated_at, $user) {
                    $query->where('updated_at', '>=', $updated_at)->with('documents');

                    if (! $user->hasPermission('view_expense')) {
                        $query->where('expenses.user_id', $user->id)->orWhere('expenses.assigned_user_id', $user->id);
                    }
                },
                'company.groups' => function ($query) use ($updated_at, $user) {
                    $query->whereNotNull('updated_at')->with('documents');

                // if(!$user->isAdmin())
                    //   $query->where('group_settings.user_id', $user->id);
                },
                'company.invoices'=> function ($query) use ($updated_at, $user) {
                    $query->where('updated_at', '>=', $updated_at)->with('invitations', 'documents');

                    if (! $user->hasPermission('view_invoice')) {
                        $query->where('invoices.user_id', $user->id)->orWhere('invoices.assigned_user_id', $user->id);
                    }
                },
                'company.payments'=> function ($query) use ($updated_at, $user) {
                    $query->where('updated_at', '>=', $updated_at)->with('paymentables', 'documents');

                    if (! $user->hasPermission('view_payment')) {
                        $query->where('payments.user_id', $user->id)->orWhere('payments.assigned_user_id', $user->id);
                    }
                },
                'company.payment_terms'=> function ($query) use ($updated_at, $user) {
                    $query->whereNotNull('updated_at');

                    if (! $user->isAdmin()) {
                        $query->where('payment_terms.user_id', $user->id);
                    }
                },
                'company.products' => function ($query) use ($updated_at, $user) {
                    $query->where('updated_at', '>=', $updated_at)->with('documents');

                    if (! $user->hasPermission('view_product')) {
                        $query->where('products.user_id', $user->id)->orWhere('products.assigned_user_id', $user->id);
                    }
                },
                'company.projects'=> function ($query) use ($updated_at, $user) {
                    $query->where('updated_at', '>=', $updated_at)->with('documents');

                    if (! $user->hasPermission('view_project')) {
                        $query->where('projects.user_id', $user->id)->orWhere('projects.assigned_user_id', $user->id);
                    }
                },
                'company.purchase_orders'=> function ($query) use ($updated_at, $user) {
                    $query->where('updated_at', '>=', $updated_at)->with('documents');

                    if (! $user->hasPermission('view_purchase_order')) {
                        $query->where('purchase_orders.user_id', $user->id)->orWhere('purchase_orders.assigned_user_id', $user->id);
                    }
                },
                'company.quotes'=> function ($query) use ($updated_at, $user) {
                    $query->where('updated_at', '>=', $updated_at)->with('invitations', 'documents');

                    if (! $user->hasPermission('view_quote')) {
                        $query->where('quotes.user_id', $user->id)->orWhere('quotes.assigned_user_id', $user->id);
                    }
                },
                'company.recurring_invoices'=> function ($query) use ($updated_at, $user) {
                    $query->where('updated_at', '>=', $updated_at)->with('invitations', 'documents', 'client.gateway_tokens', 'client.group_settings', 'client.company');

                    if (! $user->hasPermission('view_recurring_invoice')) {
                        $query->where('recurring_invoices.user_id', $user->id)->orWhere('recurring_invoices.assigned_user_id', $user->id);
                    }
                },
                'company.recurring_expenses'=> function ($query) use ($updated_at, $user) {
                    $query->where('updated_at', '>=', $updated_at)->with('documents');

                    if (! $user->hasPermission('view_recurring_expense')) {
                        $query->where('recurring_expenses.user_id', $user->id)->orWhere('recurring_expenses.assigned_user_id', $user->id);
                    }
                },
                'company.tasks'=> function ($query) use ($updated_at, $user) {
                    $query->where('updated_at', '>=', $updated_at)->with('documents');

                    if (! $user->hasPermission('view_task')) {
                        $query->where('tasks.user_id', $user->id)->orWhere('tasks.assigned_user_id', $user->id);
                    }
                },
                'company.tax_rates'=> function ($query) use ($updated_at, $user) {
                    $query->whereNotNull('updated_at');
                },
                'company.vendors'=> function ($query) use ($updated_at, $user) {
                    $query->where('updated_at', '>=', $updated_at)->with('contacts', 'documents');

                    if (! $user->hasPermission('view_vendor')) {
                        $query->where('vendors.user_id', $user->id)->orWhere('vendors.assigned_user_id', $user->id);
                    }
                },
                'company.expense_categories'=> function ($query) use ($updated_at, $user) {
                    $query->whereNotNull('updated_at');
                },
                'company.task_statuses'=> function ($query) use ($updated_at, $user) {
                    $query->whereNotNull('updated_at');
                },
                'company.activities'=> function ($query) use ($user) {
                    if (! $user->isAdmin()) {
                        $query->where('activities.user_id', $user->id);
                    }
                },
                'company.subscriptions'=> function ($query) use ($updated_at, $user) {
                    $query->whereNotNull('updated_at');

                    if (! $user->isAdmin()) {
                        $query->where('subscriptions.user_id', $user->id);
                    }
                },
            ]
        );

        if ($query instanceof Builder) {
            $limit = request()->input('per_page', 20);

            $paginator = $query->paginate($limit);
            $query = $paginator->getCollection();
            $resource = new Collection($query, $transformer, $this->entity_type);
            $resource->setPaginator(new IlluminatePaginatorAdapter($paginator));
        } else {
            $resource = new Collection($query, $transformer, $this->entity_type);
        }

        return $this->response($this->manager->createData($resource)->toArray());
    }

    protected function miniLoadResponse($query)
    {
        $user = auth()->user();

        $this->serializer = request()->input('serializer') ?: EntityTransformer::API_SERIALIZER_ARRAY;

        if ($this->serializer === EntityTransformer::API_SERIALIZER_JSON) {
            $this->manager->setSerializer(new JsonApiSerializer());
        } else {
            $this->manager->setSerializer(new ArraySerializer());
        }

        $transformer = new $this->entity_transformer($this->serializer);
        $created_at = request()->has('created_at') ? request()->input('created_at') : 0;

        $created_at = date('Y-m-d H:i:s', $created_at);

        $query->with(
            [
                'company' => function ($query) use ($created_at, $user) {
                    $query->whereNotNull('created_at')->with('documents', 'users');
                },
                'company.designs'=> function ($query) use ($created_at, $user) {
                    $query->where('created_at', '>=', $created_at)->with('company');
                },
                'company.documents'=> function ($query) use ($created_at, $user) {
                    $query->where('created_at', '>=', $created_at);
                },
                'company.groups'=> function ($query) use ($created_at, $user) {
                    $query->where('created_at', '>=', $created_at)->with('documents');
                },
                'company.payment_terms'=> function ($query) use ($created_at, $user) {
                    $query->where('created_at', '>=', $created_at);
                },
                'company.tax_rates'=> function ($query) use ($created_at, $user) {
                    $query->whereNotNull('created_at');
                },
                'company.activities'=> function ($query) use ($user) {
                    if (! $user->isAdmin()) {
                        $query->where('activities.user_id', $user->id);
                    }
                },
            ]
        );

        if ($query instanceof Builder) {
            $limit = request()->input('per_page', 20);

            $paginator = $query->paginate($limit);
            $query = $paginator->getCollection();
            $resource = new Collection($query, $transformer, $this->entity_type);
            $resource->setPaginator(new IlluminatePaginatorAdapter($paginator));
        } else {
            $resource = new Collection($query, $transformer, $this->entity_type);
        }

        return $this->response($this->manager->createData($resource)->toArray());
    }

    protected function timeConstrainedResponse($query)
    {
        $user = auth()->user();

        if ($user->getCompany()->is_large) {
            $this->manager->parseIncludes($this->mini_load);

            return $this->miniLoadResponse($query);
        } else {
            $this->manager->parseIncludes($this->first_load);
        }

        $this->serializer = request()->input('serializer') ?: EntityTransformer::API_SERIALIZER_ARRAY;

        if ($this->serializer === EntityTransformer::API_SERIALIZER_JSON) {
            $this->manager->setSerializer(new JsonApiSerializer());
        } else {
            $this->manager->setSerializer(new ArraySerializer());
        }

        $transformer = new $this->entity_transformer($this->serializer);
        $created_at = request()->has('created_at') ? request()->input('created_at') : 0;

        $created_at = date('Y-m-d H:i:s', $created_at);

        $query->with(
            [
                'company' => function ($query) use ($created_at, $user) {
                    $query->whereNotNull('created_at')->with('documents', 'users');
                },
                'company.clients' => function ($query) use ($created_at, $user) {
                    $query->where('clients.created_at', '>=', $created_at)->with('contacts.company', 'gateway_tokens', 'documents');

                    if (! $user->hasPermission('view_client')) {
                        $query->where('clients.user_id', $user->id)->orWhere('clients.assigned_user_id', $user->id);
                    }
                },
                'company.company_gateways' => function ($query) use ($user) {
                    $query->whereNotNull('created_at')->with('gateway');

                    if (! $user->isAdmin()) {
                        $query->where('company_gateways.user_id', $user->id);
                    }
                },
                'company.credits'=> function ($query) use ($created_at, $user) {
                    $query->where('created_at', '>=', $created_at)->with('invitations', 'documents');

                    if (! $user->hasPermission('view_credit')) {
                        $query->where('credits.user_id', $user->id)->orWhere('credits.assigned_user_id', $user->id);
                    }
                },
                'company.documents'=> function ($query) use ($created_at, $user) {
                    $query->where('created_at', '>=', $created_at);
                },
                'company.expenses'=> function ($query) use ($created_at, $user) {
                    $query->where('created_at', '>=', $created_at)->with('documents');

                    if (! $user->hasPermission('view_expense')) {
                        $query->where('expenses.user_id', $user->id)->orWhere('expenses.assigned_user_id', $user->id);
                    }
                },
                'company.groups' => function ($query) use ($created_at, $user) {
                    $query->where('created_at', '>=', $created_at)->with('documents');
                },
                'company.invoices'=> function ($query) use ($created_at, $user) {
                    $query->where('created_at', '>=', $created_at)->with('invitations', 'documents');

                    if (! $user->hasPermission('view_invoice')) {
                        $query->where('invoices.user_id', $user->id)->orWhere('invoices.assigned_user_id', $user->id);
                    }
                },
                'company.payments'=> function ($query) use ($created_at, $user) {
                    $query->where('created_at', '>=', $created_at)->with('paymentables', 'documents');

                    if (! $user->hasPermission('view_payment')) {
                        $query->where('payments.user_id', $user->id)->orWhere('payments.assigned_user_id', $user->id);
                    }
                },
                'company.payment_terms'=> function ($query) use ($created_at, $user) {
                    $query->where('created_at', '>=', $created_at);
                },
                'company.products' => function ($query) use ($created_at, $user) {
                    $query->where('created_at', '>=', $created_at)->with('documents');

                    if (! $user->hasPermission('view_product')) {
                        $query->where('products.user_id', $user->id)->orWhere('products.assigned_user_id', $user->id);
                    }
                },
                'company.projects'=> function ($query) use ($created_at, $user) {
                    $query->where('created_at', '>=', $created_at)->with('documents');

                    if (! $user->hasPermission('view_project')) {
                        $query->where('projects.user_id', $user->id)->orWhere('projects.assigned_user_id', $user->id);
                    }
                },
                'company.purchase_orders'=> function ($query) use ($created_at, $user) {
                    $query->where('created_at', '>=', $created_at)->with('documents');

                    if (! $user->hasPermission('view_purchase_order')) {
                        $query->where('purchase_orders.user_id', $user->id)->orWhere('purchase_orders.assigned_user_id', $user->id);
                    }
                },
                'company.quotes'=> function ($query) use ($created_at, $user) {
                    $query->where('created_at', '>=', $created_at)->with('invitations', 'documents');

                    if (! $user->hasPermission('view_quote')) {
                        $query->where('quotes.user_id', $user->id)->orWhere('quotes.assigned_user_id', $user->id);
                    }
                },
                'company.recurring_invoices'=> function ($query) use ($created_at, $user) {
                    $query->where('created_at', '>=', $created_at)->with('invitations', 'documents', 'client.gateway_tokens', 'client.group_settings', 'client.company');

                    if (! $user->hasPermission('view_recurring_invoice')) {
                        $query->where('recurring_invoices.user_id', $user->id)->orWhere('recurring_invoices.assigned_user_id', $user->id);
                    }
                },
                'company.tasks'=> function ($query) use ($created_at, $user) {
                    $query->where('created_at', '>=', $created_at)->with('documents');

                    if (! $user->hasPermission('view_task')) {
                        $query->where('tasks.user_id', $user->id)->orWhere('tasks.assigned_user_id', $user->id);
                    }
                },
                'company.tax_rates' => function ($query) use ($created_at, $user) {
                    $query->where('created_at', '>=', $created_at);
                },
                'company.vendors'=> function ($query) use ($created_at, $user) {
                    $query->where('created_at', '>=', $created_at)->with('contacts', 'documents');

                    if (! $user->hasPermission('view_vendor')) {
                        $query->where('vendors.user_id', $user->id)->orWhere('vendors.assigned_user_id', $user->id);
                    }
                },
                'company.expense_categories'=> function ($query) use ($created_at, $user) {
                    $query->whereNotNull('created_at');
                },
                'company.task_statuses'=> function ($query) use ($created_at, $user) {
                    $query->where('created_at', '>=', $created_at);
                },
                'company.activities'=> function ($query) use ($user) {
                    if (! $user->isAdmin()) {
                        $query->where('activities.user_id', $user->id);
                    }
                },
                'company.webhooks'=> function ($query) use ($user) {
                    if (! $user->isAdmin()) {
                        $query->where('webhooks.user_id', $user->id);
                    }
                },
                'company.tokens'=> function ($query) use ($user) {
                    $query->where('company_tokens.user_id', $user->id);
                },
                'company.system_logs',
                'company.subscriptions'=> function ($query) use ($created_at, $user) {
                    $query->where('created_at', '>=', $created_at);

                    if (! $user->isAdmin()) {
                        $query->where('subscriptions.user_id', $user->id);
                    }
                },
                'company.recurring_expenses'=> function ($query) use ($created_at, $user) {
                    $query->where('created_at', '>=', $created_at)->with('documents');

                    if (! $user->hasPermission('view_recurring_expense')) {
                        $query->where('recurring_expenses.user_id', $user->id)->orWhere('recurring_expenses.assigned_user_id', $user->id);
                    }
                },
            ]
        );

        if ($query instanceof Builder) {
            $limit = request()->input('per_page', 20);

            $paginator = $query->paginate($limit);
            $query = $paginator->getCollection();
            $resource = new Collection($query, $transformer, $this->entity_type);
            $resource->setPaginator(new IlluminatePaginatorAdapter($paginator));
        } else {
            $resource = new Collection($query, $transformer, $this->entity_type);
        }

        return $this->response($this->manager->createData($resource)->toArray());
    }

    protected function listResponse($query)
    {
        $this->buildManager();

        $transformer = new $this->entity_transformer(request()->input('serializer'));

        $includes = $transformer->getDefaultIncludes();

        $includes = $this->getRequestIncludes($includes);

        $query->with($includes);

        // 10-01-2022 need to ensure we snake case properly here to ensure permissions work as expected
        // 28-03-2022 this is definitely correct here, do not append _ to the view, it resolved correctly when snake cased
        if (auth()->user() && ! auth()->user()->hasPermission('view'.lcfirst(class_basename(Str::snake($this->entity_type))))) {
            $query->where('user_id', '=', auth()->user()->id);
        }

        if (request()->has('updated_at') && request()->input('updated_at') > 0) {
            $query->where('updated_at', '>=', date('Y-m-d H:i:s', intval(request()->input('updated_at'))));
        }

        if ($this->serializer && $this->serializer != EntityTransformer::API_SERIALIZER_JSON) {
            $this->entity_type = null;
        }

        if ($query instanceof Builder) {
            $limit = request()->input('per_page', 20);
            $paginator = $query->paginate($limit);
            $query = $paginator->getCollection();
            $resource = new Collection($query, $transformer, $this->entity_type);
            $resource->setPaginator(new IlluminatePaginatorAdapter($paginator));
        } else {
            $resource = new Collection($query, $transformer, $this->entity_type);
        }

        return $this->response($this->manager->createData($resource)->toArray());
    }

    protected function response($response)
    {
        $index = request()->input('index') ?: $this->forced_index;

        if ($index == 'none') {
            unset($response['meta']);
        } else {
            $meta = isset($response['meta']) ? $response['meta'] : null;
            $response = [
                $index => $response,
            ];

            if ($meta) {
                $response['meta'] = $meta;
                unset($response[$index]['meta']);
            }

            if (request()->include_static) {
                $response['static'] = Statics::company(auth()->user()->getCompany()->getLocale());
            }
        }

        ksort($response);

        $response = json_encode($response, JSON_PRETTY_PRINT);

        $headers = self::getApiHeaders();

        return response()->make($response, 200, $headers);
    }

    protected function itemResponse($item)
    {
        $this->buildManager();

        $transformer = new $this->entity_transformer(request()->input('serializer'));

        if ($this->serializer && $this->serializer != EntityTransformer::API_SERIALIZER_JSON) {
            $this->entity_type = null;
        }

        $resource = new Item($item, $transformer, $this->entity_type);

        if (auth()->user() && request()->include_static) {
            $data['static'] = Statics::company(auth()->user()->getCompany()->getLocale());
        }

        return $this->response($this->manager->createData($resource)->toArray());
    }

    public static function getApiHeaders($count = 0)
    {
        return [
            'Content-Type' => 'application/json',
            'X-Api-Version' => config('ninja.minimum_client_version'),
            'X-App-Version' => config('ninja.app_version'),
        ];
    }

    protected function getRequestIncludes($data)
    {

        /*
         * Thresholds for displaying large account on first load
         */
        if (request()->has('first_load') && request()->input('first_load') == 'true') {
            if (auth()->user()->getCompany()->is_large && request()->missing('updated_at')) {
                $data = $this->mini_load;
            } else {
                $data = $this->first_load;
            }
        } else {
            $included = request()->input('include');
            $included = explode(',', $included);

            foreach ($included as $include) {
                if ($include == 'clients') {
                    $data[] = 'clients.contacts';
                } elseif ($include) {
                    $data[] = $include;
                }
            }
        }

        return $data;
    }

    public function flutterRoute()
    {
        if ((bool) $this->checkAppSetup() !== false && $account = Account::first()) {
            if (config('ninja.require_https') && ! request()->isSecure()) {
                return redirect()->secure(request()->getRequestUri());
            }

            /* Clean up URLs and remove query parameters from the URL*/
            if (request()->has('login') && request()->input('login') == 'true') {
                return redirect('/')->with(['login' => 'true']);
            }

            if (request()->has('signup') && request()->input('signup') == 'true') {
                return redirect('/')->with(['signup' => 'true']);
            }

            $data = [];

            //pass report errors bool to front end
            $data['report_errors'] = Ninja::isSelfHost() ? $account->report_errors : true;

            //pass referral code to front end
            $data['rc'] = request()->has('rc') ? request()->input('rc') : '';
            $data['build'] = request()->has('build') ? request()->input('build') : '';
            $data['login'] = request()->has('login') ? request()->input('login') : 'false';
            $data['signup'] = request()->has('signup') ? request()->input('signup') : 'false';

            if (request()->session()->has('login')) {
                $data['login'] = 'true';
            }

            if(request()->session()->has('signup')){
                $data['signup'] = 'true';
            }

            $data['user_agent'] = request()->server('HTTP_USER_AGENT');

            $data['path'] = $this->setBuild();

            $this->buildCache();

            if (Ninja::isSelfHost() && $account->set_react_as_default_ap) {
                return response()->view('react.index', $data)->header('X-Frame-Options', 'SAMEORIGIN', false);
            } else {
                return response()->view('index.index', $data)->header('X-Frame-Options', 'SAMEORIGIN', false);
            }
        }

        return redirect('/setup');
    }

    private function setBuild()
    {
        $build = '';

        if (request()->has('build')) {
            $build = request()->input('build');
        } elseif (Ninja::isHosted()) {
            return 'main.dart.js';
        }

        switch ($build) {
            case 'wasm':
                return 'main.wasm.dart.js';
            case 'foss':
                return 'main.foss.dart.js';
            case 'last':
                return 'main.last.dart.js';
            case 'next':
                return 'main.next.dart.js';
            case 'profile':
                return 'main.profile.dart.js';
            case 'html':
                return 'main.html.dart.js';
            default:
                return 'main.foss.dart.js';

        }
    }

    public function checkFeature($feature)
    {
        if (auth()->user()->account->hasFeature($feature)) {
            return true;
        }

        return false;
    }

    public function featureFailure()
    {
        return response()->json(['message' => 'Upgrade to a paid plan for this feature.'], 403);
    }
}
