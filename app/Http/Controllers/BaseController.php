<?php

namespace App\Http\Controllers;

use App\Transformers\ArraySerializer;
use App\Transformers\EntityTransformer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Log;
use League\Fractal\Manager;
use League\Fractal\Pagination\IlluminatePaginatorAdapter;
use League\Fractal\Resource\Collection;
use League\Fractal\Resource\Item;
use League\Fractal\Serializer\JsonApiSerializer;

class BaseController extends Controller
{

	public function __construct()
    {
        $this->manager = new Manager();

        if ($include = request()->input('include')) {
            $this->manager->parseIncludes($include);
        }

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
        return response()->json([
        'message' => 'Nothing to see here!'], 404);
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
        $transformer = new $this->entityTransformer(Input::get('serializer'));

        $includes = $transformer->getDefaultIncludes();
        $includes = $this->getRequestIncludes($includes);

        $query->with($includes);

        $data = $this->createCollection($query, $transformer, $this->entityType);

        return $this->response($data);
    }

    protected function createCollection($query, $transformer, $entityType)
    {
        
        if ($this->serializer && $this->serializer != EntityTransformer::API_SERIALIZER_JSON) {
            $entityType = null;
        }

        if (is_a($query, "Illuminate\Database\Eloquent\Builder")) {
            $limit = Input::get('per_page', 20);

            $paginator = $query->paginate($limit);
            $query = $paginator->getCollection();
            $resource = new Collection($query, $transformer, $entityType);
            $resource->setPaginator(new IlluminatePaginatorAdapter($paginator));
        } else {
            $resource = new Collection($query, $transformer, $entityType);
        }

        return $this->manager->createData($resource)->toArray();
    }

    protected function response($response)
    {
        $index = request()->input('index') ?: 'data';

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
        }

        $response = json_encode($response, JSON_PRETTY_PRINT);
        $headers = self::getApiHeaders();

        return response()->make($response, 200, $headers);
    }

    protected function itemResponse($item)
    {

        $transformer = new $this->entityTransformer(Input::get('serializer'));

        $data = $this->createItem($item, $transformer, $this->entityType);

        return $this->response($data);
    }

    protected function createItem($data, $transformer, $entityType)
    {
        if ($this->serializer && $this->serializer != EntityTransformer::API_SERIALIZER_JSON) {
            $entityType = null;
        }

        $resource = new Item($data, $transformer, $entityType);

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
          'X-Total-Count' => $count,
          'X-Muudeo-Version' => config('ninja.api_version'),
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
            } elseif ($include == 'tracks') {
                $data[] = 'tracks.comments';
                $data[] = 'tracks.tags';
            } elseif ($include) {
                $data[] = $include;
            }
        }

        return $data;
    }
}