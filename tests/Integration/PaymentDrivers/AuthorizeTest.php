<?php

namespace Tests\Integration\PaymentDrivers;

use Tests\TestCase;
use net\authorize\api\constants\ANetEnvironment;
use net\authorize\api\contract\v1 as AnetAPI;
use net\authorize\api\contract\v1\CreateTransactionRequest;
use net\authorize\api\contract\v1\GetMerchantDetailsRequest;
use net\authorize\api\contract\v1\MerchantAuthenticationType;
use net\authorize\api\controller\CreateTransactionController;
use net\authorize\api\controller\GetMerchantDetailsController;

/**
 * @test
 */
class AuthorizeTest extends TestCase
{

    public function setUp() :void
    {
        parent::setUp();

        if (! config('ninja.testvars.authorize')) {
            $this->markTestSkipped('authorize.net not configured');
        }
        
    }

    public function testUnpackingVars()
    {
        $vars = json_decode(config('ninja.testvars.authorize'));

        $this->assertTrue(property_exists($vars, 'apiLoginId'));
    }

    public function testCreatePublicClientKey()
    {
        error_reporting (E_ALL & ~E_DEPRECATED);

        $vars = json_decode(config('ninja.testvars.authorize'));

        $merchantAuthentication = new AnetAPI\MerchantAuthenticationType();
        $merchantAuthentication->setName($vars->apiLoginId);
        $merchantAuthentication->setTransactionKey($vars->transactionKey);

        $request = new AnetAPI\GetMerchantDetailsRequest();
        $request->setMerchantAuthentication($merchantAuthentication);

        $controller = new GetMerchantDetailsController($request);

        $response = $controller->executeWithApiResponse( \net\authorize\api\constants\ANetEnvironment::SANDBOX);

        $this->assertNotNull($response->getPublicClientKey());
    }

}
