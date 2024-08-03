<?php

namespace Omnipay\Rotessa\Http;

use Omnipay\Common\Http\Client as HttpClient;
use Omnipay\Common\Http\Exception\NetworkException;
use Omnipay\Common\Http\Exception\RequestException;
use Http\Discovery\HttpClientDiscovery;
use Http\Discovery\MessageFactoryDiscovery;
use Http\Message\RequestFactory;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class Client extends HttpClient
{
    /**
     * The Http Client which implements `public function sendRequest(RequestInterface $request)`
     * Note: Will be changed to PSR-18 when released
     *
     * @var HttpClient
     */
    private $httpClient;

    /**
     * @var RequestFactory
     */
    private $requestFactory;

    public function __construct($httpClient = null, RequestFactory $requestFactory = null)
    {
        $this->httpClient = $httpClient ?: HttpClientDiscovery::find();
        $this->requestFactory = $requestFactory ?: MessageFactoryDiscovery::find();
        parent::__construct($httpClient, $requestFactory);
    }

    /**
     * @param $method
     * @param $uri
     * @param array $headers
     * @param string|array|resource|StreamInterface|null $body
     * @param string $protocolVersion
     * @return ResponseInterface
     * @throws \Http\Client\Exception
     */
    public function request(
        $method,
        $uri,
        array $headers = [],
        $body = null,
        $protocolVersion = '1.1'
    ) {
        return $this->sendRequest($method, $uri, $headers, $body, $protocolVersion);

    }

    /**
     * @param RequestInterface $request
     * @return ResponseInterface
     * @throws \Http\Client\Exception
     */
    private function sendRequest( $method,
    $uri,
    array $headers = [],
    $body = null,
    $protocolVersion = '1.1')
    {
        
        $response = null;

        try {
           if( method_exists($this->httpClient, 'sendRequest'))
            $response = $this->httpClient->sendRequest( $this->requestFactory->createRequest($method, $uri, $headers, $body, $protocolVersion));
            else $response = $this->httpClient->request($method, $uri, compact('body','headers'));
        } catch (\Http\Client\Exception\NetworkException $networkException) {
            throw new \Exception($networkException->getMessage());
        } catch (\Exception $exception) {
            throw new \Exception($exception->getMessage());
        }

        return $response;
    }
}