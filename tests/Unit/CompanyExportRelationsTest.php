<?php

namespace Tests\Unit;

use App\Jobs\Company\CompanyExport;
use App\Models\Company;
use App\Models\CompanyToken;
use App\Models\User;
use Tests\TestCase;
use ReflectionMethod;

class CompanyExportRelationsTest extends TestCase
{
    public function test_export_models_are_cloned_without_loaded_relations(): void
    {
        $company = new Company();
        $user = new User();
        $token = new CompanyToken();
        $token->setRelation('company', $company);
        $token->setRelation('user', $user);

        $job = new CompanyExport($company, $user, 'export-hash');
        $method = new ReflectionMethod($job, 'withoutRelations');

        /** @var CompanyToken $export_token */
        $export_token = $method->invoke($job, $token);

        $this->assertNotSame($token, $export_token);
        $this->assertSame([], $export_token->getRelations());
        $this->assertArrayNotHasKey('company', $export_token->toArray());
        $this->assertArrayNotHasKey('user', $export_token->toArray());
        $this->assertTrue($token->relationLoaded('company'));
        $this->assertTrue($token->relationLoaded('user'));
    }
}