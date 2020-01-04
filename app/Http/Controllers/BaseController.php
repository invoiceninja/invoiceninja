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

use App\Models\Company;
use App\Models\User;
use App\Transformers\ArraySerializer;
use App\Transformers\EntityTransformer;
use App\Utils\Statics;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request as Input;
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

            /* For very large accounts, we reduce the includes automatically */
            if (auth()->user()->getCompany()->clients->count() > 1000) {
                $include = [
                  'account',
                  'user.company_user',
                  'token',
                  'company.activities',
                  'company.users.company_user',
                  'company.tax_rates',
                  'company.groups',
                  'company.company_gateways.gateway',
                  'company.clients',
                  'company.products',
                  'company.invoices',
                  'company.payments',
                  'company.quotes',
              ];
            } else {
                $include = [
                  'account',
                  'user.company_user',
                  'token',
                  'company.activities',
                  'company.users.company_user',
                  'company.tax_rates',
                  'company.groups',
                  // 'company.company_gateways.gateway',
                  // 'company.clients',
                  // 'company.products',
                  // 'company.invoices',
                  // 'company.payments',
                  // 'company.quotes',
              ];
            }

            $include = array_merge($this->forced_includes, $include);

            $include = implode(",", $include);
        } elseif (request()->input('include') !== null) {
            $request_include = explode(",", request()->input('include'));

            $include = array_merge($this->forced_includes, $request_include);

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
                         ->header('X-API-VERSION', config('ninja.api_version'))
                         ->header('X-APP-VERSION', config('ninja.app_version'));
    }

    public function notFoundClient()
    {
        return abort(404);
    }

    protected function errorResponse($response, $httpErrorCode = 400)
    {
        $error['error'] = $response;
        $error = json_encode($error, JSON_PRETTY_PRINT);
        $headers = self::getApiHeaders();

        return response()->make($error, $httpErrorCode, $headers);
    }

    protected function listResponse($query)
    {
        $this->buildManager();

        $transformer = new $this->entity_transformer(Input::get('serializer'));

        $includes = $transformer->getDefaultIncludes();

        $includes = $this->getRequestIncludes($includes);

        $query->with($includes);

        if (auth()->user()->cannot('view_'.$this->entity_type)) {
            if ($this->entity_type == Company::class) {
                //no user keys exist on the company table, so we need to skip
            } elseif ($this->entity_type == User::class) {
                $query->where('id', '=', auth()->user()->id);
            } else {
                $query->where('user_id', '=', auth()->user()->id);
            }
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
          //'Access-Control-Allow-Origin' => '*',
          //'Access-Control-Allow-Methods' => 'GET',
          //'Access-Control-Allow-Headers' => 'Origin, Content-Type, Accept, Authorization, X-Requested-With',
          //'Access-Control-Allow-Credentials' => 'true',
          //'X-Total-Count' => $count,
          'X-Api-Version' => config('ninja.api_version'),
          'X-App-Version' => config('ninja.app_version'),
          //'X-Rate-Limit-Limit' - The number of allowed requests in the current period
          //'X-Rate-Limit-Remaining' - The number of remaining requests in the current period
          //'X-Rate-Limit-Reset' - The number of seconds left in the current period,
        ];
    }

    protected function getRequestIncludes($data)
    {
        $included = request()->input('include');
        $included = explode(',', $included);

        foreach ($included as $include) {
            if ($include == 'clients') {
                $data[] = 'clients.contacts';
            } elseif ($include) {
                $data[] = $include;
            }
        }

        return $data;
    }
}
