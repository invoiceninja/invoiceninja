<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Feature\Export;

use App\Export\CSV\BaseExport;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\RelationNotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\MockAccountData;
use Tests\TestCase;

class TemplateExportRelationsTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();
    }

    public function testUnconditionalTagsEagerLoadFailsForClientContacts(): void
    {
        $this->assertFalse(method_exists(new ClientContact(), 'tags'));
        $this->expectException(RelationNotFoundException::class);

        ClientContact::query()
            ->where('id', $this->contact->id)
            ->with('tags')
            ->get();
    }

    public function testTemplateEntityLoaderSkipsTagsForModelsWithoutTheRelation(): void
    {
        $entities = (new TestableTemplateExport($this->company))->entities(
            ClientContact::query()->where('id', $this->contact->id)
        );

        $this->assertCount(1, $entities);
        $this->assertSame($this->contact->id, $entities->first()->id);
    }

    public function testTemplateEntityLoaderStillEagerLoadsTagsWhenSupported(): void
    {
        $entities = (new TestableTemplateExport($this->company))->entities(
            Client::query()->where('id', $this->client->id)
        );

        $this->assertTrue($entities->first()->relationLoaded('tags'));
    }
}

class TestableTemplateExport extends BaseExport
{
    public function __construct(Company $company)
    {
        $this->company = $company;
        $this->input = [];
    }

    public function entities(Builder $query): Collection
    {
        return $this->templateEntities($query);
    }
}
