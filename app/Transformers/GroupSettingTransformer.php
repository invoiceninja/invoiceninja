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

namespace App\Transformers;

use App\Models\Document;
use App\Models\GroupSetting;
use App\Utils\Traits\MakesHash;
use stdClass;

/**
 * class ClientTransformer.
 */
class GroupSettingTransformer extends EntityTransformer
{
    use MakesHash;

    protected array $defaultIncludes = [
        'documents',
    ];

    /**
     * @var array
     */
    protected array $availableIncludes = [
    ];

    /**
     * @param GroupSetting $group_setting
     * @return array
     */
    public function transform(GroupSetting $group_setting)
    {
        return [
            'id' => $this->encodePrimaryKey($group_setting->id),
            'name' => (string) $group_setting->name ?: '',
            'settings' => $group_setting->settings ?: new stdClass(),
            'created_at' => (int) $group_setting->created_at,
            'updated_at' => (int) $group_setting->updated_at,
            'archived_at' => (int) $group_setting->deleted_at,
            'is_deleted' => (bool) $group_setting->is_deleted,
        ];
    }

    public function includeDocuments(GroupSetting $group_setting)
    {
        $transformer = new DocumentTransformer($this->serializer);

        return $this->includeCollection($group_setting->documents, $transformer, Document::class);
    }
}
