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
use App\Livewire\Profile\Settings\CustomFields;
use App\Models\Account;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\User;
use App\Utils\Traits\AppSetup;
use Faker\Factory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use stdClass;
use Tests\TestCase;

class ProfileCustomFieldsTest extends TestCase
{
    use DatabaseTransactions;
    use AppSetup;

    private \Faker\Generator $faker;

    private Account $account;

    private Company $company;

    private User $user;

    private Client $client;

    private ClientContact $contact;

    protected function setUp(): void
    {
        parent::setUp();

        $this->faker = Factory::create();

        $this->account = Account::factory()->create();

        $this->user = User::factory()->create([
            'account_id' => $this->account->id,
            'email' => uniqid('testuser') . '@gmail.com',
        ]);

        $this->company = Company::factory()->create([
            'account_id' => $this->account->id,
        ]);
        $this->company->settings->language_id = '1';

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

        $this->client = Client::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'custom_value1' => '1985-03-20',
            'custom_value2' => 'SK',
            'custom_value3' => 'yes',
            'custom_value4' => 'ORIG-001',
        ]);

        $this->contact = ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'email' => uniqid('testuser') . '@gmail.com',
            'password' => Hash::make('password'),
            'is_primary' => true,
        ]);
    }

    // --- mount: initial values ---

    public function testMountLoadsExistingCustomValuesFromClient(): void
    {
        $this->actingAs($this->contact, 'contact');

        Livewire::test(CustomFields::class)
            ->assertSet('custom_value1', '1985-03-20')
            ->assertSet('custom_value2', 'SK')
            ->assertSet('custom_value3', 'yes')
            ->assertSet('custom_value4', 'ORIG-001');
    }

    public function testMountSetsEmptyStringForNullCustomValues(): void
    {
        $emptyClient = Client::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'custom_value1' => null,
            'custom_value2' => null,
        ]);

        /** @var ClientContact $emptyContact */
        $emptyContact = ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $emptyClient->id,
            'company_id' => $this->company->id,
            'email' => uniqid('testuser') . '@gmail.com',
            'password' => Hash::make('password'),
            'is_primary' => true,
        ]);

        $this->actingAs($emptyContact, 'contact');

        Livewire::test(CustomFields::class)
            ->assertSet('custom_value1', '')
            ->assertSet('custom_value2', '');
    }

    // --- computed: field definitions ---

    public function testCustomFieldDefinitionsContainsAllVisibleFields(): void
    {
        $this->actingAs($this->contact, 'contact');

        $component = Livewire::test(CustomFields::class);
        $defs = $component->get('customFieldDefinitions');

        $keys = array_column($defs, 'key');
        $this->assertContains('custom_value1', $keys);
        $this->assertContains('custom_value2', $keys);
        $this->assertContains('custom_value3', $keys);
        $this->assertContains('custom_value4', $keys);
    }

    public function testCustomFieldDefinitionsReturnsCorrectTypes(): void
    {
        $this->actingAs($this->contact, 'contact');

        $component = Livewire::test(CustomFields::class);
        $defs = collect($component->get('customFieldDefinitions'))->keyBy('key');

        $this->assertEquals('date', $defs['custom_value1']['type']);
        $this->assertEquals('dropdown', $defs['custom_value2']['type']);
        $this->assertEquals('switch', $defs['custom_value3']['type']);
        $this->assertEquals('text', $defs['custom_value4']['type']);
    }

    // --- submit: validation ---

    public function testSubmitRejectsInvalidDateValue(): void
    {
        $this->actingAs($this->contact, 'contact');

        Livewire::test(CustomFields::class)
            ->set('custom_value1', 'not-a-date')
            ->call('submit')
            ->assertHasErrors(['custom_value1']);
    }

    public function testSubmitRejectsValueNotInDropdownOptions(): void
    {
        $this->actingAs($this->contact, 'contact');

        Livewire::test(CustomFields::class)
            ->set('custom_value2', 'DE')
            ->call('submit')
            ->assertHasErrors(['custom_value2']);
    }

    public function testSubmitRejectsInvalidSwitchValue(): void
    {
        $this->actingAs($this->contact, 'contact');

        Livewire::test(CustomFields::class)
            ->set('custom_value3', 'maybe')
            ->call('submit')
            ->assertHasErrors(['custom_value3']);
    }

    public function testSubmitRejectsMissingRequiredDateField(): void
    {
        $this->actingAs($this->contact, 'contact');

        Livewire::test(CustomFields::class)
            ->set('custom_value1', '')
            ->call('submit')
            ->assertHasErrors(['custom_value1']);
    }

    // --- submit: success ---

    public function testSubmitSavesValidCustomValuesToClient(): void
    {
        $this->actingAs($this->contact, 'contact');

        Livewire::test(CustomFields::class)
            ->set('custom_value1', '1990-06-15')
            ->set('custom_value2', 'CZ')
            ->set('custom_value3', 'no')
            ->set('custom_value4', 'NEW-CONTRACT-42')
            ->call('submit')
            ->assertHasNoErrors();

        $this->client->refresh();
        $this->assertEquals('1990-06-15', $this->client->custom_value1);
        $this->assertEquals('CZ', $this->client->custom_value2);
        $this->assertEquals('no', $this->client->custom_value3);
        $this->assertEquals('NEW-CONTRACT-42', $this->client->custom_value4);
    }

    public function testSubmitUpdatesSavedTimestamp(): void
    {
        $this->actingAs($this->contact, 'contact');

        Livewire::test(CustomFields::class)
            ->set('custom_value1', '2000-01-01')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('saved', fn ($value) => str_contains($value, ':'));
    }

    public function testSubmitDoesNothingWhenNoCustomFieldsDefined(): void
    {
        $this->company->custom_fields = new stdClass();
        $this->company->client_registration_fields = ClientRegistrationFields::generate();
        $this->company->save();

        $this->actingAs($this->contact, 'contact');

        Livewire::test(CustomFields::class)
            ->call('submit')
            ->assertHasNoErrors();

        $this->client->refresh();
        $this->assertEquals('1985-03-20', $this->client->custom_value1);
    }

    public function tearDown(): void
    {
        $this->account->delete();
        parent::tearDown();
    }
}
