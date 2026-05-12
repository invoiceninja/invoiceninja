<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Integration\Einvoice\Storecove;

use Tests\TestCase;
use App\Models\Company;
use App\Models\Country;
use Tests\MockAccountData;
use App\DataMapper\CompanySettings;
use App\Services\EDocument\Gateway\Storecove\Storecove;
use App\Services\EDocument\Gateway\Storecove\StorecoveProxy;
use App\Services\EDocument\Gateway\Storecove\StorecoveC5;
use App\Services\EDocument\Gateway\Storecove\LegalEntityService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Response;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Http;
use Mockery;

/**
 * Tests for StorecoveProxy — the hosted/self-hosted gateway layer.
 *
 * Every proxy method follows the same pattern:
 *   - Hosted:     call Storecove directly → handle array (success) or Response (error)
 *   - Self-hosted: HTTP POST to hosted Ninja server → parse response
 *
 * These tests mock the Storecove service to verify:
 *   1. Hosted path: success responses are returned as-is
 *   2. Hosted path: error responses are parsed by handleResponseError()
 *   3. Self-hosted path: correct URI and payload sent via HTTP
 *   4. Self-hosted path: quota header is captured
 *   5. handleResponseError() correctly parses various HTTP error codes
 *   6. Discovery: hosted vs self-hosted branching
 */
class StorecoveProxyTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    private StorecoveProxy $proxy;
    private Storecove $mockStorecove;
    private LegalEntityService $mockLegalEntity;
    private Company $testCompany;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        if (config('ninja.testvars.travis') !== false) {
            $this->markTestSkipped('Skip test for GH Actions');
        }

        $this->withoutMiddleware(ThrottleRequests::class);

        $this->setupCompany();
        $this->setupMocks();
    }

    private function setupCompany(): void
    {
        $settings = CompanySettings::defaults();
        $settings->vat_number = 'DE923356489';
        $settings->id_number = '01234567890';
        $settings->classification = 'business';
        $settings->country_id = Country::where('iso_3166_2', 'DE')->first()->id;

        $this->company->settings = $settings;
        $this->company->legal_entity_id = 290868;
        $this->company->save();

        $account = $this->company->account;
        $account->e_invoicing_token = 'test-token-123';
        $account->e_invoice_quota = 100;
        $account->save();

        $this->testCompany = $this->company;
    }

    private function setupMocks(): void
    {
        $this->mockStorecove = Mockery::mock(Storecove::class)->makePartial();
        $this->mockStorecove->router = new \App\Services\EDocument\Gateway\Storecove\StorecoveRouter();

        $mockC5 = Mockery::mock(StorecoveC5::class);
        $this->mockStorecove->c5 = $mockC5;

        $this->mockLegalEntity = Mockery::mock(LegalEntityService::class);
        $this->mockStorecove->legalEntity = $this->mockLegalEntity;

        $this->proxy = new StorecoveProxy($this->mockStorecove);
        $this->proxy->setCompany($this->testCompany);
    }

    private function setHosted(): void
    {
        config(['ninja.environment' => 'hosted']);
    }

    private function setSelfHosted(): void
    {
        config(['ninja.environment' => 'selfhost']);
    }

    private function makeMockResponse(int $status, array $body = []): Response
    {
        return new Response(
            new \GuzzleHttp\Psr7\Response($status, ['Content-Type' => 'application/json'], json_encode($body))
        );
    }

    // ════════════════════════════════════════════════════════════════════
    // getLegalEntity
    // ════════════════════════════════════════════════════════════════════

    public function testGetLegalEntityHostedSuccess(): void
    {
        $this->setHosted();

        $expected = ['id' => 290868, 'party_name' => 'Test Company', 'city' => 'Berlin'];

        $this->mockStorecove
            ->shouldReceive('getLegalEntity')
            ->with(290868)
            ->once()
            ->andReturn($expected);

        $result = $this->proxy->getLegalEntity(290868);

        $this->assertEquals($expected, $result);
    }

    public function testGetLegalEntityHostedError(): void
    {
        $this->setHosted();

        $errorResponse = $this->makeMockResponse(404, ['message' => 'Entity not found']);

        $this->mockStorecove
            ->shouldReceive('getLegalEntity')
            ->with(999999)
            ->once()
            ->andReturn($errorResponse);

        $result = $this->proxy->getLegalEntity(999999);

        $this->assertEquals('error', $result['status']);
        $this->assertEquals(404, $result['code']);
        $this->assertEquals('Resource not found', $result['message']);
    }

    public function testGetLegalEntitySelfHosted(): void
    {
        $this->setSelfHosted();

        Http::fake([
            '*/api/einvoice/peppol/legal_entity' => Http::response(
                ['id' => 290868, 'party_name' => 'Test Company'],
                200,
            ),
        ]);

        $result = $this->proxy->getLegalEntity(290868);

        $this->assertEquals(290868, $result['id']);
        $this->assertEquals('Test Company', $result['party_name']);
    }

    // ════════════════════════════════════════════════════════════════════
    // setup (create legal entity)
    // ════════════════════════════════════════════════════════════════════

    public function testSetupHostedSuccess(): void
    {
        $this->setHosted();

        $data = [
            'country' => 'DE',
            'acts_as_sender' => true,
            'acts_as_receiver' => true,
        ];

        $this->mockStorecove
            ->shouldReceive('checkNetworkStatus')
            ->once()
            ->andReturn(false);

        $this->mockStorecove
            ->shouldReceive('setupLegalEntity')
            ->once()
            ->andReturn(['legal_entity_id' => 290868, 'tax_data' => ['acts_as_sender' => true, 'acts_as_receiver' => true]]);

        $result = $this->proxy->setup($data);

        $this->assertEquals(290868, $result['legal_entity_id']);
    }

    public function testSetupHostedAlreadyRegistered(): void
    {
        $this->setHosted();

        $alreadyRegistered = [
            'status' => 'error',
            'code' => 422,
            'error' => ['status' => 'error', 'code' => 422],
        ];

        $this->mockStorecove
            ->shouldReceive('checkNetworkStatus')
            ->once()
            ->andReturn($alreadyRegistered);

        $result = $this->proxy->setup(['country' => 'DE', 'acts_as_sender' => true, 'acts_as_receiver' => true]);

        $this->assertEquals('error', $result['status']);
        $this->assertEquals(422, $result['code']);
    }

    public function testSetupHostedApiError(): void
    {
        $this->setHosted();

        $this->mockStorecove
            ->shouldReceive('checkNetworkStatus')
            ->once()
            ->andReturn(false);

        $this->mockStorecove
            ->shouldReceive('setupLegalEntity')
            ->once()
            ->andReturn($this->makeMockResponse(422, ['error' => 'Invalid data']));

        $result = $this->proxy->setup(['country' => 'DE', 'acts_as_sender' => true, 'acts_as_receiver' => true]);

        $this->assertEquals('error', $result['status']);
    }

    public function testSetupSelfHosted(): void
    {
        $this->setSelfHosted();

        Http::fake([
            '*/api/einvoice/peppol/setup' => Http::response(
                ['legal_entity_id' => 290868],
                200,
            ),
        ]);

        $result = $this->proxy->setup(['country' => 'DE', 'acts_as_sender' => true, 'acts_as_receiver' => true]);

        $this->assertEquals(290868, $result['legal_entity_id']);
    }

    public function testSetupMergesCompanyDefaults(): void
    {
        $this->setHosted();

        $capturedData = null;

        $this->mockStorecove
            ->shouldReceive('checkNetworkStatus')
            ->once()
            ->andReturn(false);

        $this->mockStorecove
            ->shouldReceive('setupLegalEntity')
            ->once()
            ->withArgs(function ($data) use (&$capturedData) {
                $capturedData = $data;
                return true;
            })
            ->andReturn(['legal_entity_id' => 290868]);

        $this->proxy->setup(['country' => 'DE', 'acts_as_sender' => true, 'acts_as_receiver' => true]);

        $this->assertEquals('DE923356489', $capturedData['vat_number']);
        $this->assertEquals('business', $capturedData['classification']);
        $this->assertEquals('01234567890', $capturedData['id_number']);
    }

    // ════════════════════════════════════════════════════════════════════
    // disconnect
    // ════════════════════════════════════════════════════════════════════

    public function testDisconnectHostedSuccess(): void
    {
        $this->setHosted();

        $this->mockStorecove
            ->shouldReceive('deleteIdentifier')
            ->with(290868)
            ->once()
            ->andReturn([]);

        $result = $this->proxy->disconnect();

        $this->assertEquals([], $result);
    }

    public function testDisconnectHostedError(): void
    {
        $this->setHosted();

        $this->mockStorecove
            ->shouldReceive('deleteIdentifier')
            ->once()
            ->andReturn($this->makeMockResponse(500, ['error' => 'Internal error']));

        $result = $this->proxy->disconnect();

        $this->assertEquals('error', $result['status']);
        $this->assertEquals(500, $result['code']);
    }

    public function testDisconnectSelfHosted(): void
    {
        $this->setSelfHosted();

        // Auth the user so remoteRequest can access auth()->user()
        $this->actingAs($this->user);

        Http::fake([
            '*/api/einvoice/peppol/disconnect' => Http::response(
                ['status' => 'disconnected'],
                200,
            ),
        ]);

        $result = $this->proxy->disconnect();

        $this->assertIsArray($result);
        $this->assertEquals('disconnected', $result['status']);
    }

    // ════════════════════════════════════════════════════════════════════
    // updateLegalEntity
    // ════════════════════════════════════════════════════════════════════

    public function testUpdateLegalEntityHostedSuccess(): void
    {
        $this->setHosted();

        $this->mockStorecove
            ->shouldReceive('updateLegalEntity')
            ->with(290868, Mockery::type('array'))
            ->once()
            ->andReturn(['id' => 290868, 'acts_as_sender' => true]);

        $result = $this->proxy->updateLegalEntity(['acts_as_sender' => true]);

        $this->assertEquals(290868, $result['id']);
    }

    public function testUpdateLegalEntitySelfHosted(): void
    {
        $this->setSelfHosted();

        Http::fake([
            '*/api/einvoice/peppol/update' => Http::response(['id' => 290868], 200),
        ]);

        $result = $this->proxy->updateLegalEntity(['acts_as_sender' => false]);

        $this->assertEquals(290868, $result['id']);
    }

    // ════════════════════════════════════════════════════════════════════
    // addAdditionalTaxIdentifier
    // ════════════════════════════════════════════════════════════════════

    public function testAddAdditionalTaxIdentifierHostedSuccess(): void
    {
        $this->setHosted();

        $this->testCompany->legal_entity_id = 290868;
        $this->testCompany->save();

        $this->mockLegalEntity
            ->shouldReceive('addAdditionalTaxIdentifier')
            ->withArgs(function ($legalEntityId, $data) {
                return $legalEntityId === 290868
                    && ($data['country'] ?? null) === 'FR'
                    && ($data['vat_number'] ?? null) === 'FRAA123456789'
                    && ($data['identifier'] ?? null) === 'FRAA123456789'
                    && ($data['scheme'] ?? null) === 'FR:VAT'
                    && ($data['legal_entity_id'] ?? null) === 290868;
            })
            ->once()
            ->andReturn(['id' => 42, 'identifier' => 'FRAA123456789']);

        $result = $this->proxy->addAdditionalTaxIdentifier([
            'country' => 'FR',
            'vat_number' => 'FRAA123456789',
        ]);

        $this->assertEquals('FRAA123456789', $result['identifier']);
    }

    public function testAddAdditionalTaxIdentifierSelfHosted(): void
    {
        $this->setSelfHosted();

        Http::fake([
            '*/api/einvoice/peppol/add_additional_legal_identifier' => Http::response(
                ['id' => 42, 'identifier' => 'FRAA123456789'],
                200,
            ),
        ]);

        $result = $this->proxy->addAdditionalTaxIdentifier([
            'country' => 'FR',
            'vat_number' => 'FRAA123456789',
        ]);

        $this->assertEquals('FRAA123456789', $result['identifier']);
    }

    // ════════════════════════════════════════════════════════════════════
    // removeAdditionalTaxIdentifier
    // ════════════════════════════════════════════════════════════════════

    public function testRemoveAdditionalTaxIdentifierHostedSuccess(): void
    {
        $this->setHosted();

        $this->mockStorecove
            ->shouldReceive('removeAdditionalTaxIdentifier')
            ->with(290868, 'FRAA123456789')
            ->once()
            ->andReturn([]);

        $result = $this->proxy->removeAdditionalTaxIdentifier(['vat_number' => 'FRAA123456789']);

        $this->assertEquals([], $result);
    }

    public function testRemoveAdditionalTaxIdentifierHostedNotFound(): void
    {
        $this->setHosted();

        $this->mockStorecove
            ->shouldReceive('removeAdditionalTaxIdentifier')
            ->with(290868, 'XX999999999')
            ->once()
            ->andReturn(false);

        $result = $this->proxy->removeAdditionalTaxIdentifier(['vat_number' => 'XX999999999']);

        $this->assertFalse($result);
    }

    public function testRemoveAdditionalTaxIdentifierSelfHosted(): void
    {
        $this->setSelfHosted();

        Http::fake([
            '*/api/einvoice/peppol/remove_additional_legal_identifier' => Http::response([], 200),
        ]);

        $result = $this->proxy->removeAdditionalTaxIdentifier(['vat_number' => 'FRAA123456789']);

        $this->assertIsArray($result);
    }

    // ════════════════════════════════════════════════════════════════════
    // C5 Singapore flows
    // ════════════════════════════════════════════════════════════════════

    public function testC5ActivateHostedSuccess(): void
    {
        $this->setHosted();

        $this->mockStorecove->c5
            ->shouldReceive('activate')
            ->with(290868, '01234567890', 'John Doe', 'john@example.com')
            ->once()
            ->andReturn(['status' => 'activated']);

        $result = $this->proxy->c5Activate('John Doe', 'john@example.com');

        $this->assertEquals('activated', $result['status']);
    }

    public function testC5ActivateHostedError(): void
    {
        $this->setHosted();

        $this->mockStorecove->c5
            ->shouldReceive('activate')
            ->once()
            ->andReturn($this->makeMockResponse(400, ['error' => 'Invalid UEN']));

        $result = $this->proxy->c5Activate('John Doe', 'john@example.com');

        $this->assertEquals('error', $result['status']);
    }

    public function testC5ActivateSelfHosted(): void
    {
        $this->setSelfHosted();

        Http::fake([
            '*/api/einvoice/peppol/sg/c5/activate' => Http::response(['status' => 'activated'], 200),
        ]);

        $result = $this->proxy->c5Activate('John Doe', 'john@example.com');

        $this->assertEquals('activated', $result['status']);
    }

    public function testC5DeactivateHostedSuccess(): void
    {
        $this->setHosted();

        $this->mockStorecove->c5
            ->shouldReceive('deactivate')
            ->with(290868, '01234567890', 'John Doe', 'john@example.com')
            ->once()
            ->andReturn(['status' => 'deactivated']);

        $result = $this->proxy->c5Deactivate('John Doe', 'john@example.com');

        $this->assertEquals('deactivated', $result['status']);
    }

    public function testC5CancelHostedSuccess(): void
    {
        $this->setHosted();

        $this->mockStorecove->c5
            ->shouldReceive('cancel')
            ->with(290868, '01234567890')
            ->once()
            ->andReturn(['status' => 'cancelled']);

        $result = $this->proxy->c5Cancel();

        $this->assertEquals('cancelled', $result['status']);
    }

    public function testC5CancelSelfHosted(): void
    {
        $this->setSelfHosted();

        Http::fake([
            '*/api/einvoice/peppol/sg/c5/cancel' => Http::response(['status' => 'cancelled'], 200),
        ]);

        $result = $this->proxy->c5Cancel();

        $this->assertEquals('cancelled', $result['status']);
    }

    public function testC5ActivateSelfHosted404TranslatesToFriendlyError(): void
    {
        $this->setSelfHosted();

        Http::fake([
            '*/api/einvoice/peppol/sg/c5/activate' => Http::response([], 404),
        ]);

        $result = $this->proxy->c5Activate('John Doe', 'john@example.com');

        $this->assertEquals('error', $result['status']);
        $this->assertEquals(503, $result['code']);
        $this->assertStringContainsString('Singapore C5', $result['message']);
    }

    public function testC5DeactivateSelfHosted404TranslatesToFriendlyError(): void
    {
        $this->setSelfHosted();

        Http::fake([
            '*/api/einvoice/peppol/sg/c5/deactivate' => Http::response([], 404),
        ]);

        $result = $this->proxy->c5Deactivate('John Doe', 'john@example.com');

        $this->assertEquals('error', $result['status']);
        $this->assertEquals(503, $result['code']);
        $this->assertStringContainsString('Singapore C5', $result['message']);
    }

    public function testC5CancelSelfHosted404TranslatesToFriendlyError(): void
    {
        $this->setSelfHosted();

        Http::fake([
            '*/api/einvoice/peppol/sg/c5/cancel' => Http::response([], 404),
        ]);

        $result = $this->proxy->c5Cancel();

        $this->assertEquals('error', $result['status']);
        $this->assertEquals(503, $result['code']);
        $this->assertStringContainsString('Singapore C5', $result['message']);
    }

    // ════════════════════════════════════════════════════════════════════
    // discovery
    // ════════════════════════════════════════════════════════════════════

    public function testDiscoveryHostedFound(): void
    {
        $this->setHosted();

        $this->mockStorecove
            ->shouldReceive('discovery')
            ->with('DE923356489', 'DE:VAT')
            ->once()
            ->andReturn(true);

        $result = $this->proxy->discovery('DE923356489', 'DE:VAT');

        $this->assertTrue($result);
    }

    public function testDiscoveryHostedNotFound(): void
    {
        $this->setHosted();

        $this->mockStorecove
            ->shouldReceive('discovery')
            ->with('XX000000000', 'XX:VAT')
            ->once()
            ->andReturn(false);

        $result = $this->proxy->discovery('XX000000000', 'XX:VAT');

        $this->assertFalse($result);
    }

    public function testDiscoverySelfHostedFound(): void
    {
        $this->setSelfHosted();

        Http::fake([
            '*/api/einvoice/peppol/discovery' => Http::response(['discovered' => true], 200),
        ]);

        $result = $this->proxy->discovery('DE923356489', 'DE:VAT');

        $this->assertTrue($result);
    }

    public function testDiscoverySelfHostedNotFound(): void
    {
        $this->setSelfHosted();

        Http::fake([
            '*/api/einvoice/peppol/discovery' => Http::response(['discovered' => false], 200),
        ]);

        $result = $this->proxy->discovery('XX000000000', 'XX:VAT');

        $this->assertFalse($result);
    }

    public function testDiscoverySelfHostedNetworkError(): void
    {
        $this->setSelfHosted();

        Http::fake([
            '*/api/einvoice/peppol/discovery' => Http::response([], 500),
        ]);

        $result = $this->proxy->discovery('DE923356489', 'DE:VAT');

        $this->assertFalse($result);
    }

    public function testDiscoverySelfHosted404FallsBackToDiscoverable(): void
    {
        $this->setSelfHosted();

        Http::fake([
            '*/api/einvoice/peppol/discovery' => Http::response([], 404),
        ]);

        $result = $this->proxy->discovery('DE923356489', 'DE:VAT');

        $this->assertTrue($result);
    }

    // ════════════════════════════════════════════════════════════════════
    // handleResponseError
    // ════════════════════════════════════════════════════════════════════

    public function testHandleResponseError401(): void
    {
        $response = $this->makeMockResponse(401, ['error' => 'Unauthorized']);

        $result = $this->proxy->handleResponseError($response);

        $this->assertEquals('error', $result['status']);
        $this->assertEquals(401, $result['code']);
        $this->assertEquals('Authentication failed', $result['message']);
    }

    public function testHandleResponseError403(): void
    {
        $response = $this->makeMockResponse(403, ['error' => 'Forbidden']);

        $result = $this->proxy->handleResponseError($response);

        $this->assertEquals('error', $result['status']);
        $this->assertEquals(403, $result['code']);
        $this->assertEquals('Access forbidden', $result['message']);
    }

    public function testHandleResponseError404(): void
    {
        $response = $this->makeMockResponse(404, ['message' => 'Not found']);

        $result = $this->proxy->handleResponseError($response);

        $this->assertEquals('error', $result['status']);
        $this->assertEquals(404, $result['code']);
        $this->assertEquals('Resource not found', $result['message']);
    }

    public function testHandleResponseError422WithErrors(): void
    {
        $response = $this->makeMockResponse(422, [
            'message' => 'Validation failed',
            'errors' => [
                ['field' => 'vat_number', 'details' => 'Invalid format'],
                ['field' => 'country', 'details' => 'Not supported'],
            ],
        ]);

        $result = $this->proxy->handleResponseError($response);

        $this->assertEquals('error', $result['status']);
        $this->assertEquals(422, $result['code']);
        $this->assertEquals('Validation failed', $result['message']);
        $this->assertCount(2, $result['errors']);
        $this->assertEquals('vat_number', $result['errors'][0]['field']);
    }

    public function testHandleResponseError500(): void
    {
        $response = $this->makeMockResponse(500, ['error' => 'Internal server error']);

        $result = $this->proxy->handleResponseError($response);

        $this->assertEquals('error', $result['status']);
        $this->assertEquals(500, $result['code']);
        $this->assertEquals('Internal server error', $result['message']);
    }

    public function testHandleResponseErrorWithEmptyBody(): void
    {
        $response = new Response(
            new \GuzzleHttp\Psr7\Response(502, [], '')
        );

        $result = $this->proxy->handleResponseError($response);

        $this->assertEquals('error', $result['status']);
        $this->assertEquals(502, $result['code']);
        $this->assertEquals('Unknown error occurred', $result['message']);
    }

    // ════════════════════════════════════════════════════════════════════
    // setCompany
    // ════════════════════════════════════════════════════════════════════

    public function testSetCompanyReturnsSelf(): void
    {
        $result = $this->proxy->setCompany($this->testCompany);

        $this->assertInstanceOf(StorecoveProxy::class, $result);
        $this->assertSame($this->testCompany, $this->proxy->company);
    }

    // ════════════════════════════════════════════════════════════════════
    // Self-hosted quota header capture
    // ════════════════════════════════════════════════════════════════════

    public function testSelfHostedQuotaHeaderCaptured(): void
    {
        $this->setSelfHosted();

        // Auth the user so remoteRequest can access auth()->user()
        $this->actingAs($this->user);

        Http::fake([
            '*/api/einvoice/peppol/legal_entity' => Http::response(
                ['id' => 290868],
                200,
                ['X-EINVOICE-QUOTA' => '42'],
            ),
        ]);

        $result = $this->proxy->getLegalEntity(290868);

        $this->assertEquals(290868, $result['id']);

        // Verify quota was saved to account
        $this->testCompany->account->refresh();
        $this->assertEquals(42, $this->testCompany->account->e_invoice_quota);
    }

    public function testSelfHostedRemoteRequestError(): void
    {
        $this->setSelfHosted();

        Http::fake([
            '*/api/einvoice/peppol/legal_entity' => Http::response(
                ['error' => 'Server error'],
                500,
            ),
        ]);

        $result = $this->proxy->getLegalEntity(290868);

        $this->assertEquals('error', $result['status']);
        $this->assertEquals(500, $result['code']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
