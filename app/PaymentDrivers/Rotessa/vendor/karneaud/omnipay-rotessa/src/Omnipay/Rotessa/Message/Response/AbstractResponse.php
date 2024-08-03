<?php
namespace Omnipay\Rotessa\Message\Response;

use Omnipay\Common\Message\AbstractResponse as Response;

abstract class AbstractResponse extends Response implements ResponseInterface
{
    
    abstract public function getData();
    
    abstract public function getCode();

    abstract public function getMessage();

    abstract public function getParameter(string $key);
}