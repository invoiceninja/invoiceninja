<?php

namespace App\PaymentDrivers\Rotessa\Resources;

use Illuminate\Http\Request;
use Omnipay\Rotessa\Model\CustomerModel;
use Illuminate\Http\Resources\Json\JsonResource;

class Customer extends JsonResource
{
   function __construct($resource) {
      parent::__construct( new CustomerModel($resource));
   }

   function jsonSerialize() : array {
     return $this->resource->jsonSerialize();
   }

   function toArray(Request $request) : array {
      return $this->additional + parent::toArray($request);
   }
}
