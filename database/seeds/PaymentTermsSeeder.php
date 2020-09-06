<?php

use App\Models\PaymentTerm;
use Illuminate\Database\Seeder;

class PaymentTermsSeeder extends Seeder
{
    public function run()
    {
        Eloquent::unguard();

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
