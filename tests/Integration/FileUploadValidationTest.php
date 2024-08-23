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

use App\Utils\Traits\MakesHash;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 */
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

    public function testIteratingThroughAllEntities()
    {

        Storage::fake('local');

        $file = UploadedFile::fake()->image('avatar.jpg');

        $data = [
            'documents' => [$file],
            'is_public' => false,
            '_method' => 'PUT',
        ];

        $entities = [
            'invoice' => 'invoices',
            'quote' => 'quotes',
            'payment' => 'payments',
            'credit' => 'credits',
            'expense' => 'expenses',
            'project' => 'projects',
            'task' => 'tasks',
            'vendor' => 'vendors',
            'product' => 'products',
            'client' => 'clients',
            'recurring_invoice' => 'recurring_invoices',
            'recurring_expense' => 'recurring_expenses',
        ];

        foreach($entities as $key => $value) {

            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->postJson("/api/v1/{$value}/{$this->{$key}->hashed_id}/upload", $data);

            $acc = $response->json();
            $response->assertStatus(200);

            $this->assertCount(1, $acc['data']['documents']);
            $this->assertFalse($acc['data']['documents'][0]['is_public']);
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

}
