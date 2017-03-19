/<?php

use Codeception\Util\Fixtures;
use Faker\Factory;

class OnlinePaymentCest
{
    private $faker;

    public function _before(AcceptanceTester $I)
    {
        $I->checkIfLogin($I);

        $this->faker = Factory::create();
    }

    public function onlinePayment(AcceptanceTester $I)
    {
        $I->wantTo('test an online payment');

        $clientEmail = $this->faker->safeEmail;
        $productKey = $this->faker->text(10);

        $I->createGateway($I);
        $I->createClient($I, $clientEmail);

        // create product
        $I->amOnPage('/products/create');
        $I->fillField(['name' => 'product_key'], $productKey);
        $I->fillField(['name' => 'notes'], $this->faker->text(80));
        $I->fillField(['name' => 'cost'], $this->faker->numberBetween(1, 20));
        $I->click('Save');
        $I->wait(1);
        //$I->see($productKey);

        // create invoice
        $I->amOnPage('/invoices/create');
        $invoiceNumber = $I->grabAttributeFrom('#invoice_number', 'value');
        $I->selectDropdown($I, $clientEmail, '.client_select .dropdown-toggle');
        $I->fillField('table.invoice-table tbody tr:nth-child(1) #product_key', $productKey);
        $I->click('table.invoice-table tbody tr:nth-child(1) .tt-selectable');
        $I->click('Mark Sent');
        $I->see($clientEmail);

        // enter payment
        $clientId = $I->grabFromDatabase('contacts', 'client_id', ['email' => $clientEmail]);
        $invoiceId = $I->grabFromDatabase('invoices', 'id', ['client_id' => $clientId, 'invoice_number' => $invoiceNumber]);
        $invitationKey = $I->grabFromDatabase('invitations', 'invitation_key', ['invoice_id' => $invoiceId]);

        $I->createOnlinePayment($I, $invitationKey);

        /*
        $invoiceId = $I->grabFromDatabase('invoices', 'id', ['client_id' => $clientId]);
        $invitationKey = $I->grabFromDatabase('invitations', 'invitation_key', ['invoice_id' => $invoiceId]);

        $clientSession = $I->haveFriend('client');
        $clientSession->does(function(AcceptanceTester $I) use ($invitationKey) {
            $I->amOnPage('/view/' . $invitationKey);
            $I->click('Pay Now');
            $I->click('Credit Card');
            $I->fillField('#card_number', '4242424242424242');
            $I->fillField('#cvv', '100');
            $I->selectOption('#expiration_month', 12);
            $I->selectOption('#expiration_year', date('Y'));
            $I->click('.btn-success');
            $I->wait(3);
            $I->see('Successfully applied payment');
        });
        $I->wait(1);
        */

        // create recurring invoice and auto-bill
        $I->amOnPage('/recurring_invoices/create');
        $I->selectDropdown($I, 'Test Test', '.client_select .dropdown-toggle');
        $I->fillField('table.invoice-table tbody tr:nth-child(1) #product_key', $productKey);
        $I->click('table.invoice-table tbody tr:nth-child(1) .tt-selectable');
        $I->selectOption('#auto_bill', 3);
        $I->executeJS('onConfirmEmailClick()');
        $I->wait(4);
        $I->see("$0.00");
   }
}
