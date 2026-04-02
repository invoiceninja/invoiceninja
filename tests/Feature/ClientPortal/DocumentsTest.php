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

use App\DataMapper\ClientSettings;
use App\DataMapper\CompanySettings;
use App\Models\Account;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\User;
use App\Utils\Traits\AppSetup;
use App\Utils\Traits\MakesHash;
use Faker\Factory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class DocumentsTest extends TestCase
{
    use DatabaseTransactions;
    use AppSetup;
    use MakesHash;

    private $faker;

    private $account;

    private $user;

    private $company;

    private $client;

    private $contact;

    private $otherClient;

    private $otherContact;

    protected function setUp(): void
    {
        parent::setUp();

        $this->faker = Factory::create();

        $this->account = Account::factory()->create();

        $this->user = User::factory()->create([
            'account_id' => $this->account->id,
            'email' => $this->faker->safeEmail(),
        ]);

        $this->company = Company::factory()->create(['account_id' => $this->account->id]);
        $this->company->settings = CompanySettings::defaults();
        $this->company->save();

        $this->client = Client::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
        ]);
        $settings = ClientSettings::defaults();
        $settings->language_id = '1';
        $this->client->settings = $settings;
        $this->client->save();

        $this->contact = ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
        ]);

        $this->otherClient = Client::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
        ]);
        $otherSettings = ClientSettings::defaults();
        $otherSettings->language_id = '1';
        $this->otherClient->settings = $otherSettings;
        $this->otherClient->save();

        $this->otherContact = ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->otherClient->id,
            'company_id' => $this->company->id,
        ]);

        $this->withoutMiddleware();
    }

    public function testDownloadMultipleOwnClientDocuments(): void
    {
        $document = Document::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
        ]);

        $this->client->documents()->save($document);

        $response = $this->actingAs($this->contact, 'contact')
            ->postJson(route('client.documents.download_multiple'), [
                'file_hash' => [$document->hashed_id],
            ]);

        $this->assertNotEquals(401, $response->getStatusCode());
    }

    public function testDownloadMultipleBlocksOtherClientDocuments(): void
    {
        $document = Document::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
        ]);

        $this->otherClient->documents()->save($document);

        $response = $this->actingAs($this->contact, 'contact')
            ->postJson(route('client.documents.download_multiple'), [
                'file_hash' => [$document->hashed_id],
            ]);

        $response->assertStatus(401);
    }

    public function testDownloadMultipleBlocksPrivateEntityDocuments(): void
    {
        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        $document = Document::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'is_public' => false,
        ]);

        $invoice->documents()->save($document);

        $response = $this->actingAs($this->contact, 'contact')
            ->postJson(route('client.documents.download_multiple'), [
                'file_hash' => [$document->hashed_id],
            ]);

        $response->assertStatus(401);
    }

    public function testDownloadMultipleAllowsPublicEntityDocuments(): void
    {
        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        $document = Document::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'is_public' => true,
        ]);

        $invoice->documents()->save($document);

        $response = $this->actingAs($this->contact, 'contact')
            ->postJson(route('client.documents.download_multiple'), [
                'file_hash' => [$document->hashed_id],
            ]);

        $this->assertNotEquals(401, $response->getStatusCode());
    }

    public function testDownloadMultipleBlocksOtherClientEntityDocuments(): void
    {
        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->otherClient->id,
        ]);

        $document = Document::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'is_public' => true,
        ]);

        $invoice->documents()->save($document);

        $response = $this->actingAs($this->contact, 'contact')
            ->postJson(route('client.documents.download_multiple'), [
                'file_hash' => [$document->hashed_id],
            ]);

        $response->assertStatus(401);
    }

    public function testDownloadMultipleBlocksMixedAuthorizedAndUnauthorized(): void
    {
        $ownDoc = Document::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
        ]);
        $this->client->documents()->save($ownDoc);

        $otherDoc = Document::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
        ]);
        $this->otherClient->documents()->save($otherDoc);

        $response = $this->actingAs($this->contact, 'contact')
            ->postJson(route('client.documents.download_multiple'), [
                'file_hash' => [$ownDoc->hashed_id, $otherDoc->hashed_id],
            ]);

        $response->assertStatus(401);
    }

    public function testDownloadMultipleAllowsPublicCompanyDocuments(): void
    {
        $document = Document::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'is_public' => true,
        ]);

        $this->company->documents()->save($document);

        $response = $this->actingAs($this->contact, 'contact')
            ->postJson(route('client.documents.download_multiple'), [
                'file_hash' => [$document->hashed_id],
            ]);

        $this->assertNotEquals(401, $response->getStatusCode());
    }

    public function testDownloadMultipleBlocksPrivateCompanyDocuments(): void
    {
        $document = Document::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'is_public' => false,
        ]);

        $this->company->documents()->save($document);

        $response = $this->actingAs($this->contact, 'contact')
            ->postJson(route('client.documents.download_multiple'), [
                'file_hash' => [$document->hashed_id],
            ]);

        $response->assertStatus(401);
    }
}
