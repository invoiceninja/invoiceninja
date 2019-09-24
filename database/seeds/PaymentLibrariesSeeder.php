<?php

use App\Models\Gateway;
use Illuminate\Database\Seeder;

class PaymentLibrariesSeeder extends Seeder
{
    public function run()
    {
        Eloquent::unguard();

        $gateways = [
            ['name' => 'Authorize.Net AIM', 'provider' => 'AuthorizeNet_AIM', 'sort_order' => 5, 'key' => '3b6621f970ab18887c4f6dca78d3f8bb'],
            ['name' => 'CardSave', 'provider' => 'CardSave', 'key' => '46c5c1fed2c43acf4f379bae9c8b9f76'],
            ['name' => 'Eway Rapid', 'provider' => 'Eway_RapidShared', 'is_offsite' => true, 'key' => '944c20175bbe6b9972c05bcfe294c2c7'],
            ['name' => 'FirstData Connect', 'provider' => 'FirstData_Connect', 'key' => '4e0ed0d34552e6cb433506d1ac03a418'],
            ['name' => 'Migs ThreeParty', 'provider' => 'Migs_ThreeParty', 'key' => '513cdc81444c87c4b07258bc2858d3fa'],
            ['name' => 'Migs TwoParty', 'provider' => 'Migs_TwoParty', 'key' => '99c2a271b5088951334d1302e038c01a'],
            ['name' => 'Mollie', 'provider' => 'Mollie', 'is_offsite' => true, 'sort_order' => 8, 'key' => '1bd651fb213ca0c9d66ae3c336dc77e8'],
            ['name' => 'MultiSafepay', 'provider' => 'MultiSafepay', 'key' => 'c3dec814e14cbd7d86abd92ce6789f8c'],
            ['name' => 'Netaxept', 'provider' => 'Netaxept', 'key' => '070dffc5ca94f4e66216e44028ebd52d'],
            ['name' => 'NetBanx', 'provider' => 'NetBanx', 'key' => '334d419939c06bd99b4dfd8a49243f0f'],
            ['name' => 'PayFast', 'provider' => 'PayFast', 'is_offsite' => true, 'key' => 'd6814fc83f45d2935e7777071e629ef9'],
            ['name' => 'Payflow Pro', 'provider' => 'Payflow_Pro', 'key' => '0d97c97d227f91c5d0cb86d01e4a52c9'],
            ['name' => 'PaymentExpress PxPay', 'provider' => 'PaymentExpress_PxPay', 'key' => 'a66b7062f4c8212d2c428209a34aa6bf'],
            ['name' => 'PaymentExpress PxPost', 'provider' => 'PaymentExpress_PxPost', 'key' => '7e6fc08b89467518a5953a4839f8baba'],
            ['name' => 'PayPal Express', 'provider' => 'PayPal_Express', 'is_offsite' => true, 'sort_order' => 4, 'key' => '38f2c48af60c7dd69e04248cbb24c36e'],
            ['name' => 'PayPal Pro', 'provider' => 'PayPal_Pro', 'key' => '80af24a6a69f5c0bbec33e930ab40665'],
            ['name' => 'Pin', 'provider' => 'Pin', 'key' => '0749cb92a6b36c88bd9ff8aabd2efcab'],
            ['name' => 'SagePay Direct', 'provider' => 'SagePay_Direct', 'key' => '4c8f4e5d0f353a122045eb9a60cc0f2d'],
            ['name' => 'SecurePay DirectPost', 'provider' => 'SecurePay_DirectPost', 'key' => '8036a5aadb2bdaafb23502da8790b6a2'],
            ['name' => 'Stripe', 'provider' => 'Stripe', 'sort_order' => 1, 'key' => 'd14dd26a37cecc30fdd65700bfb55b23'],
            ['name' => 'TargetPay Direct eBanking', 'provider' => 'TargetPay_Directebanking', 'key' => 'd14dd26a37cdcc30fdd65700bfb55b23'],
            ['name' => 'TargetPay Ideal', 'provider' => 'TargetPay_Ideal', 'key' => 'ea3b328bd72d381387281c3bd83bd97c'],
            ['name' => 'TargetPay Mr Cash', 'provider' => 'TargetPay_Mrcash', 'key' => 'a0035fc0d87c4950fb82c73e2fcb825a'],
            ['name' => 'TwoCheckout', 'provider' => 'TwoCheckout', 'is_offsite' => true, 'key' => '16dc1d3c8a865425421f64463faaf768'],
            ['name' => 'WorldPay', 'provider' => 'WorldPay', 'key' => '43e639234f660d581ddac725ba7bcd29'],
            ['name' => 'moolah', 'provider' => 'AuthorizeNet_AIM', 'key' => '2f71dc17b0158ac30a7ae0839799e888'],
            ['name' => 'Alipay', 'provider' => 'Alipay_Express', 'key' => '733998ee4760b10f11fb48652571e02c'],
            ['name' => 'Buckaroo', 'provider' => 'Buckaroo_CreditCard', 'key' => '6312879223e49c5cf92e194646bdee8f'],
            ['name' => 'Coinbase', 'provider' => 'Coinbase', 'is_offsite' => true, 'key' => '106ef7e7da9062b0df363903b455711c'],
            ['name' => 'DataCash', 'provider' => 'DataCash', 'key' => 'e9a38f0896b5b82d196be3b7020c8664'],
            ['name' => 'Pacnet', 'provider' => 'Pacnet', 'key' => '0da4e18ed44a5bd5c8ec354d0ab7b301'],
            ['name' => 'Realex', 'provider' => 'Realex_Remote', 'key' => 'd3979e62eb603fbdf1c78fe3a8ba7009'],
            ['name' => 'Sisow', 'provider' => 'Sisow', 'key' => '557d98977e7ec02dfa53de4b69b335be'],
            ['name' => 'Skrill', 'provider' => 'Skrill', 'is_offsite' => true, 'key' => '54dc60c869a7322d87efbec5c0c25805'],
            ['name' => 'BitPay', 'provider' => 'BitPay', 'is_offsite' => true, 'sort_order' => 7, 'key' => 'e4a02f0a4b235eb5e9e294730703bb74'],
            ['name' => 'AGMS', 'provider' => 'Agms', 'key' => '1b3c6f3ccfea4f5e7eadeae188cccd7f'],
            ['name' => 'Barclays', 'provider' => 'BarclaysEpdq\Essential', 'key' => '7cba6ce5c125f9cb47ea8443ae671b68'],
            ['name' => 'Cardgate', 'provider' => 'Cardgate', 'key' => 'b98cfa5f750e16cee3524b7b7e78fbf6'],
            ['name' => 'Checkout.com', 'provider' => 'CheckoutCom', 'key' => '3758e7f7c6f4cecf0f4f348b9a00f456'],
            ['name' => 'Creditcall', 'provider' => 'Creditcall', 'key' => 'cbc7ef7c99d31ec05492fbcb37208263'],
            ['name' => 'Cybersource', 'provider' => 'Cybersource', 'key' => 'e186a98d3b079028a73390bdc11bdb82'],
            ['name' => 'ecoPayz', 'provider' => 'Ecopayz', 'key' => '761040aca40f685d1ab55e2084b30670'],
            ['name' => 'Fasapay', 'provider' => 'Fasapay', 'key' => '1b2cef0e8c800204a29f33953aaf3360'],
            ['name' => 'Komoju', 'provider' => 'Komoju', 'key' => '7ea2d40ecb1eb69ef8c3d03e5019028a'],
            ['name' => 'Paysafecard', 'provider' => 'Paysafecard', 'key' => '70ab90cd6c5c1ab13208b3cef51c0894'],
            ['name' => 'Paytrace', 'provider' => 'Paytrace_CreditCard', 'key' => 'bbd736b3254b0aabed6ad7fda1298c88'],
            ['name' => 'Secure Trading', 'provider' => 'SecureTrading', 'key' => '231cb401487b9f15babe04b1ac4f7a27'],
            ['name' => 'SecPay', 'provider' => 'SecPay', 'key' => 'bad8699d581d9fa040e59c0bb721a76c'],
            ['name' => 'WePay', 'provider' => 'WePay', 'is_offsite' => false, 'sort_order' => 3, 'key' => '8fdeed552015b3c7b44ed6c8ebd9e992'],
            ['name' => 'Braintree', 'provider' => 'Braintree', 'sort_order' => 3, 'key' => 'f7ec488676d310683fb51802d076d713'],
            ['name' => 'Custom', 'provider' => 'Custom1', 'is_offsite' => true, 'sort_order' => 20, 'key' => 'ff0847592555bb2fdb429984e3de4147'],
            ['name' => 'FirstData Payeezy', 'provider' => 'FirstData_Payeezy', 'key' => '30334a52fb698046572c627ca10412e8'],
            ['name' => 'GoCardless', 'provider' => 'GoCardlessV2\Redirect', 'sort_order' => 9, 'is_offsite' => true, 'key' => 'b9886f9257f0c6ee7c302f1c74475f6c'],
            ['name' => 'PagSeguro', 'provider' => 'PagSeguro', 'key' => 'ef498756b54db63c143af0ec433da803'],
            ['name' => 'PAYMILL', 'provider' => 'Paymill', 'key' => 'ca52f618a39367a4c944098ebf977e1c'],
            ['name' => 'Custom', 'provider' => 'Custom2', 'is_offsite' => true, 'sort_order' => 21, 'key' => '54faab2ab6e3223dbe848b1686490baa'],
            ['name' => 'Custom', 'provider' => 'Custom3', 'is_offsite' => true, 'sort_order' => 22, 'key' => '8149a02d9e691a78da2664d0ce9ce1a9'],
        ];

        foreach ($gateways as $gateway) {
            $record = Gateway::whereName($gateway['name'])
                        ->whereProvider($gateway['provider'])
                        ->first();
            if ($record) {
                $record->fill($gateway);
                $record->save();
            } else {
                Gateway::create($gateway);
            }
        }

    }
}
