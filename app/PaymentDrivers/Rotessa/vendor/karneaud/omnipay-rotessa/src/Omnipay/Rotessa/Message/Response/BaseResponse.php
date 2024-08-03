<?php
namespace Omnipay\Rotessa\Message\Response;

use Omnipay\Rotessa\Message\Request\RequestInterface;
use Omnipay\Rotessa\Message\Response\ResponseInterface;
use Omnipay\Common\Message\AbstractResponse as Response;

class BaseResponse extends Response implements ResponseInterface
{

    protected $code = 0;
    protected $message = null;

    function __construct(RequestInterface $request, array $data = [], int $code = 200, string $message = null ) {
        parent::__construct($request, $data);

        $this->code = $code;
        $this->message = $message;
    }

    public function getData() {
        return $this->getParameters(); 
    }
    
    public function getCode() {
        return (int) $this->code;
    }

    public function isSuccessful() {
        return $this->code < 300;
    }

    public function getMessage() {
        return $this->message;
    }

    protected function getParameters() {
        return $this->data;
    }

    public function getParameter(string $key) {
        return $this->getParameters()[$key];
    }
}