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

use App\Models\PaymentTerm;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;

class PaymentTermsSeeder extends Seeder
{
    public function run()
    {
        Model::unguard();

        $paymentTerms = [
            ['num_days' => 0, 'name' => 'Net 0'],
            ['num_days' => 7,  'name'  => ''],
            ['num_days' => 10, 'name' => ''],
            ['num_days' => 14, 'name' => ''],
            ['num_days' => 15, 'name' => ''],
            ['num_days' => 30, 'name' => ''],
            ['num_days' => 60, 'name' => ''],
            ['num_days' => 90, 'name' => ''],
        ];

        foreach ($paymentTerms as $paymentTerm) {
            PaymentTerm::create($paymentTerm);
        }
    }
}
