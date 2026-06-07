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

namespace Tests\Feature\Search;

use App\Factory\CompanyUserFactory;
use App\Http\Controllers\SearchController;
use App\Models\CompanyToken;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\CoversMethod;
use Tests\MockAccountData;
use Tests\TestCase;

#[CoversMethod(SearchController::class, 'permissionFilter')]
class SearchPermissionFilterTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    /** Every index searched by the controller. */
    private const ALL_INDICES = [
        'clients', 'invoices', 'client_contacts', 'quotes', 'expenses', 'credits',
        'recurring_invoices', 'vendors', 'vendor_contacts', 'purchase_orders', 'projects', 'tasks',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();
    }

    private function buildFilter(User $user): array
    {
        $controller = new SearchController();

        $method = new \ReflectionMethod(SearchController::class, 'permissionFilter');

        return $method->invoke($controller, $user, $this->company);
    }

    private function makeRestrictedUser(string $permissions): User
    {
        $user = User::factory()->create([
            'account_id' => $this->account->id,
            'confirmation_code' => uniqid('code', true),
            'email' => uniqid('user', true) . '@example.com',
        ]);

        $cu = CompanyUserFactory::create($user->id, $this->company->id, $this->account->id);
        $cu->is_owner = false;
        $cu->is_admin = false;
        $cu->is_locked = false;
        $cu->permissions = $permissions;
        $cu->save();

        $token = new CompanyToken();
        $token->user_id = $user->id;
        $token->company_id = $this->company->id;
        $token->account_id = $this->account->id;
        $token->name = 'restricted search token';
        $token->token = \Illuminate\Support\Str::random(64);
        $token->is_system = true;
        $token->save();

        return $user->fresh();
    }

    /**
     * Locate the unrestricted `terms` bucket by structure, not by position.
     *
     * @return string[]
     */
    private function unrestrictedIndices(array $filter): array
    {
        foreach ($filter['bool']['should'] ?? [] as $clause) {
            if (isset($clause['terms']['_index'])) {
                return $clause['terms']['_index'];
            }
        }

        return [];
    }

    /**
     * Locate the restricted ownership-scoped bucket by structure, not by position.
     *
     * @return array<string, mixed>|null
     */
    private function restrictedClause(array $filter): ?array
    {
        foreach ($filter['bool']['should'] ?? [] as $clause) {
            if (isset($clause['bool'])) {
                return $clause['bool'];
            }
        }

        return null;
    }

    /**
     * @return string[]
     */
    private function restrictedIndices(array $filter): array
    {
        return $this->restrictedClause($filter)['must'][0]['terms']['_index'] ?? [];
    }

    public function testAdminFilterIsCompanyScopedOnly(): void
    {
        $filter = $this->buildFilter($this->user);

        $this->assertSame([
            [
                'match' => [
                    'company_key' => $this->company->company_key,
                ],
            ],
        ], $filter);
    }

    public function testRestrictedUserAlwaysEnforcesCompanyKey(): void
    {
        $user = $this->makeRestrictedUser('[]');

        $filter = $this->buildFilter($user);

        $this->assertArrayHasKey('bool', $filter);
        $this->assertSame(1, $filter['bool']['minimum_should_match']);
        // company_key is the sole, mandatory top-level must — never bypassable.
        $this->assertCount(1, $filter['bool']['must']);
        $this->assertSame(
            $this->company->company_key,
            $filter['bool']['must'][0]['match']['company_key']
        );
    }

    public function testRestrictedUserWithNoPermissionsScopesEveryIndexToOwnership(): void
    {
        $user = $this->makeRestrictedUser('[]');

        $filter = $this->buildFilter($user);

        // No view permissions => no unrestricted indices, single restricted clause.
        $this->assertCount(1, $filter['bool']['should']);
        $this->assertEmpty($this->unrestrictedIndices($filter));

        $restrictedIndices = $this->restrictedIndices($filter);

        // Every index — including the contact indices — must be ownership-scoped.
        foreach (self::ALL_INDICES as $index) {
            $this->assertContains($index, $restrictedIndices, "{$index} should be ownership-scoped");
        }

        $ownership = $this->restrictedClause($filter)['must'][1]['bool'];

        $this->assertSame(1, $ownership['minimum_should_match']);
        $this->assertSame((string) $user->id, $ownership['should'][0]['term']['user_id']);
        $this->assertSame((string) $user->id, $ownership['should'][1]['term']['assigned_user_id']);
    }

    public function testPartialPermissionSplitsIndicesIntoUnrestrictedAndRestricted(): void
    {
        // view_invoice grants full invoice visibility; everything else stays ownership-scoped.
        $user = $this->makeRestrictedUser('["view_invoice"]');

        $filter = $this->buildFilter($user);

        $unrestricted = $this->unrestrictedIndices($filter);
        $this->assertContains('invoices', $unrestricted);
        $this->assertNotContains('clients', $unrestricted);

        $restricted = $this->restrictedIndices($filter);
        $this->assertContains('clients', $restricted);
        $this->assertNotContains('invoices', $restricted);
    }

    public function testViewClientGivesFullClientsWhileInvoicesStayOwnershipScoped(): void
    {
        // view_client grants full client + client_contact visibility;
        // invoices (no view_invoice) must remain ownership-scoped and unaffected.
        $user = $this->makeRestrictedUser('["view_client"]');

        $filter = $this->buildFilter($user);

        $unrestricted = $this->unrestrictedIndices($filter);
        $this->assertContains('clients', $unrestricted);
        $this->assertContains('client_contacts', $unrestricted);
        $this->assertNotContains('invoices', $unrestricted);

        $restricted = $this->restrictedIndices($filter);
        $this->assertContains('invoices', $restricted);
        $this->assertNotContains('clients', $restricted);
    }

    public function testViewVendorGivesFullVendorsAndVendorContacts(): void
    {
        // Vendor-side mirror of view_client: vendor_contacts follow the parent vendor permission.
        $user = $this->makeRestrictedUser('["view_vendor"]');

        $filter = $this->buildFilter($user);

        $unrestricted = $this->unrestrictedIndices($filter);
        $this->assertContains('vendors', $unrestricted);
        $this->assertContains('vendor_contacts', $unrestricted);

        $restricted = $this->restrictedIndices($filter);
        $this->assertNotContains('vendors', $restricted);
        $this->assertNotContains('vendor_contacts', $restricted);
    }

    public function testViewAllGrantsFullAccessToEveryIndex(): void
    {
        // A non-admin holding view_all should see every index unrestricted (no ownership clause).
        $user = $this->makeRestrictedUser('["view_all"]');

        $filter = $this->buildFilter($user);

        $this->assertCount(1, $filter['bool']['should']);
        $this->assertNull($this->restrictedClause($filter));

        $unrestricted = $this->unrestrictedIndices($filter);
        foreach (self::ALL_INDICES as $index) {
            $this->assertContains($index, $unrestricted, "{$index} should be unrestricted under view_all");
        }

        // company_key still mandatory.
        $this->assertSame($this->company->company_key, $filter['bool']['must'][0]['match']['company_key']);
    }

    public function testEditAllGrantsFullAccessToEveryIndex(): void
    {
        // edit_all implies view_all for every entity via hasPermission().
        $user = $this->makeRestrictedUser('["edit_all"]');

        $filter = $this->buildFilter($user);

        $this->assertCount(1, $filter['bool']['should']);
        $this->assertNull($this->restrictedClause($filter));

        $unrestricted = $this->unrestrictedIndices($filter);
        foreach (self::ALL_INDICES as $index) {
            $this->assertContains($index, $unrestricted, "{$index} should be unrestricted under edit_all");
        }
    }

    public function testEditEntityImpliesViewForThatIndexOnly(): void
    {
        // edit_invoice implies view_invoice; clients (no edit/view) stay ownership-scoped.
        $user = $this->makeRestrictedUser('["edit_invoice"]');

        $filter = $this->buildFilter($user);

        $unrestricted = $this->unrestrictedIndices($filter);
        $this->assertContains('invoices', $unrestricted);
        $this->assertNotContains('clients', $unrestricted);

        $restricted = $this->restrictedIndices($filter);
        $this->assertContains('clients', $restricted);
        $this->assertNotContains('invoices', $restricted);
        // edit_invoice must NOT leak into the recurring_invoice entity.
        $this->assertContains('recurring_invoices', $restricted);
    }

    public function testViewRecurringInvoiceUnrestrictsRecurringInvoicesOnly(): void
    {
        // Multi-underscore entity: view_recurring_invoice maps to the recurring_invoices index
        // and must NOT unrestrict the plain invoices index.
        $user = $this->makeRestrictedUser('["view_recurring_invoice"]');

        $filter = $this->buildFilter($user);

        $unrestricted = $this->unrestrictedIndices($filter);
        $this->assertContains('recurring_invoices', $unrestricted);
        $this->assertNotContains('invoices', $unrestricted);

        $restricted = $this->restrictedIndices($filter);
        $this->assertContains('invoices', $restricted);
        $this->assertNotContains('recurring_invoices', $restricted);
    }
}
