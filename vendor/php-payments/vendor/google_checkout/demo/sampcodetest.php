<?php
/*
 * Created on May 9, 2008
 *
 * To change the template for this generated file go to
 * Window - Preferences - PHPeclipse - PHP - Code Templates
 */

   // Point to the correct directory
  chdir("..");
  // Include all the required files
  require_once('library/googlecart.php');
  require_once('library/googleitem.php');
  require_once('library/googleshipping.php');
  require_once('library/googletax.php');
  UseCase2();

  function UseCase2() {
    // Create a new shopping cart object
    $merchant_id = "";  // Your Merchant ID
    $merchant_key = "";  // Your Merchant Key
    $server_type = "sandbox";
    $currency = "USD";
    $cart = new GoogleCart($merchant_id, $merchant_key, $server_type, $currency);

    // Add items to the cart
    $item_1 = new GoogleItem("Dry Food Pack AA1453",
        "A pack of highly nutritious dried food for emergency", 2, 24.99);
    $item_1->SetTaxTableSelector("food");

    $item_2 = new GoogleItem("MegaSound 2GB MP3 Player",
        "Portable MP3 player - stores 500 songs", 1, 175.49);
    $item_2->SetMerchantPrivateItemData(
                      new MerchantPrivateItemData(array("color" => "blue",
                                                        "weight" => "3.2")));
    $item_2->SetMerchantItemId("Item#012345");

    $cart->AddItem($item_1);
    $cart->AddItem($item_2);

    // Add shipping options
    $ship_1 = new GoogleFlatRateShipping("Ground", 15);
    $restriction_1 = new GoogleShippingFilters();
    $restriction_1->SetAllowedWorldArea(true);
    $ship_1->AddShippingRestrictions($restriction_1);

    $ship_2 = new GooglePickup("Pick Up", 5);

    $cart->AddShipping($ship_1);
    $cart->AddShipping($ship_2);

    // Add default tax rules
    $tax_rule_1 = new GoogleDefaultTaxRule(0.17);
    $tax_rule_1->AddPostalArea("GB", "SW*");
    $tax_rule_1->AddPostalArea("FR");
    $tax_rule_1->AddPostalArea("DE");

    $tax_rule_2 = new GoogleDefaultTaxRule(0.10);
    $tax_rule_2->SetWorldArea(true);

    $cart->AddDefaultTaxRules($tax_rule_1);
    $cart->AddDefaultTaxRules($tax_rule_2);

    // Add alternate tax table
    $tax_table = new GoogleAlternateTaxTable("food");

    $tax_rule_1 = new GoogleAlternateTaxRule(0.05);
    $tax_rule_1->AddPostalArea("GB");
    $tax_rule_1->AddPostalArea("FR");
    $tax_rule_1->AddPostalArea("DE");

    $tax_rule_2 = new GoogleAlternateTaxRule(0.03);
    $tax_rule_2->SetWorldArea(true);

    $tax_table->AddAlternateTaxRules($tax_rule_1);
    $tax_table->AddAlternateTaxRules($tax_rule_2);

    $cart->AddAlternateTaxTables($tax_table);

    // Add <merchant-private-data>
    $cart->SetMerchantPrivateData(
              new MerchantPrivateData(array( "animals" => array( "type" => "cat,dog" ))));

    // Specify <edit-cart-url>
    $cart->SetEditCartUrl("http://www.example.com/edit");

    // Specify "Return to xyz" link
    $cart->SetContinueShoppingUrl("http://www.example.com/continue");

    // Request buyer's phone number
    $cart->SetRequestBuyerPhone(true);

    // Define rounding policy
    $cart->AddRoundingPolicy("CEILING", "TOTAL");

    // Display XML data
    // echo "<pre>";
    // echo htmlentities($cart->GetXML());
    // echo "</pre>";

    // Display a medium size button
    echo $cart->GetXML();
  }
?>
