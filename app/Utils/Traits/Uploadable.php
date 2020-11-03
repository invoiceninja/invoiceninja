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

use App\Jobs\Util\UnlinkFile;
use App\Jobs\Util\UploadAvatar;
use Illuminate\Support\Facades\Storage;

/**
 * Class Uploadable.
 */
trait Uploadable
{

    public function removeLogo($company)
    {
        $company_logo = $company->settings->company_logo;

info("company logo to be deleted = {$company_logo}");

        $file_name = basename($company_logo);

        $storage_path = $company->company_key . '/' . $file_name;

        if (Storage::exists($storage_path)) {
            UnlinkFile::dispatchNow(config('filesystems.default'), $storage_path);
        }


    }

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
