<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2019. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Utils\Traits;

use App\DataMapper\CompanySettings;
use App\DataMapper\FeesAndLimits;

/**
 * Class CompanyGatewayFeesAndLimitsSaver
 * @package App\Utils\Traits
 */
trait CompanyGatewayFeesAndLimitsSaver
{

	public function validateFeesAndLimits($fees_and_limits)
	{
		$fees_and_limits = (object)$fees_and_limits;
		$casts = FeesAndLimits::$casts;

		foreach ($casts as $key => $value){

			/* Handles unset settings or blank strings */
			if(!property_exists($fees_and_limits, $key) || is_null($fees_and_limits->{$key}) || !isset($fees_and_limits->{$key}) || $fees_and_limits->{$key} == '')
				continue;
			

			/*Catch all filter */
			if(!$this->checkAttribute($value, $fees_and_limits->{$key}))
				return [$key, $value];
			
		}

		return true;
	}

}