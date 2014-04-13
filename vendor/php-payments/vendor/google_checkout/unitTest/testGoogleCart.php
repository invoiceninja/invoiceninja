<?php

require_once('classes/phpunit.php');
require_once('../library/googlecart.php');
require_once('../library/googleitem.php');
require_once('../library/googleshipping.php');
require_once('../library/googletax.php');

class TestGoogleCart extends TestCase {          
  function TestGoogleCart($name) {
    $this->TestCase($name);
  }

  function setUp() {
    /* put any common setup here */
  }

  function tearDown() {
    /* put any common endup here */
  }

  function TestGoogleCartSimple(){
    $Gcart = new googlecart('123', 'abc', "sandbox", 'GBP');
    $Gitem = new GoogleItem('Name',
                            'description',
                            '3', 
                            '12.34');
    $Gitem->SetMerchantPrivateItemData(
        new MerchantPrivateItemData('PrivateItemData'));
    $Gitem->SetMerchantItemId('123-4321');
//    $Gitem->SetTaxTableSelector('TaxableGood');
    $Gcart->AddItem($Gitem);
    $this->assertEquals(trim($Gcart->getXML()), 
      trim('<?xml version="1.0" encoding="utf-8"?>
<checkout-shopping-cart xmlns="http://checkout.google.com/schema/2">
  <shopping-cart>
    <items>
      <item>
        <item-name>Name</item-name>
        <item-description>description</item-description>
        <unit-price currency="GBP">12.34</unit-price>
        <quantity>3</quantity>
        <merchant-private-item-data>PrivateItemData</merchant-private-item-data>
        <merchant-item-id>123-4321</merchant-item-id>
      </item>
    </items>
  </shopping-cart>
  <checkout-flow-support>
    <merchant-checkout-flow-support>
    </merchant-checkout-flow-support>
  </checkout-flow-support>
</checkout-shopping-cart>'));

  }
  
}

if(!isset($suite)) {
  $suite = new TestSuite();
}

$suite->addTest(new TestGoogleCart("TestGoogleCartSimple"));

?>