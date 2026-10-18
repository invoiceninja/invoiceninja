<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Feature;

use App\DataMapper\Settings\SettingsData;
use App\Models\GroupSetting;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Session;
use Tests\MockAccountData;
use Tests\TestCase;

class GroupSettingTest extends TestCase
{
    use MakesHash;
    use MockAccountData;
    protected function setUp(): void
    {
        parent::setUp();

        Session::start();
        Model::reguard();

        $this->makeTestData();
    }


    public function testGroupUpdateCastsNullSettingsInsteadOfUnsetting(): void
    {
        $company_settings = $this->company->settings;
        $company_settings->invoice_terms = 'COMPANY_INVOICE_TERMS';
        $company_settings->auto_archive_invoice = true;
        $this->company->settings = $company_settings;
        $this->company->save();

        $create = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/group_settings', [
            'name' => 'Null Settings Group',
            'settings' => [
                'currency_id' => '1',
                'invoice_terms' => 'GROUP_INVOICE_TERMS',
                'auto_archive_invoice' => false,
            ],
        ]);

        $create->assertStatus(200);

        $group_id = $create->json('data.id');
        $this->assertSame('GROUP_INVOICE_TERMS', $create->json('data.settings.invoice_terms'));
        $this->assertFalse($create->json('data.settings.auto_archive_invoice'));

        $update = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/group_settings/'.$group_id, [
            'settings' => [
                'currency_id' => '1',
                'invoice_terms' => null,
                'auto_archive_invoice' => null,
            ],
        ]);

        $update->assertStatus(200);

        $settings = $update->json('data.settings');
        $group = GroupSetting::find($this->decodePrimaryKey($group_id));

        $this->assertArrayHasKey('invoice_terms', $settings);
        $this->assertArrayHasKey('auto_archive_invoice', $settings);
        $this->assertSame('', $settings['invoice_terms']);
        $this->assertFalse($settings['auto_archive_invoice']);

        $this->assertTrue(property_exists($group->settings, 'invoice_terms'));
        $this->assertTrue(property_exists($group->settings, 'auto_archive_invoice'));
        $this->assertSame('', $group->settings->invoice_terms);
        $this->assertFalse($group->settings->auto_archive_invoice);

        $this->client->group_settings_id = $group->id;
        $this->client->save();
        $this->client->refresh();

        $this->assertSame('', $this->client->getSetting('invoice_terms'));
        $this->assertFalse($this->client->getSetting('auto_archive_invoice'));
    }

    public function testCreateBlankGroupSetting()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->getJson('/api/v1/group_settings/create');

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertSame($this->encodePrimaryKey(0), $arr['data']['id']);
    }

    public function testCompanyOnlySettingsAreUnset(): void
    {
        $settings = new \stdClass();
        $settings->pdf_variables = 'xx';
        $settings->translations = (object) ['invoice' => 'Group Translation'];
        $settings->currency_id = '2';

        $data = [
            'name' => 'testX',
            'settings' => $settings,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/group_settings', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertArrayNotHasKey('pdf_variables', $arr['data']['settings']);
        $this->assertArrayNotHasKey('translations', $arr['data']['settings']);
        $this->assertSame('2', $arr['data']['settings']['currency_id']);

        $data = [
            'name' => 'testX',
            'settings' => $settings,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/group_settings/'.$arr['data']['id'], $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertArrayNotHasKey('pdf_variables', $arr['data']['settings']);
        $this->assertArrayNotHasKey('translations', $arr['data']['settings']);
        $this->assertSame('2', $arr['data']['settings']['currency_id']);

    }

    public function testCastingMagic()
    {

        $settings = new \stdClass();
        $settings->currency_id = '1';
        $settings->tax_name1 = '';
        $settings->tax_rate1 = 0;
        $s = new SettingsData();
        $settings = $s->cast($settings)->toObject();

        $this->assertEquals("", $settings->tax_name1);
        $settings = null;

        $settings = new \stdClass();
        $settings->currency_id = '1';
        $settings->tax_name1 = "1";
        $settings->tax_rate1 = 0;

        $settings = $s->cast($settings)->toObject();

        $this->assertEquals("1", $settings->tax_name1);

        $settings = $s->cast($settings)->toArray();
        $this->assertEquals("1", $settings['tax_name1']);

        $settings = new \stdClass();
        $settings->currency_id = '1';
        $settings->tax_name1 = [];
        $settings->tax_rate1 = 0;

        $settings = $s->cast($settings)->toObject();

        $this->assertEquals("", $settings->tax_name1);

        $settings = $s->cast($settings)->toArray();
        $this->assertEquals("", $settings['tax_name1']);

        $settings = new \stdClass();
        $settings->currency_id = '1';
        $settings->tax_name1 = new \stdClass();
        $settings->tax_rate1 = 0;

        $settings = $s->cast($settings)->toObject();

        $this->assertEquals("", $settings->tax_name1);

        $settings = $s->cast($settings)->toArray();
        $this->assertEquals("", $settings['tax_name1']);



        // nlog(json_encode($settings));
    }

    public function testTaxNameInGroupFilters()
    {
        $settings = new \stdClass();
        $settings->currency_id = '1';
        $settings->tax_name1 = '';
        $settings->tax_rate1 = 0;

        $data = [
            'name' => 'testX',
            'settings' => $settings,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/group_settings', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals("", (string)null);
        $this->assertNotNull($arr['data']['settings']['tax_name1']);
    }


    public function testAddGroupFilters()
    {
        $settings = new \stdClass();
        $settings->currency_id = '1';

        $data = [
            'name' => 'testX',
            'settings' => $settings,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/group_settings', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals('testX', $arr['data']['name']);
        $this->assertEquals(0, $arr['data']['archived_at']);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/group_settings?name=fdfdfd');

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertCount(0, $arr['data']);

    }


    public function testAddGroupSettings()
    {
        $settings = new \stdClass();
        $settings->currency_id = '1';

        $data = [
            'name' => 'testX',
            'settings' => $settings,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/group_settings', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals('testX', $arr['data']['name']);
        $this->assertEquals(0, $arr['data']['archived_at']);
    }

    public function testArchiveGroupSettings()
    {
        $settings = new \stdClass();
        $settings->currency_id = '1';

        $data = [
            'name' => 'testY',
            'settings' => $settings,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/group_settings', $data);

        $response->assertStatus(200);

        $arr = $response->json();
        $id = $arr['data']['id'];

        $this->assertEquals(0, $arr['data']['archived_at']);

        $data = [
            'action' => 'archive',
            'ids' => [$id],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/group_settings/bulk', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertNotNull($arr['data'][0]['archived_at']);

        $data = [
            'action' => 'restore',
            'ids' => [$id],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/group_settings/bulk', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals(0, $arr['data'][0]['archived_at']);

        $data = [
            'action' => 'delete',
            'ids' => [$id],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/group_settings/bulk', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertNotNull($arr['data'][0]['archived_at']);
        $this->assertTrue($arr['data'][0]['is_deleted']);


    }

}
