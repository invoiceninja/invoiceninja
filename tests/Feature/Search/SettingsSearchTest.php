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

use App\DataProviders\SettingsSearchMap;
use App\Factory\CompanyUserFactory;
use App\Http\Controllers\SearchController;
use App\Models\CompanyToken;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use Tests\MockAccountData;
use Tests\TestCase;

#[CoversClass(SettingsSearchMap::class)]
#[CoversMethod(SearchController::class, 'settingsMap')]
class SettingsSearchTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        Cache::flush();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function settingsFor(User $user): array
    {
        $controller = new SearchController();

        $method = new \ReflectionMethod(SearchController::class, 'settingsMap');

        return $method->invoke($controller, $user);
    }

    private function paths(array $settings): array
    {
        return array_column($settings, 'path');
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

    public function testCatalogueEntriesAreWellFormed(): void
    {
        foreach (SettingsSearchMap::all() as $entry) {
            $this->assertArrayHasKey('path', $entry);
            $this->assertStringStartsWith('/settings', $entry['path']);

            $this->assertNotEmpty($entry['label']);
            $this->assertNotEmpty(ctrans("texts.{$entry['label']}"));

            if ($entry['section'] !== null) {
                $this->assertNotEmpty(ctrans("texts.{$entry['section']}"));
            }

            $this->assertContains($entry['permission'], [null, SettingsSearchMap::ADMIN]);
            $this->assertContains($entry['scope'], [
                SettingsSearchMap::SCOPE_ALL,
                SettingsSearchMap::SCOPE_HOSTED,
                SettingsSearchMap::SCOPE_SELFHOST,
            ]);
        }
    }

    public function testCatalogueEntriesAreUnique(): void
    {
        // Paths may be intentionally aliased (e.g. "Tax Settings" and "Tax Rates"
        // both resolve to /settings/tax_settings), but no path+label row may repeat.
        $keys = array_map(
            fn (array $entry): string => $entry['path'] . '|' . $entry['label'],
            SettingsSearchMap::all()
        );

        $this->assertSame(array_unique($keys), $keys);
    }

    public function testListSettingsDoNotPointAtIndexlessRoutes(): void
    {
        // These route groups only define create/:id/edit children in the UI; their
        // lists render inside the parent settings page, so the catalogue must never
        // navigate to the bare (index-less) path.
        $paths = array_column(SettingsSearchMap::all(), 'path');

        $this->assertNotContains('/settings/tax_rates', $paths);
        $this->assertNotContains('/settings/task_statuses', $paths);
        $this->assertNotContains('/settings/expense_categories', $paths);
        $this->assertNotContains('/settings/integrations', $paths);
        $this->assertNotContains('/settings/company_details/details', $paths);
        $this->assertNotContains('/settings/backup_restore/backup', $paths);
    }

    public function testQuickbooksIsDiscoverableViaIntegrations(): void
    {
        $entry = collect(SettingsSearchMap::all())
            ->first(fn (array $e): bool => str_contains($e['keywords'], 'quickbooks'));

        $this->assertNotNull($entry);
        $this->assertSame('/settings/account_management/integrations', $entry['path']);
    }

    public function testAdminReceivesAdminOnlyDestinations(): void
    {
        $settings = $this->settingsFor($this->user);

        $paths = $this->paths($settings);

        $this->assertContains('/settings/users', $paths);
        $this->assertContains('/settings/company_details', $paths);
        $this->assertContains('/settings/account_management/danger_zone', $paths);
        $this->assertContains('/settings/user_details', $paths);
    }

    public function testRestrictedUserOnlyReceivesUngatedDestinations(): void
    {
        $user = $this->makeRestrictedUser('[]');

        $paths = $this->paths($this->settingsFor($user));

        // Profile settings remain available to every user...
        $this->assertContains('/settings/user_details', $paths);
        $this->assertContains('/settings/user_details/enable_two_factor', $paths);

        // ...but admin-gated destinations must not leak.
        $this->assertNotContains('/settings/users', $paths);
        $this->assertNotContains('/settings/company_details', $paths);
        $this->assertNotContains('/settings/system_logs', $paths);

        foreach ($paths as $path) {
            $this->assertStringStartsWith('/settings/user_details', $path);
        }
    }

    public function testResultsNeverLeakInternalGatingFields(): void
    {
        $settings = $this->settingsFor($this->user);

        foreach ($settings as $entry) {
            $this->assertArrayNotHasKey('permission', $entry);
            $this->assertArrayNotHasKey('scope', $entry);

            $this->assertArrayHasKey('name', $entry);
            $this->assertArrayHasKey('heading', $entry);
            $this->assertArrayHasKey('keywords', $entry);
            $this->assertArrayHasKey('path', $entry);
            $this->assertNotEmpty($entry['heading']);
        }
    }

    public function testKeywordsIncludeHeadingForNestedDestinations(): void
    {
        $settings = $this->settingsFor($this->user);

        $address = collect($settings)->firstWhere('path', '/settings/company_details/address');

        $this->assertNotNull($address);
        $this->assertStringContainsString($address['heading'], $address['keywords']);
        $this->assertStringContainsStringIgnoringCase('street', $address['keywords']);
    }

    public function testResultsAreSortedByName(): void
    {
        $names = array_column($this->settingsFor($this->user), 'name');

        $sorted = $names;
        usort($sorted, fn ($a, $b) => strcasecmp($a, $b));

        $this->assertSame($sorted, $names);
    }

    public function testHostedScopedDestinationsAreGatedByDeployment(): void
    {
        config(['ninja.environment' => 'hosted']);
        $hosted = $this->paths($this->settingsFor($this->user));
        $this->assertContains('/settings/account_management/referral_program', $hosted);

        config(['ninja.environment' => 'selfhost']);
        $selfhost = $this->paths($this->settingsFor($this->user));
        $this->assertNotContains('/settings/account_management/referral_program', $selfhost);
    }
}
