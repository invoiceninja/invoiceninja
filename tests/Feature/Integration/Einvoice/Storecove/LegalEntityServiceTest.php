<?php

namespace Tests\Feature\Integration\Einvoice\Storecove;

use GuzzleHttp\Psr7\Response as PsrResponse;
use App\Services\EDocument\Gateway\Storecove\Storecove;
use App\Services\EDocument\Gateway\Storecove\StorecoveRouter;
use Carbon\CarbonImmutable;
use Tests\TestCase;
use Mockery;
use Illuminate\Http\Client\Response;
use App\Services\EDocument\Gateway\Storecove\LegalEntityService;
use Illuminate\Support\Facades\Validator;
use Modules\Admin\Http\Requests\EInvoice\Peppol\StoreEntityRequestSelf;

class LegalEntityServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if(!class_exists(StoreEntityRequestSelf::class)) {
            $this->markTestSkipped('StoreEntityRequestSelf class does not exist');
        }
    }

    protected function tearDown(): void
    {

        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function testSingaporeRegistrationReturnsCorpPassFailureInsteadOfFallingThroughToStandardIdentifier(): void
    {
        $legalEntityId = 987654;
        $storecove = Mockery::mock(Storecove::class)->makePartial();
        $service = new LegalEntityService($storecove);

        $storecove->shouldReceive('httpClient')
            ->once()
            ->withArgs(function (string $uri, string $verb, array $payload) {
                return $uri === 'legal_entities'
                    && $verb === 'post'
                    && $payload['country'] === 'SG'
                    && $payload['id_number'] === '202012345A';
            })
            ->andReturn($this->makeResponse(200, [
                'id' => $legalEntityId,
                'tenant_id' => 'sg-company',
            ]));

        $corpPassFailure = $this->makeResponse(422, [
            'error' => 'This UEN is already registered on the PEPPOL network.',
            'errors' => [
                [
                    'source' => 'identifier',
                    'details' => 'This UEN is already registered on the PEPPOL network.',
                ],
            ],
        ]);

        $storecove->shouldReceive('startCorpPassFlow')
            ->once()
            ->with($legalEntityId, '202012345A')
            ->andReturn($corpPassFailure);

        $storecove->shouldReceive('deleteIdentifier')
            ->once()
            ->with($legalEntityId)
            ->andReturn([]);

        $storecove->shouldReceive('httpClient')
            ->never()
            ->withArgs(fn(string $uri, string $verb = '', array $payload = []) => $uri === "legal_entities/{$legalEntityId}/peppol_identifiers");

        $result = $service->setup([
            'country' => 'SG',
            'classification' => 'business',
            'id_number' => '202012345A',
            'vat_number' => 'M2-1234567-X',
            'tenant_id' => 'sg-company',
            'party_name' => 'Singapore Test Company',
            'tax_registered' => true,
            'city' => 'Singapore',
            'line1' => '1 Market Street',
            'zip' => '048619',
            'acts_as_sender' => true,
            'acts_as_receiver' => true,
        ]);

        $this->assertSame($corpPassFailure, $result);
        $this->assertSame(422, $result->status());
        $this->assertSame('This UEN is already registered on the PEPPOL network.', $result->json('error'));
    }

    public function testFrenchSetupRegistersVatSireneAndCompanyCtcOnTheRequiredNetworks(): void
    {
        CarbonImmutable::setTestNow('2026-08-03 12:00:00 Europe/Paris');
        $legalEntityId = 123456;
        $storecove = $this->storecoveMock();
        $service = new LegalEntityService($storecove);

        $storecove->shouldReceive('httpClient')
            ->once()
            ->ordered()
            ->withArgs(fn(string $uri, string $verb, array $payload): bool => $uri === 'legal_entities'
                && $verb === 'post'
                && $payload['country'] === 'FR')
            ->andReturn($this->makeResponse(200, [
                'id' => $legalEntityId,
                'tenant_id' => 'fr-company',
            ]));

        $storecove->shouldReceive('httpClient')
            ->once()
            ->ordered()
            ->with("legal_entities/{$legalEntityId}/peppol_identifiers", 'post', [
                'identifier' => 'FR44732829320',
                'scheme' => 'FR:VAT',
                'superscheme' => 'iso6523-actorid-upis',
                'networks_specification' => $this->peppolFranceNetworks(),
            ])
            ->andReturn($this->makeResponse(200, ['id' => 1]));

        $storecove->shouldReceive('httpClient')
            ->once()
            ->ordered()
            ->with("legal_entities/{$legalEntityId}/peppol_identifiers", 'post', [
                'identifier' => '732829320',
                'scheme' => 'FR:SIRENE',
                'superscheme' => 'iso6523-actorid-upis',
                'networks_specification' => $this->peppolFranceNetworks(),
            ])
            ->andReturn($this->makeResponse(200, ['id' => 2]));

        $storecove->shouldReceive('httpClient')
            ->once()
            ->ordered()
            ->with("legal_entities/{$legalEntityId}/peppol_identifiers", 'post', [
                'identifier' => '732829320',
                'scheme' => 'FR:CTC',
                'superscheme' => 'iso6523-actorid-upis',
                'networks_specification' => [
                    ...$this->peppolFranceNetworks(),
                    [
                        'name' => 'dgfip',
                        'sub_networks' => ['main'],
                        'annuaire' => ['start_date' => '2026-09-01'],
                    ],
                ],
            ])
            ->andReturn($this->makeResponse(200, ['id' => 3]));

        $result = $service->setup($this->frenchSetupData());

        $this->assertSame($legalEntityId, $result['legal_entity_id']);
    }

    public function testFrenchSetupRollsBackWhenRequiredCtcRegistrationFails(): void
    {
        CarbonImmutable::setTestNow('2026-08-03 12:00:00 Europe/Paris');
        $legalEntityId = 123456;
        $storecove = $this->storecoveMock();
        $service = new LegalEntityService($storecove);
        $ctcFailure = $this->makeResponse(422, ['error' => 'CTC registration failed']);

        $storecove->shouldReceive('httpClient')
            ->once()
            ->ordered()
            ->withArgs(fn(string $uri, string $verb): bool => $uri === 'legal_entities' && $verb === 'post')
            ->andReturn($this->makeResponse(200, [
                'id' => $legalEntityId,
                'tenant_id' => 'fr-company',
            ]));

        $storecove->shouldReceive('httpClient')
            ->once()
            ->ordered()
            ->withArgs(fn(string $uri, string $verb, array $payload): bool => $uri === "legal_entities/{$legalEntityId}/peppol_identifiers"
                && $verb === 'post'
                && $payload['scheme'] === 'FR:VAT')
            ->andReturn($this->makeResponse(200, ['id' => 1]));

        $storecove->shouldReceive('httpClient')
            ->once()
            ->ordered()
            ->withArgs(fn(string $uri, string $verb, array $payload): bool => $uri === "legal_entities/{$legalEntityId}/peppol_identifiers"
                && $verb === 'post'
                && $payload['scheme'] === 'FR:SIRENE')
            ->andReturn($this->makeResponse(200, ['id' => 2]));

        $storecove->shouldReceive('httpClient')
            ->once()
            ->ordered()
            ->withArgs(fn(string $uri, string $verb, array $payload): bool => $uri === "legal_entities/{$legalEntityId}/peppol_identifiers"
                && $verb === 'post'
                && $payload['scheme'] === 'FR:CTC')
            ->andReturn($ctcFailure);

        $storecove->shouldReceive('httpClient')
            ->once()
            ->ordered()
            ->with("/legal_entities/{$legalEntityId}", 'delete', [])
            ->andReturn($this->makeResponse(200, []));

        $this->assertSame($ctcFailure, $service->setup($this->frenchSetupData()));
    }

    public function testSelfHostedRequestRejectsAnInvalidDerivedSiren(): void
    {
        $data = $this->frenchSetupData();
        $data['vat_number'] = 'FR00123456789';
        $request = StoreEntityRequestSelf::create('/api/einvoice/peppol/setup', 'POST', $data);
        $validator = Validator::make($data, $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('vat_number', $validator->errors()->toArray());
    }

    private function storecoveMock(): Storecove
    {
        $storecove = Mockery::mock(Storecove::class)->makePartial();
        $storecove->router = new StorecoveRouter();

        return $storecove;
    }

    /**
     * @return array<string, mixed>
     */
    private function frenchSetupData(): array
    {
        return [
            'country' => 'FR',
            'classification' => 'business',
            'id_number' => '',
            'vat_number' => 'FR44732829320',
            'tenant_id' => 'fr-company',
            'party_name' => 'French Test Company',
            'tax_registered' => true,
            'city' => 'Paris',
            'line1' => '1 Rue de Test',
            'zip' => '75001',
            'acts_as_sender' => true,
            'acts_as_receiver' => true,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function peppolFranceNetworks(): array
    {
        return [
            [
                'name' => 'peppol',
                'sub_networks' => ['main', 'france'],
            ],
        ];
    }

    private function makeResponse(int $status, array $body): Response
    {
        return new Response(
            new PsrResponse($status, ['Content-Type' => 'application/json'], json_encode($body, JSON_THROW_ON_ERROR))
        );
    }
}
