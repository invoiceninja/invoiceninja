<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Repositories;

use App\Models\Design;
use Illuminate\Support\Str;

/**
 * Class for DesignRepository .
 */
class DesignRepository extends BaseRepository
{
    public function delete($design) :Design
    {
        $design->name = $design->name.'_deleted_'.Str::random(5);

        parent::delete($design);

        return $design;
    }
}
