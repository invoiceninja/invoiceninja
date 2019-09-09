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

	public function findCompanyGateways()
	{
		$settings = $this->getMergedSettings();

		if(isset($settings->groups->company_gateways))
		{

		}
	}

}