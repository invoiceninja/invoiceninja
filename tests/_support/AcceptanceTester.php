<?php

use Codeception\Util\Fixtures;

/**
 * Inherited Methods
 * @method void wantToTest($text)
 * @method void wantTo($text)
 * @method void execute($callable)
 * @method void expectTo($prediction)
 * @method void expect($prediction)
 * @method void amGoingTo($argumentation)
 * @method void am($role)
 * @method void lookForwardTo($achieveValue)
 * @method void comment($description)
 * @method \Codeception\Lib\Friend haveFriend($name, $actorClass = null)
 *
 * @SuppressWarnings(PHPMD)
*/
class AcceptanceTester extends \Codeception\Actor
{
    use _generated\AcceptanceTesterActions;

   /**
    * Define custom actions here
    */
    function checkIfLogin(\AcceptanceTester $I)
    {
        //if ($I->loadSessionSnapshot('login')) return;

        $I->amOnPage('/login?lang=en');
        $I->see('Login');
        $I->fillField(['name' => 'email'], Fixtures::get('username'));
        $I->fillField(['name' => 'password'], Fixtures::get('password'));
        $I->click('Login');

        //$I->saveSessionSnapshot('login');
    }

    function selectDataPicker(\AcceptanceTester $I, $element, $date = 'now')
    {
        $date = strtotime($date) * 1000;
        $I->executeJS(sprintf('$(\'%s\').datepicker(\'update\', new Date(%s))', $element, $date));
    }

    function selectDropdown(\AcceptanceTester $I, $option, $dropdownSelector)
    {
        $I->click($dropdownSelector);
        $I->click(sprintf('ul.typeahead li[data-value*="%s"]', $option));
    }

    function selectDropdownCreate(\AcceptanceTester $I, $entityType, $value, $entityTypeShort = false)
    {
        $entityTypeShort = $entityTypeShort ?: $entityType;
        $I->fillField("#{$entityType}_name", $value);
        $I->click(sprintf('ul.typeahead li[data-value*="%s"]', "Create {$entityTypeShort}: \$name"));
    }

    function selectDropdownRow(\AcceptanceTester $I, $option, $dropdownSelector)
    {
        $I->click("$dropdownSelector span.dropdown-toggle");
        $I->click("$dropdownSelector ul li:nth-child($option)");
    }

    function createGateway(\AcceptanceTester $I)
    {
        if ( ! $I->grabFromDatabase('account_gateways', 'id', ['id' => 1])) {
            $I->wantTo('create a gateway');
            $I->amOnPage('/gateways/create?other_providers=true');
            $I->fillField(['name' =>'23_apiKey'], env('stripe_secret_key') ?: Fixtures::get('stripe_secret_key'));
            $I->fillField(['name' =>'publishable_key'], '');
            $I->click('Save');
            $I->see('Successfully created gateway');
        }
    }

    function createClient(\AcceptanceTester $I, $email)
    {
        $I->amOnPage('/clients/create');
        $I->fillField(['name' => 'contacts[0][email]'], $email);
        $I->click('Save');
        $I->see($email);
    }

    function createProduct(\AcceptanceTester $I, $productKey, $cost, $taxName = '', $taxRate = '')
    {
        $I->amOnPage('/products/create');
        $I->fillField(['name' => 'product_key'], $productKey);
        $I->fillField(['name' => 'cost'], $cost);

        if ($taxName && $taxRate) {
            $taxOption = $taxName . ': ' . number_format($taxRate, 3) . '%';
            $I->selectOption('#tax_select1', $taxOption);
        }

        $I->click('Save');
        $I->wait(1);
        //$I->see($productKey);
    }

    function createTaxRate(\AcceptanceTester $I, $name, $rate)
    {
        $I->amOnPage('/tax_rates/create');
        $I->fillField(['name' => 'name'], $name);
        $I->fillField(['name' => 'rate'], $rate);
        $I->click('Save');
        $I->see($name);
        $I->see($rate);
    }

    function fillInvoice(\AcceptanceTester $I, $clientEmail, $productKey)
    {
        $I->amOnPage('/invoices/create');
        $invoiceNumber = $I->grabValueFrom('#invoice_number');

        $I->selectDropdown($I, $clientEmail, '.client_select .dropdown-toggle');
        $I->fillField('table.invoice-table tbody tr:nth-child(1) #product_key', $productKey);
        $I->click('table.invoice-table tbody tr:nth-child(1) .tt-selectable');

        return $invoiceNumber;
    }

    function createOnlinePayment(\AcceptanceTester $I, $invitationKey)
    {
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
    }

    function checkSettingOption(\AcceptanceTester $I, $url, $option)
    {
        $I->amOnPage('/settings/' . $url);
        $I->checkOption('#' . $option);
        $I->click('Save');
    }

    function uncheckSettingOption(\AcceptanceTester $I, $url, $option)
    {
        $I->amOnPage('/settings/' . $url);
        $I->uncheckOption('#' . $option);
        $I->click('Save');
    }

}
