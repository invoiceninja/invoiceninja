<?php

namespace Tests\Feature\Integration\Einvoice\Storecove;

use GuzzleHttp\Psr7\Response as PsrResponse;
use App\Services\EDocument\Gateway\Storecove\Storecove;
use Tests\TestCase;
use Mockery;
use Illuminate\Http\Client\Response;
use App\Services\EDocument\Gateway\Storecove\LegalEntityService;

class LegalEntityServiceTest extends TestCase
{
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
            ->withArgs(fn (string $uri, string $verb = '', array $payload = []) => $uri === "legal_entities/{$legalEntityId}/peppol_identifiers");

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

    private function makeResponse(int $status, array $body): Response
    {
        return new Response(
            new PsrResponse($status, ['Content-Type' => 'application/json'], json_encode($body, JSON_THROW_ON_ERROR))
        );
    }
}
