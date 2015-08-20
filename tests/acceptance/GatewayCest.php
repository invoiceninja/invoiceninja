<?php
use \AcceptanceTester;
use Faker\Factory;

class GatewayCest
{
    private $faker;

    public function _before(AcceptanceTester $I)
    {
        $I->checkIfLogin($I);

        $this->faker = Factory::create();
    }


    // tests
    public function create(AcceptanceTester $I)
    {
        $I->wantTo("create a gateway");
        $I->amOnPage('/gateways/create');
        $I->seeCurrentUrlEquals('/gateways/create');

        $I->fillField(['name' => '23_apiKey'], $this->faker->swiftBicNumber);
        $I->click('Save');
        
        $I->see('Successfully created gateway');
        $I->seeInDatabase('account_gateways', array('gateway_id' => 23));
    }
}
