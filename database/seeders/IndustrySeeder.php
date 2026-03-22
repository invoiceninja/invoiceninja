<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Database\Seeders;

use App\Models\Industry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;

class IndustrySeeder extends Seeder
{
    public function run()
    {
        Model::unguard();

        $industries = [
            ['id' => 1, 'name' => 'Accounting & Legal'],
            ['id' => 2, 'name' => 'Advertising'],
            ['id' => 3, 'name' => 'Aerospace'],
            ['id' => 4, 'name' => 'Agriculture'],
            ['id' => 5, 'name' => 'Automotive'],
            ['id' => 6, 'name' => 'Banking & Finance'],
            ['id' => 7, 'name' => 'Biotechnology'],
            ['id' => 8, 'name' => 'Broadcasting'],
            ['id' => 9, 'name' => 'Business Services'],
            ['id' => 10, 'name' => 'Commodities & Chemicals'],
            ['id' => 11, 'name' => 'Communications'],
            ['id' => 12, 'name' => 'Computers & Hightech'],
            ['id' => 13, 'name' => 'Defense'],
            ['id' => 14, 'name' => 'Energy'],
            ['id' => 15, 'name' => 'Entertainment'],
            ['id' => 16, 'name' => 'Government'],
            ['id' => 17, 'name' => 'Healthcare & Life Sciences'],
            ['id' => 18, 'name' => 'Insurance'],
            ['id' => 19, 'name' => 'Manufacturing'],
            ['id' => 20, 'name' => 'Marketing'],
            ['id' => 21, 'name' => 'Media'],
            ['id' => 22, 'name' => 'Nonprofit & Higher Ed'],
            ['id' => 23, 'name' => 'Pharmaceuticals'],
            ['id' => 24, 'name' => 'Professional Services & Consulting'],
            ['id' => 25, 'name' => 'Real Estate'],
            ['id' => 26, 'name' => 'Retail & Wholesale'],
            ['id' => 27, 'name' => 'Sports'],
            ['id' => 28, 'name' => 'Transportation'],
            ['id' => 29, 'name' => 'Travel & Luxury'],
            ['id' => 30, 'name' => 'Other'],
            ['id' => 31, 'name' => 'Photography'],
            ['id' => 32, 'name' => 'Construction'],
            ['id' => 33, 'name' => 'Restaurant & Catering'],
        ];

        foreach ($industries as $industry) {
            $record = Industry::whereName($industry['name'])->first();
            if (! $record) {
                Industry::create($industry);
            }
        }
    }
}
