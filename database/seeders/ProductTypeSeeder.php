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

namespace Database\Seeders;

use App\Models\ProductType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;

class ProductTypeSeeder extends Seeder
{
    public function run()
    {
        Model::unguard();

        $this->createProductTypes();
    }

    private function createProductTypes()
    {
        $productTypes = [
            ['id' => 1, 'name' => 'Physical', 'user_id' => null, 'company_id' => null, 'is_custom' => false, 'unit_of_measure' => 'EA', 'allocation_aggregation_interval' => null, 'allocation_max_quantity' => null, 'serial_number_required' => null, 'is_active' => true],
            ['id' => 2, 'name' => 'Digital', 'user_id' => null, 'company_id' => null, 'is_custom' => false, 'unit_of_measure' => 'EA', 'allocation_aggregation_interval' => null, 'allocation_max_quantity' => null, 'serial_number_required' => null, 'is_active' => true],
            ['id' => 3, 'name' => 'Usage', 'user_id' => null, 'company_id' => null, 'is_custom' => false, 'unit_of_measure' => 'EA', 'allocation_aggregation_interval' => 86400, 'allocation_max_quantity' => null, 'serial_number_required' => false, 'is_active' => true],
            ['id' => 4, 'name' => 'Rental', 'user_id' => null, 'company_id' => null, 'is_custom' => false, 'unit_of_measure' => 'H', 'allocation_aggregation_interval' => null, 'allocation_max_quantity' => 1, 'serial_number_required' => null, 'is_active' => true],
            ['id' => 5, 'name' => 'Labor', 'user_id' => null, 'company_id' => null, 'is_custom' => false, 'unit_of_measure' => 'H', 'allocation_aggregation_interval' => 86400, 'allocation_max_quantity' => null, 'serial_number_required' => false, 'is_active' => true],
        ];

        foreach ($productTypes as $productType) {
            $record = ProductType::find($productType['id']);

            if ($record) {
                $record->id = $productType['id'];
                $record->name = $productType['name'];
                $record->user_id = $productType['user_id'];
                $record->company_id = $productType['company_id'];
                $record->is_custom = $productType['is_custom'];
                $record->unit_of_measure = $productType['unit_of_measure'];
                $record->allocation_aggregation_interval = $productType['allocation_aggregation_interval'];
                $record->allocation_max_quantity = $productType['allocation_max_quantity'];
                $record->serial_number_required = $productType['serial_number_required'];
                $record->is_active = $productType['is_active'];

                $record->save();
            } else {
                ProductType::create($productType);
            }
        }
    }
}
