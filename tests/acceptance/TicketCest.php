<?php

use Faker\Factory;

class TicketCest
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

    public function createTicket(AcceptanceTester $I)
    {
        $subject = $this->faker->text(20);
        $description = $this->faker->text(100);
        $clientName = $this->faker->name;
        $clientEmail = $this->faker->safeEmail;

        $I->wantTo('create a new client ticket');

        // create client
        $I->amOnPage('/clients/create');
        $I->fillField(['name' => 'name'], $clientName);
        $I->fillField(['name' => 'contacts[0][email]'], $clientEmail);
        $I->click('Save');
        $I->see($clientEmail);
        $clientId = $I->grabFromDatabase('clients', 'id', ['name' => $clientName]);

        $I->amOnPage('/tickets/create');
        $I->seeCurrentUrlEquals('/tickets/create');

        $I->fillField(['name' => 'subject'], $subject);
        $I->fillField(['id' => 'ql-editor-1'], $description);
        $I->selectDropdown($I, $clientName, '.client-select .dropdown-toggle');
        $I->click('Create ticket');
        $I->wait(2);
        $I->see($clientName);

    }


}