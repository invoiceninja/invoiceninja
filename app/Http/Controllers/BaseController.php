<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
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
use App\Models\Design;
use App\Models\User;
use App\Transformers\ArraySerializer;
use App\Transformers\EntityTransformer;
use App\Utils\Ninja;
use App\Utils\Statics;
use App\Utils\Traits\AppSetup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request as Input;
use Illuminate\Support\Facades\Schema;
use League\Fractal\Manager;
use League\Fractal\Pagination\IlluminatePaginatorAdapter;
use League\Fractal\Resource\Collection;
use League\Fractal\Resource\Item;
use League\Fractal\Serializer\JsonApiSerializer;

/**
 * Class BaseController
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
     * the key of the response object
     * @var string
     */
    public $forced_index;

    /**
     * Fractal manager
     * @var object
     */
    protected $manager;


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
            $include = implode(",", array_merge($this->forced_includes, $this->getRequestIncludes([])));
        } elseif (request()->input('include') !== null) {
            $include = array_merge($this->forced_includes, explode(",", request()->input('include')));

            $include = implode(",", $include);
        } elseif (count($this->forced_includes) >= 1) {
            $include = implode(",", $this->forced_includes);
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
     * for non-existant route
     */
    public function notFound()
    {
        return response()->json(['message' => '404 | Nothing to see here!'], 404)
                         ->header('X-API-VERSION', config('ninja.minimum_client_version'))
                         ->header('X-APP-VERSION', config('ninja.app_version'));
    }

    /**
     * 404 for the client portal
     * @return Response 404 response
     */
    public function notFoundClient()
    {
        return abort(404);
    }

    /**
     * API Error response
     * @param  string  $message       The return error message
     * @param  integer $httpErrorCode 404/401/403 etc
     * @return Response               The JSON response
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
      $includes = [
          'company.tax_rates',
          'company.groups',
          'company.company_gateways.gateway',
          'company.clients.contacts',
          'company.products',
          'company.invoices.invitations.contact',
          'company.invoices.invitations.company',
          'company.invoices.documents',
          'company.payments.paymentables',
          'company.quotes.invitations.contact',
          'company.quotes.invitations.company',
          'company.quotes.documents',
          'company.credits.invitations.contact',
          'company.credits.invitations.company',
          'company.credits.documents',
          'company.payment_terms.company',
          'company.credits.invitations.contact',
          'company.credits.invitations.company',
          'company.vendors.contacts',
          'company.expenses',
          'company.tasks',
          'company.projects',
          'company.designs',
          'company.webhooks',
          'company.tokens_hashed'
        ];

        $this->manager->parseIncludes($includes);

        $this->serializer = request()->input('serializer') ?: EntityTransformer::API_SERIALIZER_ARRAY;

        if ($this->serializer === EntityTransformer::API_SERIALIZER_JSON) {
            $this->manager->setSerializer(new JsonApiSerializer());
        } else {
            $this->manager->setSerializer(new ArraySerializer());
        }

        $transformer = new $this->entity_transformer(Input::get('serializer'));

        $updated_at = request()->has('updated_at') ? request()->input('updated_at') : 0;
        $updated_at = date('Y-m-d H:i:s', $updated_at);

        $query->with(
        [
          'company' => function ($query) use($updated_at){
            $query->where('updated_at', '>=', date('Y-m-d H:i:s', 0));
          },
          'company.clients'=>function ($query) use($updated_at){
              $query->where('clients.updated_at', '>=', $updated_at)->with('contacts');
          },
          'company.tax_rates'=>function ($query) use($updated_at){
            $query->where('updated_at', '>=', $updated_at);
          },
          'company.groups'=>function ($query) use($updated_at){
            $query->where('updated_at', '>=', $updated_at);
          },
          'company.company_gateways'=>function ($query) use($updated_at){
            $query->where('updated_at', '>=', $updated_at)->with('gateway');
          },
          'company.products'=>function ($query) use($updated_at){
            $query->where('updated_at', '>=', $updated_at);
          },
          'company.invoices'=>function ($query) use($updated_at){
            $query->where('updated_at', '>=', $updated_at)->with('invitations','documents');
          },
          'company.payments'=>function ($query) use($updated_at){
            $query->where('updated_at', '>=', $updated_at)->with('paymentables');
          },
          'company.quotes'=>function ($query) use($updated_at){
            $query->where('updated_at', '>=', $updated_at)->with('invitations','documents');
          },
          'company.credits'=>function ($query) use($updated_at){
            $query->where('updated_at', '>=', $updated_at)->with('invitations','documents');
          },
          'company.payment_terms'=>function ($query) use($updated_at){
            $query->where('updated_at', '>=', $updated_at);
          },
          'company.vendors'=>function ($query) use($updated_at){
            $query->where('updated_at', '>=', $updated_at)->with('contacts');
          },
          'company.expenses'=>function ($query) use($updated_at){
            $query->where('updated_at', '>=', $updated_at);
          },
          'company.tasks'=>function ($query) use($updated_at){
            $query->where('updated_at', '>=', $updated_at);
          },
          'company.projects'=>function ($query) use($updated_at){
            $query->where('updated_at', '>=', $updated_at);
          },
          'company.designs'=>function ($query) use($updated_at){
            $query->where('updated_at', '>=', $updated_at);
          },
        ]
        );


        if (is_a($query, "Illuminate\Database\Eloquent\Builder")) {
            $limit = Input::get('per_page', 20);

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

        $transformer = new $this->entity_transformer(Input::get('serializer'));

        $includes = $transformer->getDefaultIncludes();

        $includes = $this->getRequestIncludes($includes);

        $query->with($includes);

        if (!auth()->user()->hasPermission('view_'.lcfirst(class_basename($this->entity_type)))) {
            $query->where('user_id', '=', auth()->user()->id);
        }

        if (request()->has('updated_at') && request()->input('updated_at') > 0) {
            $updated_at = intval(request()->input('updated_at'));
            $query->where('updated_at', '>=', date('Y-m-d H:i:s', $updated_at));
        }

        $data = $this->createCollection($query, $transformer, $this->entity_type);

        return $this->response($data);
    }

    protected function createCollection($query, $transformer, $entity_type)
    {
        $this->buildManager();

        if ($this->serializer && $this->serializer != EntityTransformer::API_SERIALIZER_JSON) {
            $entity_type = null;
        }
        

        if (is_a($query, "Illuminate\Database\Eloquent\Builder")) {
            $limit = Input::get('per_page', 20);

            $paginator = $query->paginate($limit);
            $query = $paginator->getCollection();
            $resource = new Collection($query, $transformer, $entity_type);
            $resource->setPaginator(new IlluminatePaginatorAdapter($paginator));
        } else {
            $resource = new Collection($query, $transformer, $entity_type);
        }

        return $this->manager->createData($resource)->toArray();
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

        $transformer = new $this->entity_transformer(Input::get('serializer'));

        $data = $this->createItem($item, $transformer, $this->entity_type);

        if (request()->include_static) {
            $data['static'] = Statics::company(auth()->user()->getCompany()->getLocale());
        }
        
        return $this->response($data);
    }

    protected function createItem($data, $transformer, $entity_type)
    {
        if ($this->serializer && $this->serializer != EntityTransformer::API_SERIALIZER_JSON) {
            $entity_type = null;
        }
      
        $resource = new Item($data, $transformer, $entity_type);

        return $this->manager->createData($resource)->toArray();
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
        $first_load = [
          'account',
          'user.company_user',
          'token',
          'company.activities',
          'company.users.company_user',
          'company.tax_rates',
          'company.groups',
          'company.company_gateways.gateway',
          'company.clients.contacts',
          'company.products',
          'company.invoices.invitations.contact',
          'company.invoices.invitations.company',
          'company.invoices.documents',
          'company.payments.paymentables',
          'company.quotes.invitations.contact',
          'company.quotes.invitations.company',
          'company.quotes.documents',
          'company.credits.invitations.contact',
          'company.credits.invitations.company',
          'company.credits.documents',
          'company.payment_terms.company',
          //'company.credits.invitations.contact',
          //'company.credits.invitations.company',
          'company.vendors.contacts',
          'company.expenses',
          'company.tasks',
          'company.projects',
          'company.designs',
          'company.webhooks',
          'company.tokens_hashed'
        ];

        $mini_load = [
          'account',
          'user.company_user',
          'token',
          'company.activities',
          'company.users.company_user',
          'company.tax_rates',
          'company.groups',
          'company.payment_terms',
        ];

        /**
         * Thresholds for displaying large account on first load
         */
        if (request()->has('first_load') && request()->input('first_load') == 'true') {
            if (auth()->user()->getCompany()->is_large && request()->missing('updated_at')) {
                $data = $mini_load;
            } else {
                $data = $first_load;
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
      
        if ((bool)$this->checkAppSetup() !== false && Schema::hasTable('accounts') && $account = Account::all()->first()) {
     
          if (config('ninja.require_https') && !request()->isSecure()) {  
              return redirect()->secure(request()->getRequestUri());
          }

            $data = [];

            if (Ninja::isSelfHost()) {
                $data['report_errors'] = $account->report_errors;
            } else {
                $data['report_errors'] = true;
            }

            $data['hash'] = md5(public_path('main.dart.js'));

            return view('index.index', $data);
        }

        return redirect('/setup');
    }
}
