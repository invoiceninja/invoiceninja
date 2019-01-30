<?php

use \FunctionalTester;
use Faker\Factory;

class SettingsCest
{
    private $faker;

    public function _before(FunctionalTester $I)
    {
        $I->checkIfLogin($I);

        $this->faker = Factory::create();
    }

    /*
    public function companyDetails(FunctionalTester $I)
    {
        $I->wantTo('update the company details');
        $I->amOnPage('/settings/company_details');

        $name = $this->faker->company;

        $I->fillField(['name' => 'name'], $name);
        $I->fillField(['name' => 'work_email'], $this->faker->safeEmail);
        $I->fillField(['name' => 'work_phone'], $this->faker->phoneNumber);
        $I->fillField(['name' => 'address1'], $this->faker->buildingNumber . ' ' . $this->faker->streetName);
        $I->fillField(['name' => 'address2'], $this->faker->secondaryAddress);
        $I->fillField(['name' => 'city'], $this->faker->city);
        $I->fillField(['name' => 'state'], $this->faker->state);
        $I->fillField(['name' => 'postal_code'], $this->faker->postcode);
        $I->click('Save');

        $I->seeResponseCodeIs(200);
        $I->seeRecord('accounts', array('name' => $name));
    }
    */

    public function userDetails(FunctionalTester $I)
    {
        $I->wantTo('update the user details');
        $I->amOnPage('/settings/user_details');

        $firstName = $this->faker->firstName;

        $I->fillField(['name' => 'first_name'], $firstName);
        $I->fillField(['name' => 'last_name'], $this->faker->lastName);
        $I->fillField(['name' => 'phone'], $this->faker->phoneNumber);
        $I->click('Save');

        $I->seeResponseCodeIs(200);
        $I->seeRecord('users', array('first_name' => $firstName));
    }

    /*
    public function localization(FunctionalTester $I)
    {
        $I->wantTo('update the localization');
        $I->amOnPage('/settings/localization');

        $name = $this->faker->company;

        $I->fillField(['name' => 'name'], $name);
        $I->click('Save');

        $I->seeResponseCodeIs(200);
        $I->seeRecord('accounts', array('name' => $name));
    }
    */


    public function productSettings(FunctionalTester $I)
    {
        $I->wantTo('update the product settings');
        $I->amOnPage('/settings/products');

        $I->click('Save');

        $I->seeResponseCodeIs(200);
    }

    public function createProduct(FunctionalTester $I)
    {
        $I->wantTo('create a product');
        $I->amOnPage('/products/create');

        $productKey = $this->faker->text(10);

        $I->fillField(['name' => 'product_key'], $productKey);
        $I->fillField(['name' => 'notes'], $this->faker->text(80));
        $I->fillField(['name' => 'cost'], $this->faker->numberBetween(1, 20));
        $I->click('Save');

        $I->seeResponseCodeIs(200);
        $I->seeRecord('products', array('product_key' => $productKey));
    }

    public function updateProduct(FunctionalTester $I)
    {
        return;

        $I->wantTo('update a product');
        $I->amOnPage('/products/1/edit');

        $productKey = $this->faker->text(10);

        $I->fillField(['name' => 'product_key'], $productKey);
        $I->click('Save');

        $I->seeResponseCodeIs(200);
        $I->seeRecord('products', array('product_key' => $productKey));
    }

    /*
    public function updateNotifications(FunctionalTester $I)
    {
        $I->wantTo('update notification settings');
        $I->amOnPage('/settings/notifications');

        $terms = $this->faker->text(80);

        $I->fillField(['name' => 'invoice_terms'], $terms);
        $I->fillField(['name' => 'invoice_footer'], $this->faker->text(60));
        $I->click('Save');

        $I->seeResponseCodeIs(200);
        $I->seeRecord('accounts', array('invoice_terms' => $terms));
    }
    */

    public function updateInvoiceSettings(FunctionalTester $I)
    {
        $I->wantTo('update invoice settings');
        $I->amOnPage('/settings/invoice_settings');

        $label = $this->faker->text(10);

        $I->fillField(['name' => 'custom_client_label1'], $label);
        $I->click('Save');

        $I->seeResponseCodeIs(200);
        $I->seeRecord('accounts', array('custom_client_label1' => $label));

        //$I->amOnPage('/clients/create');
        //$I->see($label);
    }

    public function updateEmailTemplates(FunctionalTester $I)
    {
        $I->wantTo('update email templates');
        $I->amOnPage('/settings/templates_and_reminders');

        $string = $this->faker->text(100);

        $I->fillField(['name' => 'email_template_invoice'], $string);
        $I->click('Save');

        $I->seeResponseCodeIs(200);
        $I->seeRecord('accounts', array('email_template_invoice' => $string));
    }

    public function runReport(FunctionalTester $I)
    {
        $I->wantTo('run the report');
        $I->amOnPage('/settings/reports');

        $I->click('Run');
        $I->seeResponseCodeIs(200);
    }

    public function createUser(FunctionalTester $I)
    {
        $I->wantTo('create a user');
        $I->amOnPage('/users/create');

        $email = $this->faker->safeEmail;

        $I->fillField(['name' => 'first_name'], $this->faker->firstName);
        $I->fillField(['name' => 'last_name'], $this->faker->lastName);
        $I->fillField(['name' => 'email'], $email);
        $I->click('Send invitation');

        $I->seeResponseCodeIs(200);
        $I->seeRecord('users', array('email' => $email));
    }

    public function createToken(FunctionalTester $I)
    {
        $I->wantTo('create a token');
        $I->amOnPage('/tokens/create');

        $name = $this->faker->firstName;

        $I->fillField(['name' => 'name'], $name);
        $I->click('Save');

        $I->seeResponseCodeIs(200);
        $I->seeRecord('account_tokens', array('name' => $name));
    }

    /*
    public function onlinePayments(FunctionalTester $I)
    {
        $gateway = $I->grabRecord('account_gateways', array('gateway_id' => 23));

        if (!$gateway) {
            $apiKey = $this->faker->swiftBicNumber;

            $I->wantTo('create a gateway');
            $I->amOnPage('/gateways/create');
            $I->seeCurrentUrlEquals('/gateways/create');

            $I->fillField(['name' => '23_apiKey'], $apiKey);
            $I->click('Save');

            $I->seeResponseCodeIs(200);
            $I->see('Successfully created gateway');
            $I->seeRecord('account_gateways', array('gateway_id' => 23));
        } else {
            $config = $gateway->getConfig();
            $apiKey = $config->apiKey;
        }

        // $I->amOnPage('/gateways/1/edit');
        // $I->click('Save');

        // $I->seeResponseCodeIs(200);
        // $I->see('Successfully updated gateway');
        // $I->seeRecord('account_gateways', array('config' => '{"apiKey":"ASHHOWAH"}'));
    }
    */


}
