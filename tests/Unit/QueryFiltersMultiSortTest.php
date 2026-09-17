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

namespace Tests\Unit;

use App\Filters\QueryFilters;
use App\Models\PaymentTerm;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Tests\TestCase;

class QueryFiltersMultiSortTest extends TestCase
{
    /**
     * @return array<int, array{column: string|null, direction: string|null}>
     */
    private function ordersFrom(Builder $builder): array
    {
        return collect($builder->getQuery()->orders)
            ->map(function (array $order): array {
                return [
                    'column' => $order['column'] ?? null,
                    'direction' => $order['direction'] ?? null,
                ];
            })
            ->all();
    }

    public function testItChainsSortFiltersPassedAsAnArray(): void
    {
        $filters = new MultiSortQueryFilters(Request::create('/', 'GET', [
            'sort' => [
                'name|asc',
                'num_days|desc',
            ],
        ]));

        $builder = $filters->apply(PaymentTerm::query());

        $this->assertSame(['name|asc', 'num_days|desc'], $filters->appliedSorts);
        $this->assertSame([
            [
                'column' => 'name',
                'direction' => 'asc',
            ],
            [
                'column' => 'num_days',
                'direction' => 'desc',
            ],
        ], $this->ordersFrom($builder));
    }

    public function testItIgnoresEmptyAndNonStringSortFiltersInAnArray(): void
    {
        $filters = new MultiSortQueryFilters(Request::create('/', 'GET', [
            'sort' => [
                'name|asc',
                '',
                null,
                ['num_days|asc'],
                'num_days|desc',
            ],
        ]));

        $builder = $filters->apply(PaymentTerm::query());

        $this->assertSame(['name|asc', 'num_days|desc'], $filters->appliedSorts);
        $this->assertSame([
            [
                'column' => 'name',
                'direction' => 'asc',
            ],
            [
                'column' => 'num_days',
                'direction' => 'desc',
            ],
        ], $this->ordersFrom($builder));
    }

    public function testItStillAppliesSingleSortFilters(): void
    {
        $filters = new MultiSortQueryFilters(Request::create('/', 'GET', [
            'sort' => 'name|desc',
        ]));

        $builder = $filters->apply(PaymentTerm::query());

        $this->assertSame(['name|desc'], $filters->appliedSorts);
        $this->assertSame([
            [
                'column' => 'name',
                'direction' => 'desc',
            ],
        ], $this->ordersFrom($builder));
    }

    public function testItAddsADefaultOrderWhenNoFilterAppliesAnOrder(): void
    {
        $filters = new MultiSortQueryFilters(Request::create('/', 'GET'));

        $builder = $filters->apply(PaymentTerm::query());

        $this->assertSame([
            [
                'column' => 'payment_terms.id',
                'direction' => 'desc',
            ],
        ], $this->ordersFrom($builder));
    }

    public function testItAddsADefaultOrderAfterNonSortingFiltersAreApplied(): void
    {
        $filters = new MultiSortQueryFilters(Request::create('/', 'GET', [
            'name' => 'Net 30',
        ]));

        $builder = $filters->apply(PaymentTerm::query());

        $this->assertSame(['Net 30'], $filters->appliedNameFilters);
        $this->assertSame([
            [
                'column' => 'payment_terms.id',
                'direction' => 'desc',
            ],
        ], $this->ordersFrom($builder));
    }

    public function testItDoesNotAppendADefaultOrderWhenTheBuilderAlreadyHasAnOrder(): void
    {
        $filters = new MultiSortQueryFilters(Request::create('/', 'GET'));

        $builder = $filters->apply(PaymentTerm::query()->orderBy('name'));

        $this->assertSame([
            [
                'column' => 'name',
                'direction' => 'asc',
            ],
        ], $this->ordersFrom($builder));
    }

    public function testItCleansDecorativeFilterTerms(): void
    {
        $filters = new CleanFilterStringQueryFilters(Request::create('/', 'GET'));

        $this->assertSame(
            '微信：homei_living Richmond Showroom XXX-XXX-XXXX',
            $filters->clean('📱 微信：homei_living 📍 Richmond Showroom 📞 XXX-XXX-XXXX')
        );

        $this->assertSame('', $filters->clean('📱📍📞'));
    }

