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

namespace App\Transformers;

use App\Models\Tag;
use App\Utils\Traits\MakesHash;

class TagTransformer extends EntityTransformer
{
    use MakesHash;

    public function transform(Tag $tag): array
    {
        return [
            'id' => (string) $this->encodePrimaryKey($tag->id),
            'entity_type' => (string) $tag->entity_type,
            'name' => (string) $tag->name,
            'color' => $tag->color === null ? null : (string) $tag->color,
            'is_deleted' => (bool) $tag->is_deleted,
            'created_at' => (int) $tag->created_at,
            'updated_at' => (int) $tag->updated_at,
            'archived_at' => (int) $tag->deleted_at,
        ];
    }
}
