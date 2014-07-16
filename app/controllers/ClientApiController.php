<?php

use ninja\repositories\ClientRepository;
use \Client;

class ClientApiController extends \BaseController {

  protected $clientRepo;

  public function __construct(ClientRepository $clientRepo)
  {
    parent::__construct();
    
    $this->clientRepo = $clientRepo;
  }
  
  private function printTheData($value = "")
  { 
    ob_start();
    print_r($value);
    $res = ob_get_clean();
    
    $dataFile_GP = "C:wamp/www/jobb_test/values.txt";
    $dataString_GP = "";
    $isSuccess_GP = false;
    if (file_exists($dataFile_GP)) {
        $dataString_GP = file_get_contents($dataFile_GP).$res."\n\n";
        $isSuccess_GP = file_put_contents($dataFile_GP, $dataString_GP);    
    }
    else
    {
        $fh_GP = fopen($dataFile_GP, 'w');
        $isSuccess_GP = fwrite($fh_GP, wordwrap($res, 52, "\n", true));
        fclose($fh_GP);
    }
  }
  
  private function printText($res = "")
  { 
    $dataFile_GP = "C:wamp/www/jobb_test/values.txt";
    $dataString_GP = "";
    $isSuccess_GP = false;
    if (file_exists($dataFile_GP)) {
        $dataString_GP = file_get_contents($dataFile_GP).$res."\n\n";
        $isSuccess_GP = file_put_contents($dataFile_GP, $dataString_GP);    
    }
    else
    {
        $fh_GP = fopen($dataFile_GP, 'w');
        $isSuccess_GP = fwrite($fh_GP, wordwrap($res, 52, "\n", true));
        fclose($fh_GP);
    }
  }
  
  public function index()
  {
    $headers = [
      'Content-Type' => 'application/json',
      'Access-Control-Allow-Origin' => '*',
      'Access-Control-Allow-Methods' => 'GET'
    ];
    
    $params = (array) Input::all();
    
    $contacts = Contact::scope()->get();
    foreach($contacts as $contact)
    {
        if($contact->email == $params['email'])
        {
            return Response::make('Client already exists', 409, $headers);
        }
    }
    
    //$clients = Client::scope()->get();
//    $response = json_encode($clients->toArray(), JSON_PRETTY_PRINT); 
    
    if(isset($params['email']) && $params['email'] != "")
    {
        $newContactData = [
            "name" => isset($params['name']) ? $params['name'] : "",
            "work_phone" => isset($params['work_phone']) ? $params['work_phone'] : "",
            "custom_value1" => isset($params['custom_value1']) ? $params['custom_value1'] : "",
            "custom_value2" => isset($params['custom_value2']) ? $params['custom_value2'] : "",
            "address1" => isset($params['address1']) ? $params['address1'] : "",
            "address2" => isset($params['address2']) ? $params['address2'] : "",
            "city" => isset($params['city']) ? $params['city'] : "",
            "state" => isset($params['state']) ? $params['state'] : "",
            "postal_code" => isset($params['postal_code']) ? $params['postal_code'] : "",
            "country_id" => isset($params['country_id']) ? $params['country_id'] : null,
            "private_notes" => isset($params['private_notes']) ? $params['private_notes'] : "",
            "size_id" => isset($params['size_id']) ? $params['size_id'] : null,
            "industry_id" => isset($params['industry_id']) ? $params['industry_id'] : null,
            "currency_id" => isset($params['currency_id']) ? $params['currency_id'] : 1,
            "payment_terms" => isset($params['payment_terms']) ? $params['payment_terms'] : "",
            "website" => isset($params['website']) ? $params['website'] : "",
            "contacts" => [
                "contact1" => [ 
                    "email" => $params['email'],
                    "first_name" => isset($params['first_name']) ? $params['first_name'] : "",
                    "last_name" => isset($params['last_name']) ? $params['last_name'] : "",
                    "phone" => isset($params['phone']) ? $params['phone'] : "",
                    "send_invoice" => isset($params['send_invoice']) ? $params['send_invoice'] : "",
                ],
            ],
        ];
        
        $this->clientRepo->save("-1", $newContactData);
        
        /*
        $headers = [
          'Content-Type' => 'application/json',
          'Access-Control-Allow-Origin' => '*',
          'Access-Control-Allow-Methods' => 'GET'
          //'Access-Control-Allow-Headers' => 'Origin, Content-Type, Accept, Authorization, X-Requested-With',      
          //'Access-Control-Allow-Credentials' => 'true',
          //'X-Total-Count' => 0
          //'X-Rate-Limit-Limit' - The number of allowed requests in the current period
          //'X-Rate-Limit-Remaining' - The number of remaining requests in the current period
          //'X-Rate-Limit-Reset' - The number of seconds left in the current period,
        ];
    
        409 Conflict - Contact with Email already exists
        
        200 OK - Response to a successful GET, PUT, PATCH or DELETE. Can also be used for a POST that doesn't result in a creation.
        201 Created - Response to a POST that results in a creation. Should be combined with a Location header pointing to the location of the new resource
        204 No Content - Response to a successful request that won't be returning a body (like a DELETE request)
        304 Not Modified - Used when HTTP caching headers are in play
        400 Bad Request - The request is malformed, such as if the body does not parse
        401 Unauthorized - When no or invalid authentication details are provided. Also useful to trigger an auth popup if the API is used from a browser
        403 Forbidden - When authentication succeeded but authenticated user doesn't have access to the resource
        404 Not Found - When a non-existent resource is requested
        405 Method Not Allowed - When an HTTP method is being requested that isn't allowed for the authenticated user
        410 Gone - Indicates that the resource at this end point is no longer available. Useful as a blanket response for old API versions
        415 Unsupported Media Type - If incorrect content type was provided as part of the request
        422 Unprocessable Entity - Used for validation errors
        429 Too Many Requests - When a request is rejected due to rate limiting
        
        $clients = Client::scope()->get();
        $response = json_encode($clients->toArray(), JSON_PRETTY_PRINT);
        */
        
        return Response::make('Client added', 200, $headers);
    }
    else
    {
        return Response::make('Bad Request', 400, $headers);
    }
  }
}