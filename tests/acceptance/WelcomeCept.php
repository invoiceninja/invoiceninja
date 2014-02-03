<?php

$I = new WebGuy($scenario);
$I->wantTo('ensure that frontpage works');
$I->amOnPage('/rocksteady'); 
$I->click('#startButton');
$I->seeInDatabase('users', ['id' => 1]);

$I->click('#createClientLink');
$I->fillField('input#email', 'test@aol.com');
$I->click('#clientDoneButton');
$I->click('#saveButton');
$I->seeInDatabase('contacts', ['email' => 'test@aol.com']);
$I->seeInField('input#email', 'test@aol.com');