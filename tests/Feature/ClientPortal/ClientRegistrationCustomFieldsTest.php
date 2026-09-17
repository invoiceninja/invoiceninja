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

namespace Tests\Feature\ClientPortal;

use App\DataMapper\ClientRegistrationFields;
use App\Factory\CompanyUserFactory;
use App\Http\Middleware\ContactRegister;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Account;
use App\Models\Company;
use App\Models\User;
use App\Services\ClientPortal\CustomFieldService;
use App\Utils\Traits\AppSetup;
use Faker\Factory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use stdClass;
use Tests\TestCase;

class ClientRegistrationCustomFieldsTest extends TestCase
{
    use DatabaseTransactions;
    use AppSetup;

    public $faker;

    private Account $account;

    private Company $company;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->faker = Factory::create();

        $this->withoutMiddleware([
            VerifyCsrfToken::class,
            ThrottleRequests::class,
            ContactRegister::class,
        ]);

        $this->account = Account::factory()->create();

        $this->user = User::factory()->create([
            'account_id' => $this->account->id,
            'email' => uniqid('user.', true) . '@example.test',
        ]);

        $this->company = Company::factory()->create([
            'account_id' => $this->account->id,
            'client_can_register' => true,
        ]);
        $this->company->settings->language_id = '1';

        // client1 = date, client2 = dropdown, client3 = switch, client4 = single_line_text
        $customFields = new stdClass();
        $customFields->client1 = 'Birth Date|date';
        $customFields->client2 = 'Country|SK,CZ,HU,AT';
        $customFields->client3 = 'Consent|switch';
        $customFields->client4 = 'Contract No|single_line_text';
        $this->company->custom_fields = $customFields;

        $fields = ClientRegistrationFields::generate();
        foreach ($fields as &$field) {
            if ($field['key'] === 'custom_value1') {
                $field['visible'] = true;
                $field['required'] = true;
            }
            if (in_array($field['key'], ['custom_value2', 'custom_value3', 'custom_value4'])) {
                $field['visible'] = true;
                $field['required'] = false;
            }
        }
        $this->company->client_registration_fields = $fields;
        $this->company->save();

