<?php

/**
 * Adds data to invitation for a user/client so that the 
 * public/payment/{aaabbb} page is accessable (aaabbb is the invitation_key)
**/
class SecurePaymentFormSeeder extends Seeder
{
	public function run()
	{
		Eloquent::unguard();
        
        //Delete table content
        DB::table('invitations')->delete();
        DB::table('invoices')->delete();
        DB::table('contacts')->delete();
        DB::table('clients')->delete();
        DB::table('account_gateways')->delete();
        //To reset the auto increment
        $statement = "
                        ALTER TABLE invitations AUTO_INCREMENT = 1;
                        ALTER TABLE invoices AUTO_INCREMENT = 1;
                        ALTER TABLE contacts AUTO_INCREMENT = 1;
                        ALTER TABLE clients AUTO_INCREMENT = 1;
                        ALTER TABLE account_gateways AUTO_INCREMENT = 1;
                    ";

        DB::unprepared($statement);
        
        //$firstName = 'Oscar';
//        $lastName = 'Thompson';
//        $firstName_2 = 'Philip';
//        $lastName_2 = 'Jonsson';
//        
//        $user = AccountGateway::create(array(
//            'account_id' => 1,
//            'user_id' => 1,
//            'gateway_id' => 4,
//            'config' => '{"bla":"vla","bli":"cla"}',
//            'public_id' => 1,
//            'accepted_credit_cards' => 8
//        ));
//        
//        $user2 = AccountGateway::create(array(
//            'account_id' => 2,
//            'user_id' => 2,
//            'gateway_id' => 5,
//            'config' => '{"bla":"vla","bli":"cla"}',
//            'public_id' => 2,
//            'accepted_credit_cards' => 7
//        ));
//        
//        $client = Client::create(array(
//            'user_id' => 1,
//            'account_id' => 1,
//            'currency_id' => 1,
//            'name' => $firstName.' '.$lastName,
//            'address1' => '2119 Howe Course',
//            'address2' => '2118 Howe Course',
//            'city' => 'West Chazport',
//            'state' => 'Utah',
//            'postal_code' => '31572',
//            'country_id' => 752,
//            'work_phone' => '012-345678',
//            'private_notes' => 'bla bla bla bla bla bla bla',
//            'balance' => 10.4,
//            'paid_to_date' => 10.2,
//            'website' => 'awebsite.com',
//            'industry_id' => 8,
//            'is_deleted' => 0,
//            'payment_terms' => 2,
//            'public_id' => 1,
//            'custom_value1' => $firstName,
//            'custom_value2' => $firstName
//        ));
//        
//        $client2 = Client::create(array(
//            'user_id' => 2,
//            'account_id' => 2,
//            'currency_id' => 1,
//            'name' => $firstName_2.' '.$lastName_2,
//            'address1' => '1118 Muma Road',
//            'address2' => '1118 Muma Road',
//            'city' => 'New Orleans',
//            'state' => 'Arizona',
//            'postal_code' => '31572',
//            'country_id' => 752,
//            'work_phone' => '012-345678',
//            'private_notes' => 'bla bla bla bla bla bla bla',
//            'balance' => 10.4,
//            'paid_to_date' => 10.2,
//            'website' => 'bodosite.com',
//            'industry_id' => 8,
//            'is_deleted' => 0,
//            'payment_terms' => 2,
//            'public_id' => 1,
//            'custom_value1' => $firstName_2,
//            'custom_value2' => $firstName_2
//        ));
//        
//        $contact = Contact::create(array(
//            'account_id' => 1,
//            'user_id' => 1,
//            'client_id' => 1,
//            'is_primary' => 0,
//            'send_invoice' => 0,
//            'first_name' => $firstName,
//            'last_name' => $lastName,
//            'email' => 'an@email.com',
//            'phone' => '012-345678',
//            'public_id' => 1
//        ));
//        
//        $contact2 = Contact::create(array(
//            'account_id' => 2,
//            'user_id' => 2,
//            'client_id' => 2,
//            'is_primary' => 0,
//            'send_invoice' => 0,
//            'first_name' => $firstName_2,
//            'last_name' => $lastName_2,
//            'email' => 'the@email.com',
//            'phone' => '012-345678',
//            'public_id' => 2
//        ));
//        
//        $invoice = Invoice::create(array(
//            'client_id' => 1,
//            'user_id' => 1,
//            'account_id' => 1,
//            'invoice_number' => 1,
//            'discount' => 0.4,
//            'po_number' => $firstName,
//            'terms' => 'bla bla bla bla bla bla bla',
//            'public_notes' => 'bla bla bla bla bla bla bla',
//            'is_deleted' => 0,
//            'is_recurring' => 0,
//            'frequency_id' => 1,
//            'tax_name' => 'moms',
//            'tax_rate' => 33.0,
//            'amount' => 10.0,
//            'balance' => 8.0,
//            'public_id' => 1,
//            'is_quote' =>  0
//        ));
//        
//        $invoice2 = Invoice::create(array(
//            'client_id' => 2,
//            'user_id' => 2,
//            'account_id' => 2,
//            'invoice_number' => 2,
//            'discount' => 0.4,
//            'po_number' => $firstName_2,
//            'terms' => 'bla bla bla bla bla bla bla',
//            'public_notes' => 'bla bla bla bla bla bla bla',
//            'is_deleted' => 0,
//            'is_recurring' => 0,
//            'frequency_id' => 1,
//            'tax_name' => 'moms',
//            'tax_rate' => 33.0,
//            'amount' => 10.0,
//            'balance' => 8.0,
//            'public_id' => 2,
//            'is_quote' =>  0
//        ));
//        
//        $invitation = Invitation::create(array(
//            'account_id' => 1,
//            'user_id' => 1,
//            'contact_id' => 1,
//            'invoice_id' => 1,
//            'invitation_key' => 'aaabbb',
//            'transaction_reference' => 'bla bla bla bla bla bla bla',
//            'public_id' => 1
//        ));
//        
//        $invitation2 = Invitation::create(array(
//            'account_id' => 2,
//            'user_id' => 2,
//            'contact_id' => 2,
//            'invoice_id' => 2,
//            'invitation_key' => 'cccddd',
//            'transaction_reference' => 'bla bla bla bla bla bla bla',
//            'public_id' => 2
//        ));
	}
}