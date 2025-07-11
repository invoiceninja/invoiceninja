<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Repositories;

use App\Models\ProductEquipment;
use App\Utils\Traits\SavesDocuments;

class ProductEquipmentRepository extends BaseRepository
{
    use SavesDocuments;

    /**
     * @param array $data
     * @param ProductEquipment $productEquipment
     * @return ProductEquipment|null
     */
    public function save(array $data, ProductEquipment $productEquipment): ?ProductEquipment
    {
        $productEquipment->fill($data);
        $productEquipment->save();

        if (array_key_exists('documents', $data)) {
            $this->saveDocuments($data['documents'], $productEquipment);
        }

        return $productEquipment;
    }
}
