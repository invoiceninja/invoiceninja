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

namespace App\Factory;

use App\Models\Client;
use App\Models\GroupSetting;

class GroupSettingFactory
{
    public static function create(int $company_id, int $user_id) :GroupSetting
    {
        $settings = new \stdClass;
        $settings->entity = Client::class;

        $gs = new GroupSetting;
        $gs->name = '';
        $gs->company_id = $company_id;
        $gs->user_id = $user_id;
        $gs->settings = $settings;

        return $gs;
    }
}
