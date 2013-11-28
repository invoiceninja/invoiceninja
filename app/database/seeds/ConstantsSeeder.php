<?php

class ConstantsSeeder extends Seeder
{

	public function run()
	{
		DB::table('gateways')->delete();
		
		$gateways = [
			array('name'=>'Authorize.Net AIM', 'provider'=>'AuthorizeNet_AIM'),
			array('name'=>'Authorize.Net SIM', 'provider'=>'AuthorizeNet_SIM'),
			array('name'=>'Buckaroo', 'provider'=>'Buckaroo'),
			array('name'=>'Buckaroo Ideal', 'provider'=>'Buckaroo_Ideal'),
			array('name'=>'Buckaroo PayPal', 'provider'=>'Buckaroo_PayPal'),
			array('name'=>'CardSave', 'provider'=>'CardSave'),
			array('name'=>'Eway Rapid', 'provider'=>'Eway_Rapid'),
			array('name'=>'FirstData Connect', 'provider'=>'FirstData_Connect'),
			array('name'=>'GoCardless', 'provider'=>'GoCardless'),
			array('name'=>'Migs ThreeParty', 'provider'=>'Migs_ThreeParty'),
			array('name'=>'Migs TwoParty', 'provider'=>'Migs_TwoParty'),
			array('name'=>'Mollie', 'provider'=>'Mollie'),
			array('name'=>'MultiSafepay', 'provider'=>'MultiSafepay'),
			array('name'=>'Netaxept', 'provider'=>'Netaxept'),
			array('name'=>'NetBanx', 'provider'=>'NetBanx'),
			array('name'=>'PayFast', 'provider'=>'PayFast'),
			array('name'=>'Payflow Pro', 'provider'=>'Payflow_Pro'),
			array('name'=>'PaymentExpress PxPay', 'provider'=>'PaymentExpress_PxPay'),
			array('name'=>'PaymentExpress PxPost', 'provider'=>'PaymentExpress_PxPost'),
			array('name'=>'PayPal Express', 'provider'=>'PayPal_Express'),
			array('name'=>'PayPal Pro', 'provider'=>'PayPal_Pro'),
			array('name'=>'Pin', 'provider'=>'Pin'),
			array('name'=>'SagePay Direct', 'provider'=>'SagePay_Direct'),
			array('name'=>'SagePay Server', 'provider'=>'SagePay_Server'),
			array('name'=>'SecurePay DirectPost', 'provider'=>'SecurePay_DirectPost'),
			array('name'=>'Stripe', 'provider'=>'Stripe'),
			array('name'=>'TargetPay Direct eBanking', 'provider'=>'TargetPay_Directebanking'),
			array('name'=>'TargetPay Ideal', 'provider'=>'TargetPay_Ideal'),
			array('name'=>'TargetPay Mr Cash', 'provider'=>'TargetPay_Mrcash'),
			array('name'=>'TwoCheckout', 'provider'=>'TwoCheckout'),
			array('name'=>'WorldPay', 'provider'=>'WorldPay'),
		];

		foreach ($gateways as $gateway)
		{
			Gateway::create($gateway);
		}
	}
}