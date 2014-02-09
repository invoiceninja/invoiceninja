<?php
/**
* Testing /clients/create form
* For now do not tests all fields in Form.
**/

$I = new WebGuy($scenario);
$I->wantTo('Test Form:New Client. /clients/create');





$I->amOnPage('/rocksteady'); 
$I->click('#startButton');


$I->amOnPage('/clients/create'); 


$I->click('Add contact');

$I->fillField('input#email0', 'SomeTestEmalThatWillBeDeleted@mail.com');
$I->fillField('input#email1', 'some.test.email@gmail.com');


$I->click('Remove contact');
$I->click('Add contact');
$I->fillField('input#email1', 'AZXC123Q.test2009test@yandex2.com');


$I->seeInField('input#email0', 'some.test.email@gmail.com');
$I->seeInField('input#email1', 'AZXC123Q.test2009test@yandex2.com');


//-----------Fields done-----
//add more fields
$I->fillField('input#name', 'Some User');

$I->fillField('input#website', 'http://google.com');
$I->fillField('input#work_phone', '+308123456789');

$I->fillField('input#address1', 'Test Address 1');
$I->fillField('input#address2', 'Test Address 2 APPTMT SUITE');
$I->fillField('input#city', 'Zaporozhe');
$I->fillField('input#state', 'Test Province');
$I->fillField('input#postal_code', 'postcode 123455677');



$option='Net 7';
$I->selectOption("#payment_terms", $option);



$option='Euro';
$I->selectOption("#currency_id", $option);



$option='500+';
$I->selectOption("#size_id", $option);


$option='Aerospace';
$I->selectOption("#industry_id", $option);











//----private notes
$I->fillField('#private_notes', 'Test Note Note Notes');





//-----------------------Form is Finished--


$I->click('Save');


$I->seeInCurrentUrl('/clients/');

$I->dontSeeInCurrentUrl('/users/');
$I->dontSeeInCurrentUrl('/user/');



$I->see('Details');
$I->see('Contacts');
$I->see('some.test.email@gmail.com');


$I->seeInDatabase('contacts', ['email' => 'some.test.email@gmail.com']);
$I->seeInDatabase('contacts', ['email' => 'AZXC123Q.test2009test@yandex2.com']);
$I->seeInDatabase('contacts', ['email' => 'azxc123q.test2009test@yandex2.com']);




$I->seeInDatabase('clients', array(
	'currency_id'=>3,
	'name' => 'Some User',
	'address1' => 'Test Address 1',
    'address2' => 'Test Address 2 APPTMT SUITE',
    'city' => 'Zaporozhe',
    'state' => 'Test Province',
    
'postal_code' => 'postcode 123455677',
'work_phone' => '+308123456789',
'private_notes' => 'Test Note Note Notes',
'payment_terms' => 7,
'industry_id' => 3,
'size_id' => 6



	));


//$I->seeInDatabase('clients', ['email' => 'azxc123q.test2009test@yandex2.com']);

