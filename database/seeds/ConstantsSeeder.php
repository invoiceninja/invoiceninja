<?php

use App\Models\PaymentType;
use App\Models\Theme;
use App\Models\InvoiceStatus;
use App\Models\Frequency;
use App\Models\Industry;
use App\Models\Size;
use App\Models\PaymentTerm;
use App\Models\Currency;
use App\Models\DatetimeFormat;
use App\Models\DateFormat;
use App\Models\PaymentLibrary;
use App\Models\Gateway;
use App\Models\Timezone;


class ConstantsSeeder extends Seeder
{

	public function run()
	{
		PaymentType::create(array('name' => 'Apply Credit'));
		PaymentType::create(array('name' => 'Bank Transfer'));
		PaymentType::create(array('name' => 'Cash'));
		PaymentType::create(array('name' => 'Debit'));
		PaymentType::create(array('name' => 'ACH'));
		PaymentType::create(array('name' => 'Visa Card'));
		PaymentType::create(array('name' => 'MasterCard'));
		PaymentType::create(array('name' => 'American Express'));
		PaymentType::create(array('name' => 'Discover Card'));
		PaymentType::create(array('name' => 'Diners Card'));
		PaymentType::create(array('name' => 'EuroCard'));
		PaymentType::create(array('name' => 'Nova'));
		PaymentType::create(array('name' => 'Credit Card Other'));
		PaymentType::create(array('name' => 'PayPal'));
		PaymentType::create(array('name' => 'Google Wallet'));
		PaymentType::create(array('name' => 'Check'));

		Theme::create(array('name' => 'amelia'));
		Theme::create(array('name' => 'cerulean'));
		Theme::create(array('name' => 'cosmo'));
		Theme::create(array('name' => 'cyborg'));
		Theme::create(array('name' => 'flatly'));
		Theme::create(array('name' => 'journal'));
		Theme::create(array('name' => 'readable'));
		Theme::create(array('name' => 'simplex'));
		Theme::create(array('name' => 'slate'));
		Theme::create(array('name' => 'spacelab'));
		Theme::create(array('name' => 'united'));
		Theme::create(array('name' => 'yeti'));

		InvoiceStatus::create(array('name' => 'Draft'));
		InvoiceStatus::create(array('name' => 'Sent'));
		InvoiceStatus::create(array('name' => 'Viewed'));
		InvoiceStatus::create(array('name' => 'Partial'));
		InvoiceStatus::create(array('name' => 'Paid'));		

		Frequency::create(array('name' => 'Weekly'));
		Frequency::create(array('name' => 'Two weeks'));
		Frequency::create(array('name' => 'Four weeks'));
		Frequency::create(array('name' => 'Monthly'));
		Frequency::create(array('name' => 'Three months'));
		Frequency::create(array('name' => 'Six months'));
		Frequency::create(array('name' => 'Annually'));

		Industry::create(array('name' => 'Accounting & Legal'));
		Industry::create(array('name' => 'Advertising'));
		Industry::create(array('name' => 'Aerospace'));
		Industry::create(array('name' => 'Agriculture'));
		Industry::create(array('name' => 'Automotive'));
		Industry::create(array('name' => 'Banking & Finance'));
		Industry::create(array('name' => 'Biotechnology'));
		Industry::create(array('name' => 'Broadcasting'));
		Industry::create(array('name' => 'Business Services'));
		Industry::create(array('name' => 'Commodities & Chemicals'));
		Industry::create(array('name' => 'Communications'));
		Industry::create(array('name' => 'Computers & Hightech'));
		Industry::create(array('name' => 'Defense'));
		Industry::create(array('name' => 'Energy'));
		Industry::create(array('name' => 'Entertainment'));
		Industry::create(array('name' => 'Government'));
		Industry::create(array('name' => 'Healthcare & Life Sciences'));
		Industry::create(array('name' => 'Insurance'));
		Industry::create(array('name' => 'Manufacturing'));
		Industry::create(array('name' => 'Marketing'));
		Industry::create(array('name' => 'Media'));
		Industry::create(array('name' => 'Nonprofit & Higher Ed'));
		Industry::create(array('name' => 'Pharmaceuticals'));
		Industry::create(array('name' => 'Professional Services & Consulting'));
		Industry::create(array('name' => 'Real Estate'));
		Industry::create(array('name' => 'Retail & Wholesale'));
		Industry::create(array('name' => 'Sports'));
		Industry::create(array('name' => 'Transportation'));
		Industry::create(array('name' => 'Travel & Luxury'));
		Industry::create(array('name' => 'Other'));
		Industry::create(array('name' => 'Photography'));

		Size::create(array('name' => '1 - 3'));
		Size::create(array('name' => '4 - 10'));
		Size::create(array('name' => '11 - 50'));
		Size::create(array('name' => '51 - 100'));
		Size::create(array('name' => '101 - 500'));
		Size::create(array('name' => '500+'));		

        PaymentTerm::create(array('num_days' => 7, 'name' => 'Net 7'));
		PaymentTerm::create(array('num_days' => 10, 'name' => 'Net 10'));
		PaymentTerm::create(array('num_days' => 14, 'name' => 'Net 14'));
		PaymentTerm::create(array('num_days' => 15, 'name' => 'Net 15'));
		PaymentTerm::create(array('num_days' => 30, 'name' => 'Net 30'));
		PaymentTerm::create(array('num_days' => 60, 'name' => 'Net 60'));
		PaymentTerm::create(array('num_days' => 90, 'name' => 'Net 90'));
        
		PaymentLibrary::create(['name' => 'Omnipay']);
        PaymentLibrary::create(['name' => 'PHP-Payments [Deprecated]']);

		/*	
		d, dd: Numeric date, no leading zero and leading zero, respectively. Eg, 5, 05.
		D, DD: Abbreviated and full weekday names, respectively. Eg, Mon, Monday.
		m, mm: Numeric month, no leading zero and leading zero, respectively. Eg, 7, 07.
		M, MM: Abbreviated and full month names, respectively. Eg, Jan, January
		yy, yyyy: 2- and 4-digit years, respectively. Eg, 12, 2012.)
		*/

		$gateways = [
			array('name'=>'Authorize.Net AIM', 'provider'=>'AuthorizeNet_AIM'),
			array('name'=>'Authorize.Net SIM', 'provider'=>'AuthorizeNet_SIM'),
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

		$timezones = array(
		    'Pacific/Midway'       => "(GMT-11:00) Midway Island",
		    'US/Samoa'             => "(GMT-11:00) Samoa",
		    'US/Hawaii'            => "(GMT-10:00) Hawaii",
		    'US/Alaska'            => "(GMT-09:00) Alaska",
		    'US/Pacific'           => "(GMT-08:00) Pacific Time (US &amp; Canada)",
		    'America/Tijuana'      => "(GMT-08:00) Tijuana",
		    'US/Arizona'           => "(GMT-07:00) Arizona",
		    'US/Mountain'          => "(GMT-07:00) Mountain Time (US &amp; Canada)",
		    'America/Chihuahua'    => "(GMT-07:00) Chihuahua",
		    'America/Mazatlan'     => "(GMT-07:00) Mazatlan",
		    'America/Mexico_City'  => "(GMT-06:00) Mexico City",
		    'America/Monterrey'    => "(GMT-06:00) Monterrey",
		    'Canada/Saskatchewan'  => "(GMT-06:00) Saskatchewan",
		    'US/Central'           => "(GMT-06:00) Central Time (US &amp; Canada)",
		    'US/Eastern'           => "(GMT-05:00) Eastern Time (US &amp; Canada)",
		    'US/East-Indiana'      => "(GMT-05:00) Indiana (East)",
		    'America/Bogota'       => "(GMT-05:00) Bogota",
		    'America/Lima'         => "(GMT-05:00) Lima",
		    'America/Caracas'      => "(GMT-04:30) Caracas",
		    'Canada/Atlantic'      => "(GMT-04:00) Atlantic Time (Canada)",
		    'America/La_Paz'       => "(GMT-04:00) La Paz",
		    'America/Santiago'     => "(GMT-04:00) Santiago",
		    'Canada/Newfoundland'  => "(GMT-03:30) Newfoundland",
		    'America/Buenos_Aires' => "(GMT-03:00) Buenos Aires",
		    'Greenland'            => "(GMT-03:00) Greenland",
		    'Atlantic/Stanley'     => "(GMT-02:00) Stanley",
		    'Atlantic/Azores'      => "(GMT-01:00) Azores",
		    'Atlantic/Cape_Verde'  => "(GMT-01:00) Cape Verde Is.",
		    'Africa/Casablanca'    => "(GMT) Casablanca",
		    'Europe/Dublin'        => "(GMT) Dublin",
		    'Europe/Lisbon'        => "(GMT) Lisbon",
		    'Europe/London'        => "(GMT) London",
		    'Africa/Monrovia'      => "(GMT) Monrovia",
		    'Europe/Amsterdam'     => "(GMT+01:00) Amsterdam",
		    'Europe/Belgrade'      => "(GMT+01:00) Belgrade",
		    'Europe/Berlin'        => "(GMT+01:00) Berlin",
		    'Europe/Bratislava'    => "(GMT+01:00) Bratislava",
		    'Europe/Brussels'      => "(GMT+01:00) Brussels",
		    'Europe/Budapest'      => "(GMT+01:00) Budapest",
		    'Europe/Copenhagen'    => "(GMT+01:00) Copenhagen",
		    'Europe/Ljubljana'     => "(GMT+01:00) Ljubljana",
		    'Europe/Madrid'        => "(GMT+01:00) Madrid",
		    'Europe/Paris'         => "(GMT+01:00) Paris",
		    'Europe/Prague'        => "(GMT+01:00) Prague",
		    'Europe/Rome'          => "(GMT+01:00) Rome",
		    'Europe/Sarajevo'      => "(GMT+01:00) Sarajevo",
		    'Europe/Skopje'        => "(GMT+01:00) Skopje",
		    'Europe/Stockholm'     => "(GMT+01:00) Stockholm",
		    'Europe/Vienna'        => "(GMT+01:00) Vienna",
		    'Europe/Warsaw'        => "(GMT+01:00) Warsaw",
		    'Europe/Zagreb'        => "(GMT+01:00) Zagreb",
		    'Europe/Athens'        => "(GMT+02:00) Athens",
		    'Europe/Bucharest'     => "(GMT+02:00) Bucharest",
		    'Africa/Cairo'         => "(GMT+02:00) Cairo",
		    'Africa/Harare'        => "(GMT+02:00) Harare",
		    'Europe/Helsinki'      => "(GMT+02:00) Helsinki",
		    'Europe/Istanbul'      => "(GMT+02:00) Istanbul",
		    'Asia/Jerusalem'       => "(GMT+02:00) Jerusalem",
		    'Europe/Kiev'          => "(GMT+02:00) Kyiv",
		    'Europe/Minsk'         => "(GMT+02:00) Minsk",
		    'Europe/Riga'          => "(GMT+02:00) Riga",
		    'Europe/Sofia'         => "(GMT+02:00) Sofia",
		    'Europe/Tallinn'       => "(GMT+02:00) Tallinn",
		    'Europe/Vilnius'       => "(GMT+02:00) Vilnius",
		    'Asia/Baghdad'         => "(GMT+03:00) Baghdad",
		    'Asia/Kuwait'          => "(GMT+03:00) Kuwait",
		    'Africa/Nairobi'       => "(GMT+03:00) Nairobi",
		    'Asia/Riyadh'          => "(GMT+03:00) Riyadh",
		    'Asia/Tehran'          => "(GMT+03:30) Tehran",
		    'Europe/Moscow'        => "(GMT+04:00) Moscow",
		    'Asia/Baku'            => "(GMT+04:00) Baku",
		    'Europe/Volgograd'     => "(GMT+04:00) Volgograd",
		    'Asia/Muscat'          => "(GMT+04:00) Muscat",
		    'Asia/Tbilisi'         => "(GMT+04:00) Tbilisi",
		    'Asia/Yerevan'         => "(GMT+04:00) Yerevan",
		    'Asia/Kabul'           => "(GMT+04:30) Kabul",
		    'Asia/Karachi'         => "(GMT+05:00) Karachi",
		    'Asia/Tashkent'        => "(GMT+05:00) Tashkent",
		    'Asia/Kolkata'         => "(GMT+05:30) Kolkata",
		    'Asia/Kathmandu'       => "(GMT+05:45) Kathmandu",
		    'Asia/Yekaterinburg'   => "(GMT+06:00) Ekaterinburg",
		    'Asia/Almaty'          => "(GMT+06:00) Almaty",
		    'Asia/Dhaka'           => "(GMT+06:00) Dhaka",
		    'Asia/Novosibirsk'     => "(GMT+07:00) Novosibirsk",
		    'Asia/Bangkok'         => "(GMT+07:00) Bangkok",
            'Asia/Ho_Chi_Minh'     => "(GMT+07.00) Ho Chi Minh",
		    'Asia/Jakarta'         => "(GMT+07:00) Jakarta",
		    'Asia/Krasnoyarsk'     => "(GMT+08:00) Krasnoyarsk",
		    'Asia/Chongqing'       => "(GMT+08:00) Chongqing",
		    'Asia/Hong_Kong'       => "(GMT+08:00) Hong Kong",
		    'Asia/Kuala_Lumpur'    => "(GMT+08:00) Kuala Lumpur",
		    'Australia/Perth'      => "(GMT+08:00) Perth",
		    'Asia/Singapore'       => "(GMT+08:00) Singapore",
		    'Asia/Taipei'          => "(GMT+08:00) Taipei",
		    'Asia/Ulaanbaatar'     => "(GMT+08:00) Ulaan Bataar",
		    'Asia/Urumqi'          => "(GMT+08:00) Urumqi",
		    'Asia/Irkutsk'         => "(GMT+09:00) Irkutsk",
		    'Asia/Seoul'           => "(GMT+09:00) Seoul",
		    'Asia/Tokyo'           => "(GMT+09:00) Tokyo",
		    'Australia/Adelaide'   => "(GMT+09:30) Adelaide",
		    'Australia/Darwin'     => "(GMT+09:30) Darwin",
		    'Asia/Yakutsk'         => "(GMT+10:00) Yakutsk",
		    'Australia/Brisbane'   => "(GMT+10:00) Brisbane",
		    'Australia/Canberra'   => "(GMT+10:00) Canberra",
		    'Pacific/Guam'         => "(GMT+10:00) Guam",
		    'Australia/Hobart'     => "(GMT+10:00) Hobart",
		    'Australia/Melbourne'  => "(GMT+10:00) Melbourne",
		    'Pacific/Port_Moresby' => "(GMT+10:00) Port Moresby",
		    'Australia/Sydney'     => "(GMT+10:00) Sydney",
		    'Asia/Vladivostok'     => "(GMT+11:00) Vladivostok",
		    'Asia/Magadan'         => "(GMT+12:00) Magadan",
		    'Pacific/Auckland'     => "(GMT+12:00) Auckland",
		    'Pacific/Fiji'         => "(GMT+12:00) Fiji",
		);
	
		foreach ($timezones as $name => $location) {
			Timezone::create(array('name'=>$name, 'location'=>$location));
		}
	}
}
