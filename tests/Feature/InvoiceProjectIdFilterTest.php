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

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Project;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Session;
use Tests\MockAccountData;
use Tests\TestCase;

class InvoiceProjectIdFilterTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        Session::start();
        Model::reguard();

        $this->makeTestData();

        $this->withoutMiddleware(ThrottleRequests::class);
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        return [
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function invoiceIds(array $payload): array
    {
        return array_column($payload['data'], 'id');
    }

    private function createInvoiceForProject(Project $project): Invoice
    {
        return Invoice::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'project_id' => $project->id,
            'number' => 'inv-project-' . uniqid(),
        ]);
    }

    /**
     * @return array{company: Company, client: Client, project: Project, invoice: Invoice}
     */
    private function createForeignCompanyProjectInvoice(): array
    {
        $company = Company::factory()->create([
            'account_id' => $this->account->id,
        ]);

        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $company->id,
        ]);

        $project = Project::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $company->id,
            'client_id' => $client->id,
        ]);

        $invoice = Invoice::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $company->id,
            'client_id' => $client->id,
            'project_id' => $project->id,
            'number' => 'inv-foreign-project-' . uniqid(),
        ]);

        return [
            'company' => $company,
            'client' => $client,
            'project' => $project,
            'invoice' => $invoice,
        ];
    }

    public function testProjectIdFilterReturnsOnlyMatchingInvoices(): void
    {
        $matching = $this->createInvoiceForProject($this->project);
        $other_project = Project::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
        ]);
        $other = $this->createInvoiceForProject($other_project);

        $response = $this->withHeaders($this->headers())
            ->get('/api/v1/invoices?project_id=' . $this->project->hashed_id . '&per_page=500')
            ->assertStatus(200);

        $ids = $this->invoiceIds($response->json());

        $this->assertContains($matching->hashed_id, $ids);
        $this->assertNotContains($other->hashed_id, $ids);
        $this->assertNotContains($this->invoice->hashed_id, $ids);
    }

    public function testEmptyProjectIdDoesNotRestrictResults(): void
    {
        $matching = $this->createInvoiceForProject($this->project);

        $response = $this->withHeaders($this->headers())
            ->get('/api/v1/invoices?project_id=&per_page=500')
            ->assertStatus(200);

        $ids = $this->invoiceIds($response->json());

        $this->assertContains($matching->hashed_id, $ids);
        $this->assertContains($this->invoice->hashed_id, $ids);
    }

    public function testForeignCompanyProjectIdDoesNotReturnForeignInvoices(): void
    {
        $matching = $this->createInvoiceForProject($this->project);
        $foreign = $this->createForeignCompanyProjectInvoice();

        $response = $this->withHeaders($this->headers())
            ->get('/api/v1/invoices?project_id=' . $foreign['project']->hashed_id . '&per_page=500')
            ->assertStatus(200);

        $ids = $this->invoiceIds($response->json());

        $this->assertNotContains($foreign['invoice']->hashed_id, $ids);
        $this->assertNotContains($matching->hashed_id, $ids);
        $this->assertNotContains($this->invoice->hashed_id, $ids);
        $this->assertSame([], $ids);
    }

    public function testForeignProjectIsNotResolvedWhenInvoiceIsMislinked(): void
    {
        $foreign = $this->createForeignCompanyProjectInvoice();

        $mislinked = Invoice::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'project_id' => $foreign['project']->id,
            'number' => 'inv-mislinked-project-' . uniqid(),
        ]);

        $filtered = $this->withHeaders($this->headers())
            ->get('/api/v1/invoices?project_id=' . $foreign['project']->hashed_id . '&include=project&per_page=500')
            ->assertStatus(200)
            ->json();

        $this->assertSame([], $this->invoiceIds($filtered));

        $included = $this->withHeaders($this->headers())
            ->get('/api/v1/invoices?include=project&per_page=500')
            ->assertStatus(200)
            ->json();

        $mislinked_row = collect($included['data'])->firstWhere('id', $mislinked->hashed_id);

        $this->assertNotNull($mislinked_row);
        $this->assertArrayNotHasKey('project', $mislinked_row);
    }

    public function testSameCompanyProjectIsIncludedOnList(): void
    {
        $matching = $this->createInvoiceForProject($this->project);

        $response = $this->withHeaders($this->headers())
            ->get('/api/v1/invoices?project_id=' . $this->project->hashed_id . '&include=project&per_page=500')
            ->assertStatus(200);

        $row = collect($response->json('data'))->firstWhere('id', $matching->hashed_id);

        $this->assertIsArray($row);
        $this->assertArrayHasKey('project', $row);
        $this->assertSame($this->project->hashed_id, $row['project']['id']);
    }
}
