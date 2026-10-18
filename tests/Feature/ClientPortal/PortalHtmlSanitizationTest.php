<?php

namespace Tests\Feature\ClientPortal;

use App\Factory\GroupSettingFactory;
use App\Models\GroupSetting;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\MockAccountData;
use Tests\TestCase;

class PortalHtmlSanitizationTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    private const HTML = '<span>Portal branding</span><style id="brand" onload="window.portalProbe = true">.portal-brand { color: green; }</style><script>window.portalProbe = true</script>';
    private const CLEAN = '<span>Portal branding</span><style>.portal-brand { color: green; }</style>';

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeTestData();
        config(['ninja.environment' => 'hosted', 'ninja.production' => true, 'ninja.disable_purify_html' => true]);
    }

    #[DataProvider('writePaths')]
    public function test_api_sanitizes_header_and_footer_only_when_hosted(string $entity, string $method, string $environment): void
    {
        config(['ninja.environment' => $environment]);
        $expected = $environment === 'hosted' ? self::CLEAN : self::HTML;

        $id = match ($entity) {
            'companies' => $this->company->hashed_id,
            'clients' => $this->client->hashed_id,
            'group_settings' => $this->makeGroup()->hashed_id,
        };
        $url = '/api/v1/' . $entity . ($method === 'PUT' ? '/' . $id : '');

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->json($method, $url, [
            'name' => 'Portal sanitization',
            'settings' => [
                // Company updates without this key take the logo-only path.
                'company_logo' => '',
                'currency_id' => '1',
                'portal_custom_head' => self::HTML,
                'portal_custom_footer' => self::HTML,
            ],
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.settings.portal_custom_head', $expected);
        $response->assertJsonPath('data.settings.portal_custom_footer', $expected);
    }

    public static function writePaths(): array
    {
        return [
            ['companies', 'PUT', 'hosted'],
            ['companies', 'PUT', 'selfhost'],
            ['clients', 'POST', 'hosted'],
            ['clients', 'POST', 'selfhost'],
            ['clients', 'PUT', 'hosted'],
            ['clients', 'PUT', 'selfhost'],
            ['group_settings', 'POST', 'hosted'],
            ['group_settings', 'POST', 'selfhost'],
            ['group_settings', 'PUT', 'hosted'],
            ['group_settings', 'PUT', 'selfhost'],
        ];
    }

    #[DataProvider('settingScopes')]
    public function test_stored_html_is_sanitized_only_when_hosted_after_inheritance(string $scope, string $environment): void
    {
        config(['ninja.environment' => $environment]);
        $expected = $environment === 'hosted' ? self::CLEAN : self::HTML;

        // MockAccountData's default group overrides company fields with empty values.
        $this->client->group_settings_id = null;
        $this->client->save();

        $entity = match ($scope) {
            'company' => $this->company,
            'client' => $this->client,
            'group' => $this->makeGroup(),
        };

        if ($scope === 'group') {
            $this->client->group_settings_id = $entity->id;
            $this->client->save();
        }

        // Bypass repository sanitization to represent already-stored content.
        $settings = $entity->settings;
        $settings->portal_custom_head = self::HTML;
        $settings->portal_custom_footer = self::HTML;
        $entity->settings = $settings;
        $entity->save();

        $this->actingAs($this->client->contacts()->first(), 'contact');
        $response = $this->get('/client/invoices');

        $response->assertOk();
        $this->assertSame(2, substr_count($response->getContent(), $expected));

        if ($environment === 'hosted') {
            $response->assertDontSee('window.portalProbe', false);
            $response->assertDontSee('id="brand"', false);
        }
    }

    public static function settingScopes(): array
    {
        return [
            ['company', 'hosted'], ['company', 'selfhost'],
            ['group', 'hosted'], ['group', 'selfhost'],
            ['client', 'hosted'], ['client', 'selfhost'],
        ];
    }

    private function makeGroup(): GroupSetting
    {
        $group = GroupSettingFactory::create($this->company->id, $this->user->id);
        $group->name = 'Portal group ' . uniqid();
        $group->save();

        return $group;
    }
}
