<?php

namespace Tests\Unit\Company;

use App\Jobs\Company\CompanyImport;
use Exception;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

class CompanyImportTransformIdTest extends TestCase
{
    public function testMissingNullableRelationReturnsNull(): void
    {
        $import = $this->makeImport();

        $this->assertNull($this->transformId($import, 'invoices', 'missing-invoice', true));
    }

    public function testMissingRequiredRelationThrowsException(): void
    {
        $import = $this->makeImport();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Missing clients key: missing-client');

        $this->transformId($import, 'clients', 'missing-client');
    }

    private function makeImport(): CompanyImport
    {
        $reflection = new ReflectionClass(CompanyImport::class);

        /** @var CompanyImport $import */
        $import = $reflection->newInstanceWithoutConstructor();
        $import->ids = [
            'clients' => [],
            'invoices' => [],
        ];

        return $import;
    }

    private function transformId(
        CompanyImport $import,
        string $resource,
        string $old,
        bool $allow_missing = false
    ): ?int {
        $method = new ReflectionMethod(CompanyImport::class, 'transformId');
        $method->setAccessible(true);

        return $method->invoke($import, $resource, $old, $allow_missing);
    }
}
