<?php

require_once('classes/phpunit.php');
require_once('../library/xml-processing/xmlparser.php');

class testGoogleXMLParser extends TestCase {          
  function testGoogleXMLParser($name) {
    $this->TestCase($name);
  }

  function setUp() {
    /* put any common setup here */
  }

  function tearDown() {
    /* put any common endup here */
  }

  function testGoogleXMLParserGetRoot(){
    $xml =  '<addresses>
                <anonymous-address id="123">
                  <test>data 1 </test>
                </anonymous-address>
                <anonymous-address id="456">
                  <test>data 2 </test>
                </anonymous-address>
            </addresses>';

    $xml_parsed = new xmlParser($xml);
    $this->assertEquals($xml_parsed->getRoot(), 'addresses');
  }

  function testGoogleXMLParserGetDataSimpleXML(){
    $xml =  '<addresses>
                <anonymous-address id="123">
                  <test>data 1 </test>
                </anonymous-address>
                <anonymous-address id="456">
                  <test>data 2 </test>
                </anonymous-address>
            </addresses>';

    $xml_parsed = new xmlParser($xml);
    $data = $xml_parsed->getData();
    
    $this->assertEquals(serialize($data), 'a:1:{s:9:"addresses";a:1:{s:17:"anonymous-address";a:2:{i:0;a:2:{s:2:"id";s:3:"123";s:4:"test";a:1:{s:5:"VALUE";s:7:"data 1 ";}}i:1;a:2:{s:2:"id";s:3:"456";s:4:"test";a:1:{s:5:"VALUE";s:7:"data 2 ";}}}}}');
  }

