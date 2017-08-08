<?php

use Faker\Factory;

class QuoteCest
{
    /**
     * @var \Faker\Generator
     */
    private $faker;

    public function _before(AcceptanceTester $I)
    {
        $I->checkIfLogin($I);

        $this->faker = Factory::create();
    }

    public function createQuote(AcceptanceTester $I)
    {
        $clientEmail = $this->faker->safeEmail;
        $productKey = $this->faker->text(10);

        $I->wantTo('create a quote');

        // create client
        $I->amOnPage('/clients/create');
        $I->fillField(['name' => 'contacts[0][email]'], $clientEmail);
        $I->click('Save');
        $I->see($clientEmail);

        // create product
        $I->amOnPage('/products/create');
        $I->fillField(['name' => 'product_key'], $productKey);
        $I->fillField(['name' => 'notes'], $this->faker->text(80));
        $I->fillField(['name' => 'cost'], $this->faker->numberBetween(1, 20));
        $I->click('Save');
        $I->wait(1);
        //$I->see($productKey);

        // create quote
        $I->amOnPage('/quotes/create');
        $I->selectDropdown($I, $clientEmail, '.client_select .dropdown-toggle');
        $I->fillField('table.invoice-table tbody tr:nth-child(1) #product_key', $productKey);
        $I->click('table.invoice-table tbody tr:nth-child(1) .tt-selectable');
        $I->click('Mark Sent');
        $I->see($clientEmail);

        // enter payment
        $clientId = $I->grabFromDatabase('contacts', 'client_id', ['email' => $clientEmail]);
        $invoiceId = $I->grabFromDatabase('invoices', 'id', ['client_id' => $clientId]);
        $invitationKey = $I->grabFromDatabase('invitations', 'invitation_key', ['invoice_id' => $invoiceId]);

        $clientSession = $I->haveFriend('client');
        $clientSession->does(function(AcceptanceTester $I) use ($invitationKey) {
            $I->amOnPage('/view/' . $invitationKey);
            $I->click('Approve');
            $I->see('The quote has been approved');
        });

    }
}
