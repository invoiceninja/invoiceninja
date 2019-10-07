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
 * Class Uploadable
 * @package App\Utils\Traits
 */
trait Uploadable
{
	public function uploadLogo($file, $company, $entity)
	{
		if(($file) 
        {

            $path = UploadAvatar::dispatchNow(($file, $company->company_key);

            if($path){

                $settings = $entity->settings;
                $settings->company_logo_url = $company->domain . $path;
                $entity->settings = $settings;
                $entity->save();
            }
            
        }
	}
}