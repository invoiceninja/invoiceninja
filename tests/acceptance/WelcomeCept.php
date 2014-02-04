<?php

$I = new WebGuy($scenario);
$I->wantTo('click invoice now');
$I->amOnPage('/rocksteady'); 
$I->click('#startButton');
$I->seeInDatabase('users', ['id' => 1]);

$I->wantTo('create a client');
$I->click('#createClientLink');
$I->fillField('input#email', 'test@aol.com');
$I->click('#clientDoneButton');
$I->click('#saveButton');
$I->seeInDatabase('contacts', ['email' => 'test@aol.com']);
$I->seeInField('input#email', 'test@aol.com');