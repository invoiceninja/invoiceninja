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

use App\DataMapper\CompanySettings;
use App\DataMapper\DefaultSettings;
use App\Factory\InvoiceInvitationFactory;
use App\Models\Account;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

/**
 * @test
 * @covers App\Models\InvoiceInvitation
 */
class InvitationTest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;
    // use RefreshDatabase;

    protected function setUp() :void
    {
        parent::setUp();
    }

    public function testInvoiceCreationAfterInvoiceMarkedSent()
    {
        $account = Account::factory()->create();
        $company = Company::factory()->create([
            'account_id' => $account->id,
        ]);

        $account->default_company_id = $company->id;
        $account->save();

        $user = User::where('email', 'user@example.com')->first();

        if (! $user) {
            $user = User::factory()->create([
                'account_id' => $account->id,
                'confirmation_code' => $this->createDbHash(config('database.default')),
            ]);
        }

        $userPermissions = collect([
            'view_invoice',
            'view_client',
            'edit_client',
            'edit_invoice',
            'create_invoice',
            'create_client',
        ]);

        $userSettings = DefaultSettings::userSettings();

        $user->companies()->attach($company->id, [
            'account_id' => $account->id,
            'is_owner' => 1,
            'is_admin' => 1,
            'notifications' => CompanySettings::notificationDefaults(),
            'permissions' => $userPermissions->toJson(),
            'settings' => json_encode($userSettings),
            'is_locked' => 0,
        ]);

        Client::factory()->create(['user_id' => $user->id, 'company_id' => $company->id])->each(function ($c) use ($user, $company) {
            ClientContact::factory()->create([
                'user_id' => $user->id,
                'client_id' => $c->id,
                'company_id' => $company->id,
                'is_primary' => 1,
            ]);

            ClientContact::factory()->count(2)->create([
                'user_id' => $user->id,
                'client_id' => $c->id,
                'company_id' => $company->id,
            ]);
        });

        $client = Client::whereUserId($user->id)->whereCompanyId($company->id)->first();

        Invoice::factory()->count(5)->create(['user_id' => $user->id, 'company_id' => $company->id, 'client_id' => $client->id]);

        $invoice = Invoice::whereUserId($user->id)->whereCompanyId($company->id)->whereClientId($client->id)->first();

        $this->assertNotNull($invoice);
        $this->assertNotNull($invoice->client);
        $this->assertNotNull($invoice->client->primary_contact);

        $i = InvoiceInvitationFactory::create($invoice->company_id, $invoice->user_id);
        $i->key = $this->createDbHash(config('database.default'));
        $i->client_contact_id = $client->primary_contact->first()->id;
        $i->invoice_id = $invoice->id;
        $i->save();

        $this->assertNotNull($invoice->invitations);
    }
}
