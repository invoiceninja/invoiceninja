<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
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
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
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
          'company.users',
          //'company.users.company_user',
          'company.clients.contacts.company',
          'company.clients.gateway_tokens',
          'company.clients.documents',
          'company.company_gateways.gateway',
          'company.credits.invitations.contact',
          'company.credits.invitations.company',
          'company.credits.documents',
          'company.expenses.documents',
          'company.groups',
          'company.invoices.invitations.contact',
          'company.invoices.invitations.company',
          'company.invoices.documents',
          'company.products',
          'company.products.documents',
          'company.payments.paymentables',
          'company.payments.documents',
          'company.payment_terms.company',
          'company.projects.documents',
          'company.recurring_invoices',
          'company.recurring_invoices.invitations.contact',
          'company.recurring_invoices.invitations.company',
          'company.recurring_invoices.documents',
          'company.quotes.invitations.contact',
          'company.quotes.invitations.company',
          'company.quotes.documents',
          'company.tasks.documents',
          'company.tax_rates',
          'company.tokens_hashed',
          'company.vendors.contacts.company',
          'company.vendors.documents',
          'company.webhooks',
        ];

    private $mini_load = [
          'account',
          'user.company_user',
          'token',
          'company.activities',
          //'company.users.company_user',
          'company.tax_rates',
          'company.groups',
          'company.payment_terms',
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
        return response()->json(['message' => '404 | Nothing to see here!'], 404)
                         ->header('X-API-VERSION', config('ninja.minimum_client_version'))
                         ->header('X-APP-VERSION', config('ninja.app_version'));
    }

    /**
     * 404 for the client portal.
     * @return Response 404 response
     */
    public function notFoundClient()
    {
        return abort(404);
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
        $this->manager->parseIncludes($this->first_load);

        $this->serializer = request()->input('serializer') ?: EntityTransformer::API_SERIALIZER_ARRAY;

        if ($this->serializer === EntityTransformer::API_SERIALIZER_JSON) {
            $this->manager->setSerializer(new JsonApiSerializer());
        } else {
            $this->manager->setSerializer(new ArraySerializer());
        }

        $transformer = new $this->entity_transformer($this->serializer);
        $updated_at = request()->has('updated_at') ? request()->input('updated_at') : 0;

        if (auth()->user()->getCompany()->is_large && ! request()->has('updated_at')) {
            return response()->json(['message' => 'Cannot load a large account without a updated_at parameter', 'errors' =>[]], 401);
        }

        $updated_at = date('Y-m-d H:i:s', $updated_at);

        $query->with(
            [
            'company' => function ($query) use ($updated_at) {
                $query->whereNotNull('updated_at')->with('documents');
            },
            'company.clients' => function ($query) use ($updated_at) {
                $query->where('clients.updated_at', '>=', $updated_at)->with('contacts.company', 'gateway_tokens', 'documents');
            },
            'company.company_gateways' => function ($query) {
                $query->whereNotNull('updated_at');
            },
            'company.credits'=> function ($query) use ($updated_at) {
                $query->where('updated_at', '>=', $updated_at)->with('invitations', 'documents');
            },
            'company.designs'=> function ($query) use ($updated_at) {
                $query->where('updated_at', '>=', $updated_at)->with('company');
            },
            'company.documents'=> function ($query) use ($updated_at) {
                $query->where('updated_at', '>=', $updated_at);
            },
            'company.expenses'=> function ($query) use ($updated_at) {
                $query->where('updated_at', '>=', $updated_at)->with('documents');
            },
            'company.groups' => function ($query) use ($updated_at) {
                $query->where('updated_at', '>=', $updated_at);
            },
            'company.invoices'=> function ($query) use ($updated_at) {
                $query->where('updated_at', '>=', $updated_at)->with('invitations', 'documents');
            },
            'company.payments'=> function ($query) use ($updated_at) {
                $query->where('updated_at', '>=', $updated_at)->with('paymentables', 'documents');
            },
            'company.payment_terms'=> function ($query) use ($updated_at) {
                $query->where('updated_at', '>=', $updated_at);
            },
            'company.products' => function ($query) use ($updated_at) {
                $query->where('updated_at', '>=', $updated_at)->with('documents');
            },
            'company.projects'=> function ($query) use ($updated_at) {
                $query->where('updated_at', '>=', $updated_at)->with('documents');
            },
            'company.quotes'=> function ($query) use ($updated_at) {
                $query->where('updated_at', '>=', $updated_at)->with('invitations', 'documents');
            },
            'company.recurring_invoices'=> function ($query) use ($updated_at) {
                $query->where('updated_at', '>=', $updated_at)->with('invitations', 'documents');
            },
            'company.tasks'=> function ($query) use ($updated_at) {
                $query->where('updated_at', '>=', $updated_at)->with('documents');
            },
            'company.tax_rates' => function ($query) use ($updated_at) {
                $query->where('updated_at', '>=', $updated_at);
            },
            'company.vendors'=> function ($query) use ($updated_at) {
                $query->where('updated_at', '>=', $updated_at)->with('contacts', 'documents');
            },
            'company.expense_categories'=> function ($query) use ($updated_at) {
                $query->where('updated_at', '>=', $updated_at);
            },
            'company.task_statuses'=> function ($query) use ($updated_at) {
                $query->where('updated_at', '>=', $updated_at);
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

        if (auth()->user() && ! auth()->user()->hasPermission('view_'.lcfirst(class_basename($this->entity_type)))) {
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

            $data = [];

            if (Ninja::isSelfHost()) {
                $data['report_errors'] = $account->report_errors;
            } else {
                $data['report_errors'] = true;
            }

            $this->buildCache();

            return view('index.index', $data);
        }

        return redirect('/setup');
    }
}
