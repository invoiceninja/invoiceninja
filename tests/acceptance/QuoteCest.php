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
        $categoryName = $this->faker->text(11);
        $snippetName = $this->faker->text(12);
        $templateName = $this->faker->text(13);

        $I->wantTo('create a quote and proposal');

        // create proposal category
        $I->amOnPage('/proposals/categories/create');
        $I->fillField(['name' => 'name'], $categoryName);
        $I->click('Save');
        $I->see('Successfully created category');
        $I->amOnPage('/proposals/categories');
        $I->see($categoryName);

        // create proposal snippet
        $I->amOnPage('/proposals/snippets/create');
        $I->fillField(['name' => 'name'], $snippetName);
        $I->selectDropdown($I, $categoryName, '.category-select .dropdown-toggle');
        $I->click('Save');
        $I->see('Successfully created snippet');
        $I->amOnPage('/proposals/snippets');
        $I->see($snippetName);
        $I->see($categoryName);

        // create proposal template
        $I->amOnPage('/proposals/templates/create');
        $I->fillField(['name' => 'name'], $templateName);
        $I->click('Save');
        $I->see('Successfully created template');
        $I->amOnPage('/proposals/templates');
        $I->see($templateName);

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
        $I->see('Successfully created product');

        // create quote
        $I->amOnPage('/quotes/create');
        $I->selectDropdown($I, $clientEmail, '.client_select .dropdown-toggle');
        $I->fillField('table.invoice-table tbody tr:nth-child(1) td:nth-child(2) input.tt-input', $productKey);
        $I->click('table.invoice-table tbody tr:nth-child(1) .tt-selectable');
        $I->click('Mark Sent');
        $I->wait(2);
        
        $I->see($clientEmail);
        $I->click('More Actions');
        $I->click('New Proposal');
        $I->see('Create');

        $I->selectDropdown($I, $templateName, '.template-select .dropdown-toggle');
        $I->click('Save');
        $I->click('Download');

        $clientId = $I->grabFromDatabase('contacts', 'client_id', ['email' => $clientEmail]);
        $invoiceId = $I->grabFromDatabase('invoices', 'id', ['client_id' => $clientId]);
        $proposalId = $I->grabFromDatabase('proposals', 'id', ['invoice_id' => $invoiceId]);
        $invitationKey = $I->grabFromDatabase('proposal_invitations', 'invitation_key', ['proposal_id' => $proposalId]);

        $clientSession = $I->haveFriend('client');
        $clientSession->does(function(AcceptanceTester $I) use ($invitationKey) {
            $I->amOnPage('/proposal/' . $invitationKey);
            $I->click('Approve');
            $I->see('Successfully approved');
        });

    }
}