        $cu = CompanyUserFactory::create($this->user->id, $this->company->id, $this->account->id);
        $cu->is_owner = true;
        $cu->save();
    }

    // --- CustomFieldService integration ---

    public function testBuildFieldsReturnsAllVisibleCustomFields(): void
    {
        $service = app(CustomFieldService::class);
        $fields = $service->buildFields($this->company);

        $keys = array_column($fields, 'key');
        $this->assertContains('custom_value1', $keys);
        $this->assertContains('custom_value2', $keys);
        $this->assertContains('custom_value3', $keys);
        $this->assertContains('custom_value4', $keys);
    }

    public function testBuildFieldsResolvesCorrectTypes(): void
    {
        $service = app(CustomFieldService::class);
        $fields = collect($service->buildFields($this->company))->keyBy('key');

        $this->assertEquals('date', $fields['custom_value1']['type']);
        $this->assertEquals('dropdown', $fields['custom_value2']['type']);
        $this->assertEquals('switch', $fields['custom_value3']['type']);
        $this->assertEquals('text', $fields['custom_value4']['type']);
    }

    public function testBuildFieldsDropdownHasCorrectOptions(): void
    {
        $service = app(CustomFieldService::class);
        $fields = collect($service->buildFields($this->company))->keyBy('key');

        $this->assertEquals(['SK', 'CZ', 'HU', 'AT'], $fields['custom_value2']['options']);
    }

    public function testBuildFieldsExcludesHiddenFields(): void
    {
        $company = Company::factory()->create([
            'account_id' => $this->account->id,
        ]);
        $company->custom_fields = $this->company->custom_fields;
        $company->client_registration_fields = ClientRegistrationFields::generate();
        $company->save();

        $service = app(CustomFieldService::class);
        $fields = $service->buildFields($company);

        $keys = array_column($fields, 'key');
        $this->assertNotContains('custom_value1', $keys);
        $this->assertNotContains('custom_value2', $keys);
    }

    // --- GET: registration form rendering ---

    public function testRegistrationFormRendersDateInputForDateCustomField(): void
    {
        $response = $this->get(route('client.register', $this->company->company_key));

        $response->assertOk();
        $response->assertSee('type="date"', false);
        $response->assertSee('name="custom_value1"', false);
    }

    public function testRegistrationFormRendersSelectForDropdownCustomField(): void
    {
        $response = $this->get(route('client.register', $this->company->company_key));

        $response->assertOk();
        $response->assertSee('name="custom_value2"', false);
        $response->assertSee('value="SK"', false);
        $response->assertSee('value="CZ"', false);
    }

    public function testRegistrationFormRendersSwitchAsSelectWithYesNo(): void
    {
        $response = $this->get(route('client.register', $this->company->company_key));

        $response->assertOk();
        $response->assertSee('name="custom_value3"', false);
        $response->assertSee('value="yes"', false);
        $response->assertSee('value="no"', false);
    }

    public function testRegistrationFormRendersTextInputForSingleLineTextField(): void
    {
        $response = $this->get(route('client.register', $this->company->company_key));

        $response->assertOk();
        $response->assertSee('name="custom_value4"', false);
        $response->assertSee('type="text"', false);
    }

    // --- POST: validation ---

    public function testRegistrationRejectsInvalidDateForDateCustomField(): void
    {
        $response = $this->post(
            '/client/register/' . $this->company->company_key,
            $this->validPayload(['custom_value1' => 'not-a-date'])
        );

        $response->assertSessionHasErrors('custom_value1');
    }

    public function testRegistrationRejectsValueNotInDropdownOptions(): void
    {
        $response = $this->post(
            '/client/register/' . $this->company->company_key,
            $this->validPayload(['custom_value2' => 'DE'])
        );

        $response->assertSessionHasErrors('custom_value2');
    }

    public function testRegistrationRejectsInvalidSwitchValue(): void
    {
        $response = $this->post(
            '/client/register/' . $this->company->company_key,
            $this->validPayload(['custom_value3' => 'maybe'])
        );

        $response->assertSessionHasErrors('custom_value3');
    }

    public function testRegistrationRejectsMissingRequiredDateCustomField(): void
    {
        $response = $this->post(
            '/client/register/' . $this->company->company_key,
            $this->validPayload(['custom_value1' => ''])
        );

        $response->assertSessionHasErrors('custom_value1');
    }

    // --- POST: success ---

    public function testRegistrationSucceedsWithValidCustomFieldValues(): void
    {
        $response = $this->post(
            '/client/register/' . $this->company->company_key,
            $this->validPayload([
                'custom_value1' => '1990-05-15',
                'custom_value2' => 'SK',
                'custom_value3' => 'yes',
                'custom_value4' => 'CONTRACT-2024-001',
            ])
        );

        $response->assertRedirect(route('client.dashboard'));
        $this->assertDatabaseHas('clients', [
            'company_id' => $this->company->id,
            'custom_value1' => '1990-05-15',
            'custom_value2' => 'SK',
            'custom_value3' => 'yes',
            'custom_value4' => 'CONTRACT-2024-001',
        ]);
    }

    public function testRegistrationSucceedsWithOptionalDropdownLeftEmpty(): void
    {
        $response = $this->post(
            '/client/register/' . $this->company->company_key,
            $this->validPayload([
                'custom_value1' => '2000-01-01',
                // custom_value2 omitted — it's optional
            ])
        );

        $response->assertRedirect(route('client.dashboard'));
    }

    // --- Helpers ---

    private function validPayload(array $overrides = []): array
    {
        $email = uniqid('testuser') . '@gmail.com';

        return array_merge([
            'company_key' => $this->company->company_key,
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'email' => $email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'custom_value1' => '1990-05-15',
        ], $overrides);
    }

    // public function tearDown(): void
    // {
    //     // $this->account->delete();
    //     // parent::tearDown();
    // }
}
