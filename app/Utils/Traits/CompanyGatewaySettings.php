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

/**
 * Class CompanyGatewaySettings
 * @package App\Utils\Traits
 */
trait CompanyGatewaySettings
{

	/**
	 * 
	 * This method will cascade through a sequence of
	 * levels and return the first available set of settings
	 * it hits
	 * 
	 * @return array  A single dimension array of company gateway ids
	 */
	public function findCompanyGateways()
	{
		$settings = $this->getMergedSettings();

		/* Group Level */
		if(isset($settings->groups->company_gateways)){
			$gateways = $this->company->company_gateways->whereIn('id', $settings->group_selectors->{$settings->group->company_gateways});
		}
		/* Client Level - Company Level*/
		else if(isset($settings->company_gateways)) {
			$gateways = $this->company->company_gateways->whereIn('id', $settings->company_gateways);
		}
		/* DB raw*/
		else
			return $this->company->company_gateways;

	}

}