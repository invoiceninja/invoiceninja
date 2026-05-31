<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Factory;

use App\Models\Tag;

class TagFactory
{
    public static function create(int $company_id, int $user_id): Tag
    {
        $tag = new Tag();
        $tag->user_id = $user_id;
        $tag->company_id = $company_id;
        $tag->entity_type = '';
        $tag->name = '';
        $tag->color = sprintf('#%06X', random_int(0, 0xFFFFFF));

        return $tag;
    }
}
