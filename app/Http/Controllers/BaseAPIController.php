<?php namespace App\Http\Controllers;

use Utils;
use Response;
use League\Fractal;
use League\Fractal\Manager;
use App\Ninja\Serializers\ArraySerializer;

class BaseAPIController extends Controller
{
    protected $manager;

    public function __construct()
    {
        $this->manager = new Manager();
        $this->manager->setSerializer(new ArraySerializer());
    }

    protected function returnData($resource, $class = false)
    {
        $response = $this->manager->createData($resource)->toArray();

        if ($class) {
            $response = [$class => $response];
        }
        
        $response = json_encode($response, JSON_PRETTY_PRINT);
        $headers = Utils::getApiHeaders();

        return Response::make($response, 200, $headers);
    }

}
