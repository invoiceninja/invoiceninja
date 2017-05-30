<?php

namespace App\Http\Controllers;

use App\Models\EntityModel;
use App\Ninja\Serializers\ArraySerializer;
use Auth;
use Input;
use League\Fractal\Manager;
use League\Fractal\Pagination\IlluminatePaginatorAdapter;
use League\Fractal\Resource\Collection;
use League\Fractal\Resource\Item;
use League\Fractal\Serializer\JsonApiSerializer;
use Request;
use Response;
use Utils;

/**
 * @SWG\Swagger(
 *     schemes={"http","https"},
 *     host="ninja.dev",
 *     basePath="/api/v1",
 *     produces={"application/json"},
 *     @SWG\Info(
 *         version="1.0.0",
 *         title="Invoice Ninja API",
 *         description="An open-source invoicing and time-tracking app built with Laravel",
 *         termsOfService="",
 *         @SWG\Contact(
 *             email="contact@invoiceninja.com"
 *         ),
 *         @SWG\License(
 *             name="Attribution Assurance License",
 *             url="https://raw.githubusercontent.com/invoiceninja/invoiceninja/master/LICENSE"
 *         )
 *     ),
 *     @SWG\ExternalDocumentation(
 *         description="Find out more about Invoice Ninja",
 *         url="https://www.invoiceninja.com"
 *     ),
 *     security={"api_key": {}},
 *     @SWG\SecurityScheme(
 *         securityDefinition="api_key",
 *         type="apiKey",
 *         in="header",
 *         name="X-Ninja-Token"
 *     )
 * )
 */
class BaseAPIController extends Controller
{
    protected $manager;
    protected $serializer;

    public function __construct()
    {
        $this->manager = new Manager();

        if ($include = Request::get('include')) {
            $this->manager->parseIncludes($include);
        }

        $this->serializer = Request::get('serializer') ?: API_SERIALIZER_ARRAY;

        if ($this->serializer === API_SERIALIZER_JSON) {
            $this->manager->setSerializer(new JsonApiSerializer());
        } else {
            $this->manager->setSerializer(new ArraySerializer());
        }
    }

    protected function handleAction($request)
    {
        $entity = $request->entity();
        $action = $request->action;

        if (! in_array($action, ['archive', 'delete', 'restore'])) {
            return $this->errorResponse("Action [$action] is not supported");
        }

        $repo = Utils::toCamelCase($this->entityType) . 'Repo';

        $this->$repo->$action($entity);

        return $this->itemResponse($entity);
    }

    protected function listResponse($query)
    {
        $transformerClass = EntityModel::getTransformerName($this->entityType);
        $transformer = new $transformerClass(Auth::user()->account, Input::get('serializer'));

        $includes = $transformer->getDefaultIncludes();
        $includes = $this->getRequestIncludes($includes);

        $query->with($includes);

        if ($updatedAt = intval(Input::get('updated_at'))) {
            $query->where('updated_at', '>=', date('Y-m-d H:i:s', $updatedAt));
        }

        if ($clientPublicId = Input::get('client_id')) {
            $filter = function ($query) use ($clientPublicId) {
                $query->where('public_id', '=', $clientPublicId);
            };
            $query->whereHas('client', $filter);
        }

        if (! Utils::hasPermission('view_all')) {
            if ($this->entityType == ENTITY_USER) {
                $query->where('id', '=', Auth::user()->id);
            } else {
                $query->where('user_id', '=', Auth::user()->id);
            }
        }

        $data = $this->createCollection($query, $transformer, $this->entityType);

        return $this->response($data);
    }

    protected function itemResponse($item)
    {
        $transformerClass = EntityModel::getTransformerName($this->entityType);
        $transformer = new $transformerClass(Auth::user()->account, Input::get('serializer'));

        $data = $this->createItem($item, $transformer, $this->entityType);

        return $this->response($data);
    }

    protected function createItem($data, $transformer, $entityType)
    {
        if ($this->serializer && $this->serializer != API_SERIALIZER_JSON) {
            $entityType = null;
        }

        $resource = new Item($data, $transformer, $entityType);

        return $this->manager->createData($resource)->toArray();
    }

    protected function createCollection($query, $transformer, $entityType)
    {
        if ($this->serializer && $this->serializer != API_SERIALIZER_JSON) {
            $entityType = null;
        }

        if (is_a($query, "Illuminate\Database\Eloquent\Builder")) {
            $limit = min(MAX_API_PAGE_SIZE, Input::get('per_page', DEFAULT_API_PAGE_SIZE));
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
        $index = Request::get('index') ?: 'data';

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
        $headers = Utils::getApiHeaders();

        return Response::make($response, 200, $headers);
    }

    protected function errorResponse($response, $httpErrorCode = 400)
    {
        $error['error'] = $response;
        $error = json_encode($error, JSON_PRETTY_PRINT);
        $headers = Utils::getApiHeaders();

        return Response::make($error, $httpErrorCode, $headers);
    }

    protected function getRequestIncludes($data)
    {
        $included = Request::get('include');
        $included = explode(',', $included);

        foreach ($included as $include) {
            if ($include == 'invoices') {
                $data[] = 'invoices.invoice_items';
            } elseif ($include == 'client') {
                $data[] = 'client.contacts';
            } elseif ($include == 'clients') {
                $data[] = 'clients.contacts';
            } elseif ($include == 'vendors') {
                $data[] = 'vendors.vendor_contacts';
            } elseif ($include) {
                $data[] = $include;
            }
        }

        return $data;
    }

    protected function fileReponse($name, $data)
    {
        header('Content-Type: application/pdf');
        header('Content-Length: ' . strlen($data));
        header('Content-disposition: attachment; filename="' . $name . '"');
        header('Cache-Control: public, must-revalidate, max-age=0');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');

        return $data;
    }
}
