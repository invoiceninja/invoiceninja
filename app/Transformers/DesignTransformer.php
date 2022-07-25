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

namespace App\Transformers;

use App\Models\Design;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class DesignTransformer.
 */
class DesignTransformer extends EntityTransformer
{
    use MakesHash;
    use SoftDeletes;

    /**
     * @var array
     */
    protected $defaultIncludes = [
    ];

    /**
     * @var array
     */
    protected $availableIncludes = [
    ];

    /**
     * @param Design $design
     *
     * @return array
     */
    public function transform(Design $design)
    {
        return [
            'id' => (string) $this->encodePrimaryKey($design->id),
            'name' => (string) $design->name,
            'is_custom' => (bool) $design->is_custom,
            'is_active' => (bool) $design->is_active,
            'design' => $design->design,
            'updated_at' => (int) $design->updated_at,
            'archived_at' => (int) $design->deleted_at,
            'created_at' => (int) $design->created_at,
            'is_deleted' => (bool) $design->is_deleted,
            'is_free' => ($design->id <= 4) ? true : false,
        ];
    }
}
