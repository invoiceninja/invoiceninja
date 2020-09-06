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

namespace App\Repositories;

use App\Libraries\MultiDB;
use App\Models\Activity;
use App\Models\Backup;
use App\Models\Client;
use App\Models\Design;
use App\Models\Invoice;
use App\Models\User;
use App\Utils\Traits\MakesInvoiceHtml;
use Illuminate\Support\Facades\Log;

/**
 * Class for activity repository.
 */
class DesignRepository extends BaseRepository
{
    use MakesInvoiceHtml;

    /**
     * Gets the class name.
     *
     * @return     string The class name.
     */
    public function getClassName()
    {
        return Design::class;
    }
}
