<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Factory;

use App\DataProviders\DesignBlocks;
use App\Models\Design;

class DesignFactory
{
    public static function create(int $company_id, int $user_id): Design
    {
        $design = new Design();
        $design->user_id = $user_id;
        $design->company_id = $company_id;
        $design->is_deleted = false;
        $design->is_active = true;
        $design->is_custom = true;
        $design->name = '';
        $design->is_template = false;
        $design->entities = '';
        $design->design = new DesignBlocks();

        return $design;
    }
}
