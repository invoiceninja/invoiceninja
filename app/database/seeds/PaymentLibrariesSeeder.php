<?php

class PaymentLibrariesSeeder extends Seeder
{

	public function run()
	{
		$gateways = [
			array('name'=>'BeanStream', 'provider'=>'BeanStream', 'payment_library_id' => 2),
			array('name'=>'iTransact', 'provider'=>'iTransact', 'payment_library_id' => 2)
		];
		
		$updateProviders = array(
			0 => 'AuthorizeNet_AIM', 
			1 => 'BeanStream', 
			2 => 'iTransact', 
			3 => 'FirstData_Connect', 
			4 => 'PayPal_Pro', 
			5 => 'TwoCheckout'
		);

		foreach ($gateways as $gateway)
		{
			Gateway::create($gateway);
		}
		
		Gateway::whereIn('provider', $updateProviders)->update(array('recommended' => 1));
		
		Gateway::where('provider', '=', 'AuthorizeNet_AIM')->update(array('sort_order' => 5, 'site_url' => 'http://www.authorize.net/'));
		Gateway::where('provider', '=', 'BeanStream')->update(array('sort_order' => 10, 'site_url' => 'http://www.beanstream.com/'));
		Gateway::where('provider', '=', 'iTransact')->update(array('sort_order' => 15, 'site_url' => 'http://itransact.com/'));
		Gateway::where('provider', '=', 'FirstData_Connect')->update(array('sort_order' => 20, 'site_url' => 'https://www.firstdata.com/'));
		Gateway::where('provider', '=', 'PayPal_Pro')->update(array('sort_order' => 25, 'site_url' => 'https://www.paypal.com/'));
		Gateway::where('provider', '=', 'TwoCheckout')->update(array('sort_order' => 30, 'site_url' => 'https://www.2checkout.com/'));
	}
}