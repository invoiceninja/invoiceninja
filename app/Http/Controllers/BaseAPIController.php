<?php namespace App\Http\Controllers;

use Utils;
use Response;
use League\Fractal;
use League\Fractal\Manager;
use League\Fractal\Resource\Item;
use League\Fractal\Resource\Collection;
use App\Ninja\Serializers\ArraySerializer;

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
