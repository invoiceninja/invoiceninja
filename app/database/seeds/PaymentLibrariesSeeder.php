<?php

class PaymentLibrariesSeeder extends Seeder
{

	public function run()
	{
		$gateways = [
			array('name'=>'Authorize.Net AIM', 'provider'=>'AuthorizeNet_AIM', 'payment_library_id' => 2),
			array('name'=>'Authorize.Net SIM', 'provider'=>'AuthorizeNet_SIM', 'payment_library_id' => 2),
			array('name'=>'CardSave', 'provider'=>'CardSave', 'payment_library_id' => 2),
			array('name'=>'Eway Rapid', 'provider'=>'Eway_Rapid', 'payment_library_id' => 2),
			array('name'=>'FirstData Connect', 'provider'=>'FirstData_Connect', 'payment_library_id' => 2),
			array('name'=>'GoCardless', 'provider'=>'GoCardless', 'payment_library_id' => 2),
			array('name'=>'Migs ThreeParty', 'provider'=>'Migs_ThreeParty', 'payment_library_id' => 2),
			array('name'=>'Migs TwoParty', 'provider'=>'Migs_TwoParty', 'payment_library_id' => 2),
			array('name'=>'Mollie', 'provider'=>'Mollie', 'payment_library_id' => 2),
			array('name'=>'MultiSafepay', 'provider'=>'MultiSafepay', 'payment_library_id' => 2),
			array('name'=>'Netaxept', 'provider'=>'Netaxept', 'payment_library_id' => 2),
			array('name'=>'NetBanx', 'provider'=>'NetBanx', 'payment_library_id' => 2),
			array('name'=>'PayFast', 'provider'=>'PayFast', 'payment_library_id' => 2),
			array('name'=>'Payflow Pro', 'provider'=>'Payflow_Pro', 'payment_library_id' => 2),
			array('name'=>'PaymentExpress PxPay', 'provider'=>'PaymentExpress_PxPay', 'payment_library_id' => 2),
			array('name'=>'PaymentExpress PxPost', 'provider'=>'PaymentExpress_PxPost', 'payment_library_id' => 2),
			array('name'=>'PayPal Express', 'provider'=>'PayPal_Express', 'payment_library_id' => 2),
			array('name'=>'PayPal Pro', 'provider'=>'PayPal_Pro', 'payment_library_id' => 2),
			array('name'=>'Pin', 'provider'=>'Pin', 'payment_library_id' => 2),
			array('name'=>'SagePay Direct', 'provider'=>'SagePay_Direct', 'payment_library_id' => 2),
			array('name'=>'SagePay Server', 'provider'=>'SagePay_Server', 'payment_library_id' => 2),
			array('name'=>'SecurePay DirectPost', 'provider'=>'SecurePay_DirectPost', 'payment_library_id' => 2),
			array('name'=>'Stripe', 'provider'=>'Stripe', 'payment_library_id' => 2),
			array('name'=>'TargetPay Direct eBanking', 'provider'=>'TargetPay_Directebanking', 'payment_library_id' => 2),
			array('name'=>'TargetPay Ideal', 'provider'=>'TargetPay_Ideal', 'payment_library_id' => 2),
			array('name'=>'TargetPay Mr Cash', 'provider'=>'TargetPay_Mrcash', 'payment_library_id' => 2),
			array('name'=>'TwoCheckout', 'provider'=>'TwoCheckout', 'payment_library_id' => 2),
			array('name'=>'WorldPay', 'provider'=>'WorldPay', 'payment_library_id' => 2)
		];

		foreach ($gateways as $gateway)
		{
			Gateway::create($gateway);
		}
	}
}