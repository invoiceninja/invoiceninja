<?php

use Faker\Factory;
use Codeception\Util\Fixtures;

class ClientCest
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

    public function createClient(AcceptanceTester $I)
    {
        $I->wantTo('Create a client');

        //Organization
        $I->amOnPage('/clients/create');

        $I->fillField(['name' => 'name'], $name = $this->faker->name);
        $I->fillField(['name' => 'id_number'], rand(0, 10000));
        $I->fillField(['name' => 'vat_number'], rand(0, 10000));
        $I->fillField(['name' => 'website'], $this->faker->url);
        $I->fillField(['name' => 'work_phone'], $this->faker->phoneNumber);

        //Contacts
        $I->fillField(['name' => 'contacts[0][first_name]'], $this->faker->firstName);
        $I->fillField(['name' => 'contacts[0][last_name]'], $this->faker->lastName);
        $I->fillField(['name' => 'contacts[0][email]'], $this->faker->companyEmail);
        $I->fillField(['name' => 'contacts[0][phone]'], $this->faker->phoneNumber);

        //Additional Contact
        //$I->click('Add contact +');
        //$I->fillField('.form-group:nth-child(6) #first_name', $this->faker->firstName);
        //$I->fillField('.form-group:nth-child(7) #last_name', $this->faker->lastName);
        //$I->fillField('.form-group:nth-child(8) #email1', $this->faker->companyEmail);
        //$I->fillField('.form-group:nth-child(9) #phone', $this->faker->phoneNumber);

        //Address
        $I->see('Street');
        $I->fillField(['name' => 'address1'], $this->faker->streetAddress);
        $I->fillField(['name' => 'address2'], $this->faker->streetAddress);
        $I->fillField(['name' => 'city'], $this->faker->city);
        $I->fillField(['name' => 'state'], $this->faker->state);
        $I->fillField(['name' => 'postal_code'], $this->faker->postcode);

        //$I->executeJS('$(\'input[name="country_id"]\').val('. Helper::getRandom('Country').')');

        //Additional Info
        //$I->selectOption(['name' => 'currency_id'], Helper::getRandom('Currency'));;
        //$I->selectOption(['name' => 'payment_terms'], Helper::getRandom('PaymentTerm', 'num_days'));
        //$I->selectOption(['name' => 'size_id'], Helper::getRandom('Size'));
        //$I->selectOption(['name' => 'industry_id'], Helper::getRandom('Industry'));
        //$I->fillField(['name' => 'private_notes'], 'Private Notes');

        $I->click('Save');
        $I->see($name);
    }

    public function editClient(AcceptanceTester $I)
    {
        $I->wantTo('Edit a client');

        //$id = Helper::getRandom('Client', 'public_id');
        //$url = sprintf('/clients/%d/edit', $id);
        $url = '/clients/1/edit';

        $I->amOnPage($url);
        $I->seeCurrentUrlEquals($url);

        //update fields
        $name = $this->faker->firstName;
        $I->fillField(['name' => 'name'], $name);
        $I->click('Save');
        
        $I->see($name);
    }
    
    /*
    public function deleteClient(AcceptanceTester $I)
    {
        $I->wantTo('delete a client');

        $I->amOnPage('/clients');
        $I->seeCurrentUrlEquals('/clients');

        $I->executeJS(sprintf('deleteEntity(%s)', $id = Helper::getRandom('Client', 'public_id')));
        $I->acceptPopup();

        //check if client was removed from database
        $I->wait(5);
        $I->seeInDatabase('clients', ['public_id' => $id, 'is_deleted' => true]);
    }
    */
}
