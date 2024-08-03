<?php

namespace App\DataProviders;

use Omnipay\Rotessa\Object\Frequency;

final class Frequencies 
{
   public static function get() : array {
      return Frequency::getTypes();
   }

   public static function getFromType() {

   }
   public static function getOnePayment() {
      return Frequency::ONCE;
   }
}
