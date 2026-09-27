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

namespace Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Testing\TestResponse;
use League\OpenAPIValidation\PSR7\Exception\ValidationFailed;
use League\OpenAPIValidation\PSR7\OperationAddress;
use League\OpenAPIValidation\PSR7\ValidatorBuilder;
use League\OpenAPIValidation\Schema\Exception\SchemaMismatch;
use Nyholm\Psr7\Factory\Psr17Factory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * Validates real API responses against openapi/api-docs.yaml, so the
 * published spec can't silently drift from what the API returns.
 */
class OpenApiContractTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        Model::reguard();
    }

    public function testStoreClient(): void
    {
        $this->assertMatchesSpec('post', '/api/v1/clients', $this->api()->postJson('/api/v1/clients', ['name' => 'Contract Test Client']));
    }

    private function api(): static
    {
        return $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ]);
    }

    private function assertMatchesSpec(string $method, string $path, TestResponse $response): void
    {
        $response->assertSuccessful();

        $factory = new Psr17Factory();
        $psr = (new PsrHttpFactory($factory, $factory, $factory, $factory))->createResponse($response->baseResponse);

        try {
            (new ValidatorBuilder())
                ->fromYamlFile(base_path('openapi/api-docs.yaml'))
                ->getResponseValidator()
                ->validate(new OperationAddress($path, $method), $psr);
        } catch (ValidationFailed $e) {
            $reasons = [];
            for ($ex = $e; $ex; $ex = $ex->getPrevious()) {
                $field = $ex instanceof SchemaMismatch && $ex->dataBreadCrumb() ? ' (at '.implode('.', $ex->dataBreadCrumb()->buildChain()).')' : '';
                $reasons[] = $ex->getMessage().$field;
            }

            $this->fail(strtoupper($method)." {$path} does not match the spec:\n- ".implode("\n- ", $reasons));
        }

        $this->addToAssertionCount(1);
    }
}
