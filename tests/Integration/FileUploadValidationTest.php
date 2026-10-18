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

namespace Tests\Integration;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Document;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\MockAccountData;
use Tests\TestCase;

class FileUploadValidationTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;
    use MakesHash;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();
    }

    /**
     * @return array<string, Model>
     */
    private function apiUploadEndpoints(): array
    {
        return [
            'invoices' => $this->invoice,
            'quotes' => $this->quote,
            'payments' => $this->payment,
            'credits' => $this->credit,
            'expenses' => $this->expense,
            'projects' => $this->project,
            'tasks' => $this->task,
            'vendors' => $this->vendor,
            'products' => $this->product,
            'clients' => $this->client,
            'recurring_invoices' => $this->recurring_invoice,
            'recurring_expenses' => $this->recurring_expense,
            'recurring_quotes' => $this->recurring_quote,
            'purchase_orders' => $this->purchase_order,
            'companies' => $this->company,
            'group_settings' => $this->client->group_settings,
        ];
    }

    private function postApiUpload(string $url, array $payload): \Illuminate\Testing\TestResponse
    {
        return $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson($url, $payload);
    }

    private function latestDocument(Model $entity): ?Document
    {
        return $entity->fresh()->documents()->orderByDesc('id')->first();
    }

    private function setDocumentsPublicByDefault(bool $value): void
    {
        $settings = clone $this->company->settings;
        $settings->documents_public_by_default = $value;
        $this->company->settings = $settings;
        $this->company->save();
    }

    private function enableClientPortalUploads(): void
    {
        $settings = clone $this->company->settings;
        $settings->client_portal_enable_uploads = true;
        $this->company->settings = $settings;
        $this->company->save();

        $group = $this->client->group_settings;
        $group_settings = clone $group->settings;
        $group_settings->client_portal_enable_uploads = true;
        $group->settings = $group_settings;
        $group->save();
    }

    private function enableVendorPortalUploads(): void
    {
        $settings = clone $this->company->settings;
        $settings->vendor_portal_enable_uploads = true;
        $this->company->settings = $settings;
        $this->company->save();
    }

    public function testIteratingThroughAllEntities()
    {
        Storage::fake('local');

        $file = UploadedFile::fake()->image('avatar.jpg');

        $data = [
            'documents' => [$file],
            'is_public' => false,
            '_method' => 'PUT',
        ];

        foreach ($this->apiUploadEndpoints() as $route => $entity) {
            $response = $this->postApiUpload("/api/v1/{$route}/{$entity->hashed_id}/upload", $data);

            $this->assertSame(200, $response->status(), "Upload failed for {$route}: ".$response->getContent());

            $document = $this->latestDocument($entity);
            $this->assertNotNull($document, "Expected a document on {$route}");
            $this->assertFalse($document->is_public, "Expected private document on {$route}");
        }
    }

    public function testFileUploadIsPublicSetsAppropriately()
    {
        Storage::fake('local');

        $file = UploadedFile::fake()->image('avatar.jpg');

        $data = [
            'documents' => [$file],
            'is_public' => false,
            '_method' => 'PUT',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson("/api/v1/invoices/{$this->invoice->hashed_id}/upload", $data);

        $response->assertStatus(200);
        $acc = $response->json();

        $this->assertCount(1, $acc['data']['documents']);
        $this->assertFalse($acc['data']['documents'][0]['is_public']);

        $data = [
                    'documents' => [$file],
                    'is_public' => true,
                    '_method' => 'PUT',
                ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson("/api/v1/invoices/{$this->invoice->hashed_id}/upload", $data);

        $response->assertStatus(200);
        $acc = $response->json();

        $this->assertCount(2, $acc['data']['documents']);
        $this->assertTrue($acc['data']['documents'][1]['is_public']);

    }

    public function testMultiFileUploadIsPublicSetsAppropriately()
    {
        Storage::fake('local');

        $file = UploadedFile::fake()->image('avatar.jpg');

        $data = [
            'documents' => [$file, $file],
            'is_public' => false,
            '_method' => 'PUT',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson("/api/v1/invoices/{$this->invoice->hashed_id}/upload", $data);

        $response->assertStatus(200);
        $acc = $response->json();

        $this->assertCount(2, $acc['data']['documents']);
        $this->assertFalse($acc['data']['documents'][0]['is_public']);
        $this->assertFalse($acc['data']['documents'][1]['is_public']);

    }

    public function testUploadUsesCompanyDocumentsPublicByDefaultSetting(): void
    {
        Storage::fake('local');

        $settings = $this->company->settings;
        $settings->documents_public_by_default = false;
        $this->company->settings = $settings;
        $this->company->save();

        $file = UploadedFile::fake()->image('avatar.jpg');

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson("/api/v1/invoices/{$this->invoice->hashed_id}/upload", [
            'documents' => [$file],
            '_method' => 'PUT',
        ]);

        $response->assertStatus(200);
        $acc = $response->json();

        $this->assertCount(1, $acc['data']['documents']);
        $this->assertFalse($acc['data']['documents'][0]['is_public']);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson("/api/v1/invoices/{$this->invoice->hashed_id}/upload", [
            'documents' => [$file],
            'is_public' => true,
            '_method' => 'PUT',
        ]);

        $response->assertStatus(200);
        $acc = $response->json();

        $this->assertCount(2, $acc['data']['documents']);
        $this->assertTrue($acc['data']['documents'][1]['is_public']);
    }

    public function testAllApiUploadEndpointsDefaultToPublicWhenIsPublicOmitted(): void
    {
        Storage::fake('local');

        $this->setDocumentsPublicByDefault(true);

        foreach ($this->apiUploadEndpoints() as $route => $entity) {
            $response = $this->postApiUpload("/api/v1/{$route}/{$entity->hashed_id}/upload", [
                'documents' => [UploadedFile::fake()->image('avatar.jpg')],
                '_method' => 'PUT',
            ]);

            $this->assertSame(200, $response->status(), "Upload failed for {$route}: ".$response->getContent());

            $document = $this->latestDocument($entity);
            $this->assertNotNull($document, "Expected a document on {$route}");
            $this->assertTrue((bool) $document->is_public, "Expected public document on {$route}");
        }
    }

    public function testAllApiUploadEndpointsHonorDocumentsPublicByDefaultFalse(): void
    {
        Storage::fake('local');

        $this->setDocumentsPublicByDefault(false);

        foreach ($this->apiUploadEndpoints() as $route => $entity) {
            $response = $this->postApiUpload("/api/v1/{$route}/{$entity->hashed_id}/upload", [
                'documents' => [UploadedFile::fake()->image('avatar.jpg')],
                '_method' => 'PUT',
            ]);

            $this->assertSame(200, $response->status(), "Upload failed for {$route}: ".$response->getContent());

            $document = $this->latestDocument($entity);
            $this->assertNotNull($document, "Expected a document on {$route}");
            $this->assertFalse((bool) $document->is_public, "Expected private document on {$route}");
        }
    }

    public function testAllApiUploadEndpointsExplicitIsPublicOverridesCompanyDefault(): void
    {
        Storage::fake('local');

        $this->setDocumentsPublicByDefault(false);

        foreach ($this->apiUploadEndpoints() as $route => $entity) {
            $response = $this->postApiUpload("/api/v1/{$route}/{$entity->hashed_id}/upload", [
                'documents' => [UploadedFile::fake()->image('avatar.jpg')],
                'is_public' => true,
                '_method' => 'PUT',
            ]);

            $this->assertSame(200, $response->status(), "Upload failed for {$route}: ".$response->getContent());

            $document = $this->latestDocument($entity);
            $this->assertNotNull($document, "Expected a document on {$route}");
            $this->assertTrue((bool) $document->is_public, "Expected public document on {$route}");
        }
    }

    public function testInvoiceFileFieldUploadHonorsDocumentsPublicByDefault(): void
    {
        Storage::fake('local');

        $this->setDocumentsPublicByDefault(false);

        $response = $this->postApiUpload("/api/v1/invoices/{$this->invoice->hashed_id}/upload", [
            'file' => [UploadedFile::fake()->image('avatar.jpg')],
            '_method' => 'PUT',
        ]);

        $response->assertStatus(200);

        $document = $this->latestDocument($this->invoice);
        $this->assertNotNull($document);
        $this->assertFalse((bool) $document->is_public);

        $response = $this->postApiUpload("/api/v1/invoices/{$this->invoice->hashed_id}/upload", [
            'file' => [UploadedFile::fake()->image('avatar.jpg')],
            'is_public' => true,
            '_method' => 'PUT',
        ]);

        $response->assertStatus(200);

        $document = $this->latestDocument($this->invoice);
        $this->assertNotNull($document);
        $this->assertTrue((bool) $document->is_public);
    }

    public function testClientPortalUploadHonorsDocumentsPublicByDefaultFalse(): void
    {
        Storage::fake('local');

        $this->withoutMiddleware(VerifyCsrfToken::class);
        $this->setDocumentsPublicByDefault(false);
        $this->enableClientPortalUploads();

        $this->actingAs($this->contact, 'contact')
            ->post('/client/upload', [
                'file' => UploadedFile::fake()->image('avatar.jpg'),
            ])
            ->assertStatus(200);

        $document = $this->latestDocument($this->client);
        $this->assertNotNull($document);
        $this->assertFalse((bool) $document->is_public);

        $this->actingAs($this->contact, 'contact')
            ->post('/client/upload', [
                'file' => UploadedFile::fake()->image('avatar.jpg'),
                'is_public' => true,
            ])
            ->assertStatus(200);

        $document = $this->latestDocument($this->client);
        $this->assertNotNull($document);
        $this->assertTrue((bool) $document->is_public);
    }

    public function testClientPortalUploadDefaultsToPublicWhenSettingEnabled(): void
    {
        Storage::fake('local');

        $this->withoutMiddleware(VerifyCsrfToken::class);
        $this->setDocumentsPublicByDefault(true);
        $this->enableClientPortalUploads();

        $this->actingAs($this->contact, 'contact')
            ->post('/client/upload', [
                'file' => UploadedFile::fake()->image('avatar.jpg'),
            ])
            ->assertStatus(200);

        $document = $this->latestDocument($this->client);
        $this->assertNotNull($document);
        $this->assertTrue((bool) $document->is_public);
    }

    public function testVendorPortalUploadHonorsDocumentsPublicByDefaultFalse(): void
    {
        Storage::fake('local');

        $this->withoutMiddleware(VerifyCsrfToken::class);
        $this->setDocumentsPublicByDefault(false);
        $this->enableVendorPortalUploads();

        $vendor_contact = $this->vendor->contacts()->first();

        $this->actingAs($vendor_contact, 'vendor')
            ->post("/vendor/purchase_order/upload/{$this->purchase_order->hashed_id}", [
                'file' => UploadedFile::fake()->image('avatar.jpg'),
            ])
            ->assertStatus(200);

        $document = $this->latestDocument($this->purchase_order);
        $this->assertNotNull($document);
        $this->assertFalse((bool) $document->is_public);

        $this->actingAs($vendor_contact, 'vendor')
            ->post("/vendor/purchase_order/upload/{$this->purchase_order->hashed_id}", [
                'file' => UploadedFile::fake()->image('avatar.jpg'),
                'is_public' => true,
            ])
            ->assertStatus(200);

        $document = $this->latestDocument($this->purchase_order);
        $this->assertNotNull($document);
        $this->assertTrue((bool) $document->is_public);
    }

    public function testVendorPortalUploadDefaultsToPublicWhenSettingEnabled(): void
    {
        Storage::fake('local');

        $this->withoutMiddleware(VerifyCsrfToken::class);
        $this->setDocumentsPublicByDefault(true);
        $this->enableVendorPortalUploads();

        $vendor_contact = $this->vendor->contacts()->first();

        $this->actingAs($vendor_contact, 'vendor')
            ->post("/vendor/purchase_order/upload/{$this->purchase_order->hashed_id}", [
                'file' => UploadedFile::fake()->image('avatar.jpg'),
            ])
            ->assertStatus(200);

        $document = $this->latestDocument($this->purchase_order);
        $this->assertNotNull($document);
        $this->assertTrue((bool) $document->is_public);
    }

}
