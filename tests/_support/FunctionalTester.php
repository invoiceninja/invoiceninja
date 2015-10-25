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
class FunctionalTester extends \Codeception\Actor
{
    use _generated\FunctionalTesterActions;

   /**
    * Define custom actions here
    */
    function checkIfLogin(\FunctionalTester $I)
    {
        //if ($I->loadSessionSnapshot('login')) return;
        $I->amOnPage('/login');
        $I->fillField(['name' => 'email'], Fixtures::get('username'));
        $I->fillField(['name' => 'password'], Fixtures::get('password'));
        $I->click('#loginButton');

        //$I->saveSessionSnapshot('login');
    }   
}
