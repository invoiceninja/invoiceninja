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
        $I->click(sprintf('ul.typeahead li[data-value="%s"]', $option));
    }

    function selectDropdownRow(\AcceptanceTester $I, $option, $dropdownSelector)
    {
        $I->click("$dropdownSelector span.dropdown-toggle");
        $I->click("$dropdownSelector ul li:nth-child($option)");
    }
}
