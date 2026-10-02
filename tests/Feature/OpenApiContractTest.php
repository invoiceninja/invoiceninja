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
use PHPUnit\Framework\Attributes\DataProvider;
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

        // MockAccountData skips the API's defaults (e.g. settings.currency_id), so
        // list rows filter down to a client created through the API instead.
        $this->apiRequest('post', '/api/v1/clients', ['name' => 'Contract Test Client'])->assertSuccessful();
    }

    /**
     * [method, spec path, MockAccountData property whose hashed_id fills {id}, request body (query string for GET)].
     * Plain data only: phpunit.xml runs tests in separate processes, so rows get serialized.
     */
    public static function operations(): array
    {
        return [
            'list clients' => ['get', '/api/v1/clients', null, ['name' => 'Contract Test Client']],
            'store client' => ['post', '/api/v1/clients', null, ['name' => 'Contract Test Client']],
        ];
    }

    #[DataProvider('operations')]
    public function testResponseMatchesSpec(string $method, string $path, ?string $entity, array $body = []): void
    {
        $url = $entity ? str_replace('{id}', $this->{$entity}->hashed_id, $path) : $path;

        $this->assertMatchesSpec($method, $path, $this->apiRequest($method, $url, $body));
    }

    private function apiRequest(string $method, string $url, array $body = []): TestResponse
    {
        if ($method === 'get' && $body) {
            [$url, $body] = [$url.'?'.http_build_query($body), []];
        }

        return $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->json(strtoupper($method), $url, $body);
    }

    private function assertMatchesSpec(string $method, string $path, TestResponse $response): void
    {
        $response->assertSuccessful();
        // An empty data array validates against any schema, so it would pass without checking anything.
        $this->assertNotEmpty($response->json('data'), strtoupper($method)." {$path} returned no data to validate");

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