  function testGoogleXMLParserGetDataMediumXML(){
    $xml =  '<merchant-calculation-callback xmlns="http://checkout.google.com/schema/2" serial-number="f1554537-aeeb-4317-9f34-9a88c104d8ae">
  <shopping-cart>
    <items>
      <item>
        <tax-table-selector>canada</tax-table-selector>
        <item-name>Theres Something About Mary Linked</item-name>
        <quantity>1</quantity>
        <unit-price currency="USD">49.99</unit-price>
        <merchant-item-id>19</merchant-item-id>
        <item-description></item-description>
        <merchant-private-item-data>YToxNzp7czoyOiJpZCI7aToxOTtzOjg6ImNhdGVnb3J5IjtzOjI6IjEyIjtzOjQ6Im5hbWUiO3M6MzU6IlRoZXJlJ3MgU29tZXRoaW5nIEFib3V0IE1hcnkgTGlua2VkIjtzOjU6Im1vZGVsIjtzOjg6IkRWRC1UU0FCIjtzOjU6ImltYWdlIjtzOjM1OiJkdmQvdGhlcmVzX3NvbWV0aGluZ19hYm91dF9tYXJ5LmdpZiI7czo1OiJwcmljZSI7czo3OiI0OS45OTAwIjtzOjg6InF1YW50aXR5IjtpOjE7czo2OiJ3ZWlnaHQiO2k6NztzOjExOiJmaW5hbF9wcmljZSI7ZDo0OS45OTAwMDAwMDAwMDAwMDE5ODk1MTk2NjAxMjgyODA1MjA0MzkxNDc5NDkyMTg3NTtzOjE1OiJvbmV0aW1lX2NoYXJnZXMiO2k6MDtzOjEyOiJ0YXhfY2xhc3NfaWQiO3M6MToiMyI7czoxMDoiYXR0cmlidXRlcyI7czowOiIiO3M6MTc6ImF0dHJpYnV0ZXNfdmFsdWVzIjtzOjA6IiI7czoyODoicHJvZHVjdHNfcHJpY2VkX2J5X2F0dHJpYnV0ZSI7czoxOiIwIjtzOjE1OiJwcm9kdWN0X2lzX2ZyZWUiO3M6MToiMCI7czoyMjoicHJvZHVjdHNfZGlzY291bnRfdHlwZSI7czoxOiIwIjtzOjI3OiJwcm9kdWN0c19kaXNjb3VudF90eXBlX2Zyb20iO3M6MToiMCI7fQ==</merchant-private-item-data>
      </item>
    </items>
    <merchant-private-data>


      <session-data>f2f7a0b668ff17095fb996420b8f428c;zenid</session-data>


      <ip-address>192.168.128.150</ip-address>


    </merchant-private-data>
  </shopping-cart>
  <buyer-id>672088952143679</buyer-id>
  <calculate>
    <shipping>
      <method name="Per Item National: Item National" />
      <method name="Zones: Zones Rates" />
    </shipping>
    <addresses>
      <anonymous-address id="562553315477382">
        <country-code>US</country-code>
        <city>miami</city>
        <region>FL</region>
        <postal-code>33013</postal-code>
      </anonymous-address>
    </addresses>
    <merchant-code-strings>
      <merchant-code-string code="123" />
    </merchant-code-strings>
    <tax>false</tax>
  </calculate>
  <buyer-language>en_US</buyer-language>
</merchant-calculation-callback>
';

    $xml_parsed = new xmlParser($xml);
    $data = $xml_parsed->getData();
    $this->assertEquals(serialize($data), 
      'a:1:{s:29:"merchant-calculation-callback";a:6:{s:5:"xmlns";s:35:"http:' .
      '//checkout.google.com/schema/2";s:13:"serial-number";s:36:"f1554537-ae' .
      'eb-4317-9f34-9a88c104d8ae";s:13:"shopping-cart";a:2:{s:5:"items";a:1:' .
      '{s:4:"item";a:7:{s:18:"tax-table-selector";a:1:{s:5:"VALUE";s:6:"canad' .
      'a";}s:9:"item-name";a:1:{s:5:"VALUE";s:34:"Theres Something About Mary' .
      ' Linked";}s:8:"quantity";a:1:{s:5:"VALUE";s:1:"1";}s:10:"unit-price";a' .
      ':2:{s:8:"currency";s:3:"USD";s:5:"VALUE";s:5:"49.99";}s:16:"merchant-i' .
      'tem-id";a:1:{s:5:"VALUE";s:2:"19";}s:16:"item-description";a:1:{s:5:"V' .
      'ALUE";s:0:"";}s:26:"merchant-private-item-data";a:1:{s:5:"VALUE";s:780' .
      ':"YToxNzp7czoyOiJpZCI7aToxOTtzOjg6ImNhdGVnb3J5IjtzOjI6IjEyIjtzOjQ6Im5h' .
      'bWUiO3M6MzU6IlRoZXJlJ3MgU29tZXRoaW5nIEFib3V0IE1hcnkgTGlua2VkIjtzOjU6Im' .
      '1vZGVsIjtzOjg6IkRWRC1UU0FCIjtzOjU6ImltYWdlIjtzOjM1OiJkdmQvdGhlcmVzX3Nv' .
      'bWV0aGluZ19hYm91dF9tYXJ5LmdpZiI7czo1OiJwcmljZSI7czo3OiI0OS45OTAwIjtzOj' .
      'g6InF1YW50aXR5IjtpOjE7czo2OiJ3ZWlnaHQiO2k6NztzOjExOiJmaW5hbF9wcmljZSI7' .
      'ZDo0OS45OTAwMDAwMDAwMDAwMDE5ODk1MTk2NjAxMjgyODA1MjA0MzkxNDc5NDkyMTg3NT' .
      'tzOjE1OiJvbmV0aW1lX2NoYXJnZXMiO2k6MDtzOjEyOiJ0YXhfY2xhc3NfaWQiO3M6MToi' .
      'MyI7czoxMDoiYXR0cmlidXRlcyI7czowOiIiO3M6MTc6ImF0dHJpYnV0ZXNfdmFsdWVzIj' .
      'tzOjA6IiI7czoyODoicHJvZHVjdHNfcHJpY2VkX2J5X2F0dHJpYnV0ZSI7czoxOiIwIjtz' .
      'OjE1OiJwcm9kdWN0X2lzX2ZyZWUiO3M6MToiMCI7czoyMjoicHJvZHVjdHNfZGlzY291bn' .
      'RfdHlwZSI7czoxOiIwIjtzOjI3OiJwcm9kdWN0c19kaXNjb3VudF90eXBlX2Zyb20iO3M6' .
      'MToiMCI7fQ==";}}}s:21:"merchant-private-data";a:2:{s:12:"session-data"' .
      ';a:1:{s:5:"VALUE";s:38:"f2f7a0b668ff17095fb996420b8f428c;zenid";}s:10:' .
      '"ip-address";a:1:{s:5:"VALUE";s:15:"192.168.128.150";}}}s:8:"buyer-id"' .
      ';a:1:{s:5:"VALUE";s:15:"672088952143679";}s:9:"calculate";a:4:{s:8:"sh' .
      'ipping";a:1:{s:6:"method";a:2:{i:0;a:2:{s:4:"name";s:32:"Per Item Nati' .
      'onal: Item National";s:5:"VALUE";s:0:"";}i:1;a:2:{s:4:"name";s:18:"Zon' .
      'es: Zones Rates";s:5:"VALUE";s:0:"";}}}s:9:"addresses";a:1:{s:17:"anon' .
      'ymous-address";a:5:{s:2:"id";s:15:"562553315477382";s:12:"country-code' .
      '";a:1:{s:5:"VALUE";s:2:"US";}s:4:"city";a:1:{s:5:"VALUE";s:5:"miami";}' .
      's:6:"region";a:1:{s:5:"VALUE";s:2:"FL";}s:11:"postal-code";a:1:{s:5:"V' .
      'ALUE";s:5:"33013";}}}s:21:"merchant-code-strings";a:1:{s:20:"merchant-' .
      'code-string";a:2:{s:4:"code";s:3:"123";s:5:"VALUE";s:0:"";}}s:3:"tax";' .
      'a:1:{s:5:"VALUE";s:5:"false";}}s:14:"buyer-language";a:1:{s:5:"VALUE";' .
      's:5:"en_US";}}}');
  }

