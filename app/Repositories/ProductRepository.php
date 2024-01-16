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

namespace App\Repositories;

use App\Models\Product;
use App\Utils\Traits\SavesDocuments;

class ProductRepository extends BaseRepository
{
    use SavesDocuments;

    /**
     * @param array $data
     * @param Product $product
     * @return Product|null
     */
    public function save(array $data, Product $product): ?Product
    {
        $product->fill($data);
        $product->save();

        if (array_key_exists('documents', $data)) {
            $this->saveDocuments($data['documents'], $product);
        }

        return $product;
    }
}
