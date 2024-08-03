<?php
namespace Omnipay\Rotessa\Model;

interface ModelInterface extends \JsonSerializable
{
    public function __toArray();
    public function __toString();
}