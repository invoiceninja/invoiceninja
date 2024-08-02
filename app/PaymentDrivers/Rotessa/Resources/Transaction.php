<?php

namespace App\PaymentDrivers\Rotessa\Resources;

use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Omnipay\Rotessa\Model\TransactionScheduleModel;

class Transaction extends JsonResource
{
   function __construct($resource) {
      parent::__construct( new TransactionScheduleModel( $resource));
   }

   function jsonSerialize() : array {
     return $this->resource->jsonSerialize();
   }

   function toArray(Request $request) : array {
      return $this->additional + parent::toArray($request);
   }
}
