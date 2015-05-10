<?php

use App\Models\Gateway;
use App\Models\PaymentTerm;

class PaymentLibrariesSeeder extends Seeder
{

	public function run()
	{
		Eloquent::unguard();

		$gateways = [
			['name' => 'BeanStream', 'provider' => 'BeanStream', 'payment_library_id' => 2],
			['name' => 'Psigate', 'provider' => 'Psigate', 'payment_library_id' => 2],
			['name' => 'moolah', 'provider' => 'AuthorizeNet_AIM', 'sort_order' => 1, 'recommended' => 1, 'site_url' => 'https://invoiceninja.mymoolah.com/', 'payment_library_id' => 1],
			['name' => 'Alipay', 'provider' => 'Alipay_Express', 'payment_library_id' => 1],
			['name' => 'Buckaroo', 'provider' => 'Buckaroo_CreditCard', 'payment_library_id' => 1],
			['name' => 'Coinbase', 'provider' => 'Coinbase', 'payment_library_id' => 1],
			['name' => 'DataCash', 'provider' => 'DataCash', 'payment_library_id' => 1],
			['name' => 'Neteller', 'provider' => 'Neteller', 'payment_library_id' => 1],
			['name' => 'Pacnet', 'provider' => 'Pacnet', 'payment_library_id' => 1],
			['name' => 'PaymentSense', 'provider' => 'PaymentSense', 'payment_library_id' => 1],
			['name' => 'Realex', 'provider' => 'Realex_Remote', 'payment_library_id' => 1],
			['name' => 'Sisow', 'provider' => 'Sisow', 'payment_library_id' => 1],
			['name' => 'Skrill', 'provider' => 'Skrill', 'payment_library_id' => 1],
            ['name' => 'BitPay', 'provider' => 'BitPay', 'payment_library_id' => 1],
		];
		
		foreach ($gateways as $gateway)
		{
			if (!DB::table('gateways')->where('name', '=', $gateway['name'])->get())	
			{
				Gateway::create($gateway);
			}
		}

        $paymentTerms = [
            ['num_days' => -1, 'name' => 'Net 0']
        ];

        foreach ($paymentTerms as $paymentTerm)
        {
            if (!DB::table('payment_terms')->where('name', '=', $paymentTerm['name'])->get())
            {
                PaymentTerm::create($paymentTerm);
            }
        }
	}
}