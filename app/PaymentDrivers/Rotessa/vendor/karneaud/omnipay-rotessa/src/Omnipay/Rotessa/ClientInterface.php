<?php
namespace Omnipay\Rotessa;

use Omnipay\Common\GatewayInterface;
use Omnipay\Rotessa\Message\Request\RequestInterface;

interface ClientInterface extends GatewayInterface
{
    public function getDefaultParameters(): array;
    public function setDefaultParameters(array $data);
}