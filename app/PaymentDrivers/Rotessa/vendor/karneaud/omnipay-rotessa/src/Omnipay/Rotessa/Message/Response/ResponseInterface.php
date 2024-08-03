<?php
namespace Omnipay\Rotessa\Message\Response;

use Omnipay\Common\Message\ResponseInterface as MessageInterface;

interface ResponseInterface extends MessageInterface
{
    public function getParameter(string $key) ;
}