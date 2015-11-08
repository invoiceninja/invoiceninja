<?php namespace App\Http\Controllers;

use Utils;
use Response;
use League\Fractal;
use League\Fractal\Manager;
use League\Fractal\Resource\Item;
use League\Fractal\Resource\Collection;
use App\Ninja\Serializers\ArraySerializer;

/**
 * @SWG\Swagger(
 *     schemes={"http","https"},
 *     host="ninja.dev",
 *     basePath="/api/v1",
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
 *     @SWG\SecurityScheme(
 *         securityDefinition="api_key",
 *         type="apiKey",
 *         in="header",
 *         name="TOKEN"
 *     )
 * )
 */
class BaseAPIController extends Controller
{
    protected $manager;

    public function __construct()
    {
        $this->manager = new Manager();
        $this->manager->setSerializer(new ArraySerializer());
    }

    protected function createItem($data, $transformer)
    {
        $resource = new Item($data, $transformer);
        return $this->manager->createData($resource)->toArray();
    }

    protected function createCollection($data, $transformer)
    {
        $resource = new Collection($data, $transformer);
        return $this->manager->createData($resource)->toArray();
    }

    protected function response($response)
    {
        $response = json_encode($response, JSON_PRETTY_PRINT);
        $headers = Utils::getApiHeaders();

        return Response::make($response, 200, $headers);
    }

}