    public function testItPreservesSupportedSearchPunctuation(): void
    {
        $filters = new CleanFilterStringQueryFilters(Request::create('/', 'GET'));

        $this->assertSame(
            "ACME: unit_42/West + sales@example.test #PO-123 O'Reilly 50%",
            $filters->clean("ACME: unit_42/West + sales@example.test #PO-123 O'Reilly 50%")
        );
    }

    public function testItRemovesCharactersThatCanBreakStringComparisons(): void
    {
        $filters = new CleanFilterStringQueryFilters(Request::create('/', 'GET'));

        $this->assertSame('Alpha Beta Gamma Delta Omega', $filters->clean("Alpha\0Beta\u{0085}Gamma📱Delta\nOmega"));
    }

    public function testItScrubsMalformedUtf8BeforeFiltering(): void
    {
        $filters = new CleanFilterStringQueryFilters(Request::create('/', 'GET'));

        $normalized_filter = $filters->clean("Valid \xF0\x28\x8C\x28 End");

        $this->assertTrue(mb_check_encoding($normalized_filter, 'UTF-8'));
        $this->assertStringContainsString('Valid', $normalized_filter);
        $this->assertStringContainsString('End', $normalized_filter);
    }

    public function testItCleansFilterRequestValueBeforeDispatch(): void
    {
        $filters = new FilterDispatchQueryFilters(Request::create('/', 'GET', [
            'filter' => '📱 微信：homei_living 📍 Richmond Showroom 📞 XXX-XXX-XXXX',
        ]));

        $builder = $filters->apply(PaymentTerm::query());

        $this->assertSame(['微信：homei_living Richmond Showroom XXX-XXX-XXXX'], $filters->appliedFilters);
        $this->assertContains('%微信：homei_living Richmond Showroom XXX-XXX-XXXX%', $builder->getBindings());
    }

    public function testItReturnsNoRowsWhenFilterCleansToEmptyBeforeDispatch(): void
    {
        $filters = new FilterDispatchQueryFilters(Request::create('/', 'GET', [
            'filter' => '📱📍📞',
        ]));

        $builder = $filters->apply(PaymentTerm::query());

        $this->assertSame([], $filters->appliedFilters);
        $this->assertStringContainsString('0 = 1', $builder->toSql());
    }

    public function testItDoesNotDispatchNonPublicHelperMethodsFromRequestKeys(): void
    {
        $filters = new MultiSortQueryFilters(Request::create('/', 'GET', [
            'cleanFilterString' => ['x'],
        ]));

        $builder = $filters->apply(PaymentTerm::query());

        $this->assertSame([
            [
                'column' => 'payment_terms.id',
                'direction' => 'desc',
            ],
        ], $this->ordersFrom($builder));
    }
}

class MultiSortQueryFilters extends QueryFilters
{
    /**
     * @var list<string>
     */
    public array $appliedSorts = [];

    /**
     * @var list<string>
     */
    public array $appliedNameFilters = [];

    public function entityFilter(): Builder
    {
        return $this->builder;
    }

    public function name(string $name = ''): Builder
    {
        $this->appliedNameFilters[] = $name;

        return $this->builder->where('name', $name);
    }

    public function sort(string $sort = ''): Builder
    {
        $this->appliedSorts[] = $sort;

        $sortColumn = explode('|', $sort);
        $direction = ($sortColumn[1] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        return $this->builder->orderBy($sortColumn[0], $direction);
    }
}

class CleanFilterStringQueryFilters extends QueryFilters
{
    public function clean(string $filter): string
    {
        return $this->cleanFilterString($filter);
    }
}

class FilterDispatchQueryFilters extends QueryFilters
{
    /**
     * @var list<string>
     */
    public array $appliedFilters = [];

    public function entityFilter(): Builder
    {
        return $this->builder;
    }

    public function filter(string $filter = ''): Builder
    {
        $this->appliedFilters[] = $filter;

        return $this->builder->where('name', 'like', '%' . $filter . '%');
    }
}
