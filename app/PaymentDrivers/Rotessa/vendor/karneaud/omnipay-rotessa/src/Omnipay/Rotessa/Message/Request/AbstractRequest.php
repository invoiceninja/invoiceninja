<?php

namespace Omnipay\Rotessa\Message\Request;

use Omnipay\Rotessa\Message\Request\RequestInterface;
use Omnipay\Common\Message\AbstractRequest as Request;
use Omnipay\Rotessa\Message\Response\ResponseInterface;

abstract class AbstractRequest extends Request implements RequestInterface
{

    protected $test_mode = false;
    protected $api_version;
    protected $method = 'GET';
    protected $endpoint;
    protected $api_key;
    
    public function setApiKey(string $value) {
        $this->api_key = $value;
    }

    public function getData() {
        try {
            if(empty($this->api_key)) throw new \Exception('No Api Key Found!');
            $this->validate( ...array_keys($data = $this->getParameters()));
        } catch (\Throwable $th) {
            throw new \Omnipay\Rotessa\Exception\ValidationException($th->getMessage() , 600, $th);
        }

        return (array) $data;
    }

    abstract public function sendData($data) : ResponseInterface;

    abstract protected function sendRequest(string $method, string $endpoint, array $headers = [], array $data = [] );

    abstract protected function createResponse(array $data) : ResponseInterface;

    abstract public function getEndpointUrl(): string; 

    public function getEndpoint() : string {
        return $this->endpoint;
    }

    public function getTestMode() {
        return $this->test_mode;
    }

    public function setTestMode($mode) {
        $this->test_mode = $mode;
    }
 }