  function testGoogleXMLParserGetDataComplexXML(){
    $xml =  '<?xml version="1.0" encoding="utf-8"?>
<checkout-shopping-cart xmlns="http://checkout.google.com/schema/2">
  <shopping-cart>
    <items>
      <item>
        <item-name>Microsoft IntelliMouse Explorer</item-name>
        <item-description>[Model:USB] </item-description>
        <unit-price currency="USD">70.95</unit-price>
        <quantity>1</quantity>
        <merchant-private-item-data>YToxODp7czoyOiJpZCI7czozNToiMjY6NDYxNDc2NmQ3OGIyNmZiNzFiZmQyMWQwOWY5ZWI5OGMiO3M6ODoiY2F0ZWdvcnkiO3M6MToiOSI7czo0OiJuYW1lIjtzOjMxOiJNaWNyb3NvZnQgSW50ZWxsaU1vdXNlIEV4cGxvcmVyIjtzOjU6Im1vZGVsIjtzOjc6Ik1TSU1FWFAiO3M6NToiaW1hZ2UiO3M6MjQ6Im1pY3Jvc29mdC9pbWV4cGxvcmVyLmdpZiI7czo1OiJwcmljZSI7czo3OiI2NC45NTAwIjtzOjg6InF1YW50aXR5IjtpOjE7czo2OiJ3ZWlnaHQiO2k6ODtzOjExOiJmaW5hbF9wcmljZSI7ZDo3MC45NTAwMDAwMDAwMDAwMDI4NDIxNzA5NDMwNDA0MDA3NDM0ODQ0OTcwNzAzMTI1O3M6MTU6Im9uZXRpbWVfY2hhcmdlcyI7aTowO3M6MTI6InRheF9jbGFzc19pZCI7czoxOiIxIjtzOjEwOiJhdHRyaWJ1dGVzIjthOjE6e2k6MztzOjE6IjkiO31zOjE3OiJhdHRyaWJ1dGVzX3ZhbHVlcyI7czowOiIiO3M6Mjg6InByb2R1Y3RzX3ByaWNlZF9ieV9hdHRyaWJ1dGUiO3M6MToiMCI7czoxNToicHJvZHVjdF9pc19mcmVlIjtzOjE6IjAiO3M6MjI6InByb2R1Y3RzX2Rpc2NvdW50X3R5cGUiO3M6MToiMCI7czoyNzoicHJvZHVjdHNfZGlzY291bnRfdHlwZV9mcm9tIjtzOjE6IjAiO2k6MzthOjU6e3M6MjE6InByb2R1Y3RzX29wdGlvbnNfbmFtZSI7czo1OiJNb2RlbCI7czoxNzoib3B0aW9uc192YWx1ZXNfaWQiO3M6MToiOSI7czoyODoicHJvZHVjdHNfb3B0aW9uc192YWx1ZXNfbmFtZSI7czozOiJVU0IiO3M6MjA6Im9wdGlvbnNfdmFsdWVzX3ByaWNlIjtzOjY6IjYuMDAwMCI7czoxMjoicHJpY2VfcHJlZml4IjtzOjE6IisiO319</merchant-private-item-data>
        <merchant-item-id>26:4614766d78b26fb71bfd21d09f9eb98c</merchant-item-id>
        <tax-table-selector>Taxable Goods</tax-table-selector>
      </item>
    </items>
    <merchant-private-data>
      <session-data>f2f7a0b668ff17095fb996420b8f428c;zenid</session-data>
      <ip-address>192.168.128.150</ip-address>
    </merchant-private-data>
  </shopping-cart>
  <checkout-flow-support>
    <merchant-checkout-flow-support>
      <edit-cart-url>http://200.69.205.154/~brovagnati/zen_demo2/index.php?main_page=shopping_cart</edit-cart-url>
      <continue-shopping-url>http://200.69.205.154/~brovagnati/zen_demo2/index.php?main_page=shopping_cart</continue-shopping-url>
      <shipping-methods>
        <merchant-calculated-shipping name="Per Item International: Item International">
          <price currency="USD">10</price>
          <shipping-restrictions>
            <allow-us-po-box>true</allow-us-po-box>
            <allowed-areas>
              <world-area />
            </allowed-areas>
            <excluded-areas>
              <postal-area>
                <country-code>US</country-code>
              </postal-area>
            </excluded-areas>
          </shipping-restrictions>
          <address-filters>
            <allow-us-po-box>true</allow-us-po-box>
            <allowed-areas>
              <world-area />
            </allowed-areas>
            <excluded-areas>
              <postal-area>
                <country-code>US</country-code>
              </postal-area>
            </excluded-areas>
          </address-filters>
        </merchant-calculated-shipping>
        <merchant-calculated-shipping name="Per Item National: Item National">
          <price currency="USD">5</price>
          <shipping-restrictions>
            <allow-us-po-box>true</allow-us-po-box>
            <allowed-areas>
              <us-country-area country-area="ALL" />
            </allowed-areas>
          </shipping-restrictions>
          <address-filters>
            <allow-us-po-box>true</allow-us-po-box>
            <allowed-areas>
              <us-country-area country-area="ALL" />
            </allowed-areas>
          </address-filters>
        </merchant-calculated-shipping>
        <merchant-calculated-shipping name="Zones: Zones Rates">
          <price currency="USD">1</price>
          <shipping-restrictions>
            <allow-us-po-box>true</allow-us-po-box>
            <allowed-areas>
              <us-country-area country-area="ALL" />
            </allowed-areas>
          </shipping-restrictions>
          <address-filters>
            <allow-us-po-box>true</allow-us-po-box>
            <allowed-areas>
              <us-country-area country-area="ALL" />
            </allowed-areas>
          </address-filters>
        </merchant-calculated-shipping>
        <merchant-calculated-shipping name="Zones: Zones Rates intl">
          <price currency="USD">2</price>
          <shipping-restrictions>
            <allow-us-po-box>true</allow-us-po-box>
            <allowed-areas>
              <world-area />
            </allowed-areas>
            <excluded-areas>
              <postal-area>
                <country-code>US</country-code>
              </postal-area>
            </excluded-areas>
          </shipping-restrictions>
          <address-filters>
            <allow-us-po-box>true</allow-us-po-box>
            <allowed-areas>
              <world-area />
            </allowed-areas>
            <excluded-areas>
              <postal-area>
                <country-code>US</country-code>
              </postal-area>
            </excluded-areas>
          </address-filters>
        </merchant-calculated-shipping>
      </shipping-methods>
      <request-buyer-phone-number>true</request-buyer-phone-number>
      <merchant-calculations>
        <merchant-calculations-url>http://200.69.205.154/~brovagnati/zen_demo2/googlecheckout/responsehandler.php</merchant-calculations-url>
        <accept-merchant-coupons>true</accept-merchant-coupons>
        <accept-gift-certificates>false</accept-gift-certificates>
      </merchant-calculations>
      <tax-tables merchant-calculated="false">
        <alternate-tax-tables>
          <alternate-tax-table standalone="false" name="Taxable Goods">
            <alternate-tax-rules>
              <alternate-tax-rule>
                <rate>0.07</rate>
                <tax-area>
                  <us-country-area country-area="ALL" />
                </tax-area>
              </alternate-tax-rule>
            </alternate-tax-rules>
          </alternate-tax-table>
        </alternate-tax-tables>
      </tax-tables>
      <rounding-policy>
        <mode>HALF_EVEN</mode>
        <rule>PER_LINE</rule>
      </rounding-policy>
    </merchant-checkout-flow-support>
  </checkout-flow-support>
</checkout-shopping-cart>';

    $xml_parsed = new xmlParser($xml);
    $data = $xml_parsed->getData();
    $this->assertEquals(serialize($data), 
    'a:1:{s:22:"checkout-shopping-cart";a:3:{s:5:"xmlns";s:35:"http://checkou' .
    't.google.com/schema/2";s:13:"shopping-cart";a:2:{s:5:"items";a:1:{s:4:"i' .
    'tem";a:7:{s:9:"item-name";a:1:{s:5:"VALUE";s:31:"Microsoft IntelliMouse ' .
    'Explorer";}s:16:"item-description";a:1:{s:5:"VALUE";s:12:"[Model:USB] ";' .
    '}s:10:"unit-price";a:2:{s:8:"currency";s:3:"USD";s:5:"VALUE";s:5:"70.95"' .
    ';}s:8:"quantity";a:1:{s:5:"VALUE";s:1:"1";}s:26:"merchant-private-item-d' .
    'ata";a:1:{s:5:"VALUE";s:1084:"YToxODp7czoyOiJpZCI7czozNToiMjY6NDYxNDc2Nm' .
    'Q3OGIyNmZiNzFiZmQyMWQwOWY5ZWI5OGMiO3M6ODoiY2F0ZWdvcnkiO3M6MToiOSI7czo0Oi' .
    'JuYW1lIjtzOjMxOiJNaWNyb3NvZnQgSW50ZWxsaU1vdXNlIEV4cGxvcmVyIjtzOjU6Im1vZG' .
    'VsIjtzOjc6Ik1TSU1FWFAiO3M6NToiaW1hZ2UiO3M6MjQ6Im1pY3Jvc29mdC9pbWV4cGxvcm' .
    'VyLmdpZiI7czo1OiJwcmljZSI7czo3OiI2NC45NTAwIjtzOjg6InF1YW50aXR5IjtpOjE7cz' .
    'o2OiJ3ZWlnaHQiO2k6ODtzOjExOiJmaW5hbF9wcmljZSI7ZDo3MC45NTAwMDAwMDAwMDAwMD' .
    'I4NDIxNzA5NDMwNDA0MDA3NDM0ODQ0OTcwNzAzMTI1O3M6MTU6Im9uZXRpbWVfY2hhcmdlcy' .
    'I7aTowO3M6MTI6InRheF9jbGFzc19pZCI7czoxOiIxIjtzOjEwOiJhdHRyaWJ1dGVzIjthOj' .
    'E6e2k6MztzOjE6IjkiO31zOjE3OiJhdHRyaWJ1dGVzX3ZhbHVlcyI7czowOiIiO3M6Mjg6In' .
    'Byb2R1Y3RzX3ByaWNlZF9ieV9hdHRyaWJ1dGUiO3M6MToiMCI7czoxNToicHJvZHVjdF9pc1' .
    '9mcmVlIjtzOjE6IjAiO3M6MjI6InByb2R1Y3RzX2Rpc2NvdW50X3R5cGUiO3M6MToiMCI7cz' .
    'oyNzoicHJvZHVjdHNfZGlzY291bnRfdHlwZV9mcm9tIjtzOjE6IjAiO2k6MzthOjU6e3M6Mj' .
    'E6InByb2R1Y3RzX29wdGlvbnNfbmFtZSI7czo1OiJNb2RlbCI7czoxNzoib3B0aW9uc192YW' .
    'x1ZXNfaWQiO3M6MToiOSI7czoyODoicHJvZHVjdHNfb3B0aW9uc192YWx1ZXNfbmFtZSI7cz' .
    'ozOiJVU0IiO3M6MjA6Im9wdGlvbnNfdmFsdWVzX3ByaWNlIjtzOjY6IjYuMDAwMCI7czoxMj' .
    'oicHJpY2VfcHJlZml4IjtzOjE6IisiO319";}s:16:"merchant-item-id";a:1:{s:5:"V' .
    'ALUE";s:35:"26:4614766d78b26fb71bfd21d09f9eb98c";}s:18:"tax-table-select' .
    'or";a:1:{s:5:"VALUE";s:13:"Taxable Goods";}}}s:21:"merchant-private-data' .
    '";a:2:{s:12:"session-data";a:1:{s:5:"VALUE";s:38:"f2f7a0b668ff17095fb996' .
    '420b8f428c;zenid";}s:10:"ip-address";a:1:{s:5:"VALUE";s:15:"192.168.128.' .
    '150";}}}s:21:"checkout-flow-support";a:1:{s:30:"merchant-checkout-flow-s' .
    'upport";a:7:{s:13:"edit-cart-url";a:1:{s:5:"VALUE";s:77:"http://200.69.2' .
    '05.154/~brovagnati/zen_demo2/index.php?main_page=shopping_cart";}s:21:"c' .
    'ontinue-shopping-url";a:1:{s:5:"VALUE";s:77:"http://200.69.205.154/~brov' .
    'agnati/zen_demo2/index.php?main_page=shopping_cart";}s:16:"shipping-meth' .
    'ods";a:1:{s:28:"merchant-calculated-shipping";a:4:{i:0;a:4:{s:4:"name";s' .
    ':42:"Per Item International: Item International";s:5:"price";a:2:{s:8:"c' .
    'urrency";s:3:"USD";s:5:"VALUE";s:2:"10";}s:21:"shipping-restrictions";a:' .
    '3:{s:15:"allow-us-po-box";a:1:{s:5:"VALUE";s:4:"true";}s:13:"allowed-are' .
    'as";a:1:{s:10:"world-area";a:1:{s:5:"VALUE";s:0:"";}}s:14:"excluded-area' .
    's";a:1:{s:11:"postal-area";a:1:{s:12:"country-code";a:1:{s:5:"VALUE";s:2' .
    ':"US";}}}}s:15:"address-filters";a:3:{s:15:"allow-us-po-box";a:1:{s:5:"V' .
    'ALUE";s:4:"true";}s:13:"allowed-areas";a:1:{s:10:"world-area";a:1:{s:5:"' .
    'VALUE";s:0:"";}}s:14:"excluded-areas";a:1:{s:11:"postal-area";a:1:{s:12:' .
    '"country-code";a:1:{s:5:"VALUE";s:2:"US";}}}}}i:1;a:4:{s:4:"name";s:32:"' .
    'Per Item National: Item National";s:5:"price";a:2:{s:8:"currency";s:3:"U' .
    'SD";s:5:"VALUE";s:1:"5";}s:21:"shipping-restrictions";a:2:{s:15:"allow-u' .
    's-po-box";a:1:{s:5:"VALUE";s:4:"true";}s:13:"allowed-areas";a:1:{s:15:"u' .
    's-country-area";a:2:{s:12:"country-area";s:3:"ALL";s:5:"VALUE";s:0:"";}}' .
    '}s:15:"address-filters";a:2:{s:15:"allow-us-po-box";a:1:{s:5:"VALUE";s:4' .
    ':"true";}s:13:"allowed-areas";a:1:{s:15:"us-country-area";a:2:{s:12:"cou' .
    'ntry-area";s:3:"ALL";s:5:"VALUE";s:0:"";}}}}i:2;a:4:{s:4:"name";s:18:"Zo' .
    'nes: Zones Rates";s:5:"price";a:2:{s:8:"currency";s:3:"USD";s:5:"VALUE";' .
    's:1:"1";}s:21:"shipping-restrictions";a:2:{s:15:"allow-us-po-box";a:1:{s' .
    ':5:"VALUE";s:4:"true";}s:13:"allowed-areas";a:1:{s:15:"us-country-area";' .
    'a:2:{s:12:"country-area";s:3:"ALL";s:5:"VALUE";s:0:"";}}}s:15:"address-f' .
    'ilters";a:2:{s:15:"allow-us-po-box";a:1:{s:5:"VALUE";s:4:"true";}s:13:"a' .
    'llowed-areas";a:1:{s:15:"us-country-area";a:2:{s:12:"country-area";s:3:"' .
    'ALL";s:5:"VALUE";s:0:"";}}}}i:3;a:4:{s:4:"name";s:23:"Zones: Zones Rates' .
    ' intl";s:5:"price";a:2:{s:8:"currency";s:3:"USD";s:5:"VALUE";s:1:"2";}s:' .
    '21:"shipping-restrictions";a:3:{s:15:"allow-us-po-box";a:1:{s:5:"VALUE";' .
    's:4:"true";}s:13:"allowed-areas";a:1:{s:10:"world-area";a:1:{s:5:"VALUE"' .
    ';s:0:"";}}s:14:"excluded-areas";a:1:{s:11:"postal-area";a:1:{s:12:"count' .
    'ry-code";a:1:{s:5:"VALUE";s:2:"US";}}}}s:15:"address-filters";a:3:{s:15:' .
    '"allow-us-po-box";a:1:{s:5:"VALUE";s:4:"true";}s:13:"allowed-areas";a:1:' .
    '{s:10:"world-area";a:1:{s:5:"VALUE";s:0:"";}}s:14:"excluded-areas";a:1:' .
    '{s:11:"postal-area";a:1:{s:12:"country-code";a:1:{s:5:"VALUE";s:2:"US";}' .
    '}}}}}}s:26:"request-buyer-phone-number";a:1:{s:5:"VALUE";s:4:"true";}s:2' .
    '1:"merchant-calculations";a:3:{s:25:"merchant-calculations-url";a:1:{s:5' .
    ':"VALUE";s:78:"http://200.69.205.154/~brovagnati/zen_demo2/googlecheckou' .
    't/responsehandler.php";}s:23:"accept-merchant-coupons";a:1:{s:5:"VALUE";' .
    's:4:"true";}s:24:"accept-gift-certificates";a:1:{s:5:"VALUE";s:5:"false"' .
    ';}}s:10:"tax-tables";a:2:{s:19:"merchant-calculated";s:5:"false";s:20:"a' .
    'lternate-tax-tables";a:1:{s:19:"alternate-tax-table";a:3:{s:10:"standalo' .
    'ne";s:5:"false";s:4:"name";s:13:"Taxable Goods";s:19:"alternate-tax-rule' .
    's";a:1:{s:18:"alternate-tax-rule";a:2:{s:4:"rate";a:1:{s:5:"VALUE";s:4:"' .
    '0.07";}s:8:"tax-area";a:1:{s:15:"us-country-area";a:2:{s:12:"country-are' .
    'a";s:3:"ALL";s:5:"VALUE";s:0:"";}}}}}}}s:15:"rounding-policy";a:2:{s:4:"' .
    'mode";a:1:{s:5:"VALUE";s:9:"HALF_EVEN";}s:4:"rule";a:1:{s:5:"VALUE";s:8:' .
    '"PER_LINE";}}}}}}');
  }

  function testGoogleXMLParserGetRootXMLNotFolding(){
    $xml =  '<addresses>
                <anonymous-address id="123">
                  <test>data 1 </test>
                </anonymous-address>
                <anonymous-address id="456">
                  <test>data 2 </test>
                </anonymous-address>
            </addresses>';

    $xml_parsed = new xmlParser($xml, array());
    
    $this->assertEquals($xml_parsed->getRoot(), 'ADDRESSES', 
                                                'Should be root in UpperCase');
  }
}

if(!isset($suite)) {
  $suite = new TestSuite();
}

$suite->addTest(new testGoogleXMLParser("testGoogleXMLParserGetRoot"));
$suite->addTest(new testGoogleXMLParser("testGoogleXMLParserGetDataSimpleXML"));
$suite->addTest(new testGoogleXMLParser("testGoogleXMLParserGetDataMediumXML"));
$suite->addTest(new testGoogleXMLParser("testGoogleXMLParserGetDataComplexXML"));
$suite->addTest(new testGoogleXMLParser("testGoogleXMLParserGetRootXMLNotFolding"));


?>