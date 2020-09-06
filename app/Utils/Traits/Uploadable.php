<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Utils\Traits;

use App\Jobs\Util\UploadAvatar;

/**
 * Class Uploadable.
 */
trait Uploadable
{
    public function uploadLogo($file, $company, $entity)
    {
        if ($file) {
            $path = UploadAvatar::dispatchNow($file, $company->company_key);

            info("the path {$path}");

            if ($path) {
                $settings = $entity->settings;
                $settings->company_logo = $path;
                $entity->settings = $settings;
                $entity->save();
            }
        }
    }
}
