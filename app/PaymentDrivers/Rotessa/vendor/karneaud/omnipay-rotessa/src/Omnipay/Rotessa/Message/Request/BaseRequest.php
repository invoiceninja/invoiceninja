<?php
namespace Omnipay\Rotessa\Message\Request;

use Omnipay\Common\Http\ClientInterface;
use Omnipay\Rotessa\Http\Response\Response;
use Omnipay\Rotessa\Message\Response\BaseResponse;
use Omnipay\Rotessa\Message\Request\RequestInterface;
use Omnipay\Rotessa\Message\Response\ResponseInterface;
use Symfony\Component\HttpFoundation\Request as HttpRequest;

class BaseRequest extends AbstractRequest implements RequestInterface
{
    protected $base_url = 'rotessa.com';
    protected $api_version = 1;
    protected $endpoint = '';
    
    const ENVIRONMENT_SANDBOX = 'sandbox-api'; 
    const ENVIRONMENT_LIVE = 'api';

    function __construct(ClientInterface $http_client = null, HttpRequest $http_request, $model ) {
        parent::__construct($http_client, $http_request );
        $this->initialize($model);
    }

    protected function sendRequest(string $method, string $endpoint, array $headers = [], array $data = [])
    {
        /**
         * @param $method
         * @param $uri
         * @param array $headers
         * @param string|resource|StreamInterface|null $body
         * @param string $protocolVersion
         * @return ResponseInterface
         * @throws \Http\Client\Exception
         */
        $response = $this->httpClient->request($method, $endpoint, $headers, json_encode($data) ) ;
        $this->response = new Response ($response->getBody()->getContents(), $response->getStatusCode(), $response->getHeaders(), true);
    }


    protected function createResponse(array $data): ResponseInterface {
       
       return new BaseResponse($this, $data, $this->response->getStatusCode(), $this->response->getReasonPhrase());
    }

    protected function replacePlaceholder($string, $array) {
        $pattern = "/\{([^}]+)\}/";
        $replacement = function($matches) use($array) {
          $key = $matches[1];
          if (array_key_exists($key, $array)) {
            return $array[$key];
          } else {
            return $matches[0];
          }
        };
      
        return preg_replace_callback($pattern, $replacement, $string);
    }

    public function sendData($data) :ResponseInterface {
        $headers = [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Authorization' => "Token token={$this->api_key}"
        ];

       $this->sendRequest(
                    $this->method,
                    $this->getEndpointUrl(),
                    $headers,
                    $data);

        return $this->createResponse(json_decode($this->response->getContent(), true));
    }
      
    public function getEndpoint() : string {
        return $this->replacePlaceholder($this->endpoint, $this->getParameters());
    }

    public function getEndpointUrl() : string {
        return sprintf('https://%s.%s/v%d%s',$this->test_mode ? self::ENVIRONMENT_SANDBOX : self::ENVIRONMENT_LIVE ,$this->base_url, $this->api_version, $this->getEndpoint());
    }

    public static function hasModel() : bool {
        return (bool) static::$model;
    }

    public static function getModel($parameters = []) {
        $class_name = static::$model;
        $class_name = "Omnipay\\Rotessa\\Model\\{$class_name}Model";
        return new $class_name($parameters);
    }
}