<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Feature\Mcp;

use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Project;
use App\Models\Quote;
use App\Models\Task;
use App\Utils\Traits\MakesHash;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Fixtures\Mcp\Fixture;
use Tests\TestCase;

class McpWorkflowTest extends TestCase
{
    use DatabaseTransactions;
    use MakesHash;

    private array $fixture;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mcp.enabled' => true, 'database.default' => config('ninja.db.default')]);
        $this->fixture = (new Fixture())->create();
    }

    private function callTool(string $tool, string $operation, array $data = [], array $parameters = []): array
    {
        $response = $this->withHeaders(['Authorization' => 'Bearer '.$this->fixture['roles']['alpha']['admin']['token']->token])
            ->postJson('/api/v1/mcp', [
                'jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call',
                'params' => ['name' => $tool, 'arguments' => ['operation' => $operation, 'data' => (object) $data, 'parameters' => (object) $parameters]],
            ]);
        $response->assertOk()->assertJsonPath('result.isError', false);
        $result = $response->json('result.structuredContent');
        $this->assertSame(200, $result['status'], $response->getContent());
        return $result['body'];
    }

    public function testInvoicePaymentAndOfflineRefundPersistAccountingEffects(): void
    {
        $client = $this->fixture['entities']['alpha']['clients'];
        $created = $this->callTool('invoices', 'store', [
            'client_id' => $client->hashed_id, 'date' => '2026-01-15',
            'line_items' => [['product_key' => 'Demo service', 'quantity' => 2, 'cost' => 50]],
        ]);
        $invoiceId = $created['data']['id'];
        $invoice = Invoice::findOrFail($this->decodePrimaryKey($invoiceId));
        $this->assertSame($client->id, $invoice->client_id);
        $this->assertSame($client->company_id, $invoice->company_id);
        $this->assertEquals(100, $invoice->amount);
        $this->assertEquals(Invoice::STATUS_DRAFT, $invoice->status_id);

        $this->callTool('invoices', 'action', [], ['invoice' => $invoiceId, 'action' => 'mark_sent']);
        $this->assertEquals(Invoice::STATUS_SENT, $invoice->fresh()->status_id);
        $paid = $this->callTool('payments', 'store', [
            'client_id' => $client->hashed_id, 'amount' => 100, 'date' => '2026-01-15', 'payment_type_id' => 1,
            'invoices' => [['invoice_id' => $invoiceId, 'amount' => 100]],
        ]);
        $paymentId = $paid['data']['id'];
        $payment = Payment::findOrFail($this->decodePrimaryKey($paymentId));
        $this->assertEquals(100, $payment->applied);
        $this->assertSame($client->company_id, $payment->company_id);
        $this->assertEquals(0, $invoice->fresh()->balance);
        $this->assertEquals(Invoice::STATUS_PAID, $invoice->fresh()->status_id);
        $this->assertDatabaseHas('paymentables', ['payment_id' => $payment->id, 'paymentable_id' => $invoice->id, 'paymentable_type' => $invoice->getMorphClass(), 'amount' => 100]);

        $this->callTool('payments', 'refund', [
            'id' => $paymentId, 'amount' => 25, 'date' => '2026-01-16', 'gateway_refund' => false, 'send_email' => false,
            'invoices' => [['invoice_id' => $invoiceId, 'amount' => 25]],
        ]);
        $this->assertEquals(25, $payment->fresh()->refunded);
        $this->assertDatabaseHas('paymentables', ['payment_id' => $payment->id, 'paymentable_id' => $invoice->id, 'refunded' => 25]);
        $read = $this->callTool('payments', 'show', [], ['payment' => $paymentId]);
        $this->assertEquals(25, $read['data']['refunded']);
    }

    public function testQuoteConversionCreatesTheLinkedInvoiceWithItsLineItems(): void
    {
        $client = $this->fixture['entities']['alpha']['clients'];
        $created = $this->callTool('quotes', 'store', [
            'client_id' => $client->hashed_id, 'date' => '2026-01-15',
            'line_items' => [['product_key' => 'Demo consultation', 'quantity' => 3, 'cost' => 40]],
        ]);
        $id = $created['data']['id'];
        $quote = Quote::findOrFail($this->decodePrimaryKey($id));
        $before = Invoice::where('company_id', $client->company_id)->count();
        $this->callTool('quotes', 'action', [], ['quote' => $id, 'action' => 'convert_to_invoice']);
        $quote->refresh();
        $this->assertEquals(Quote::STATUS_CONVERTED, $quote->status_id);
        $this->assertSame($before + 1, Invoice::where('company_id', $client->company_id)->count());
        $invoice = Invoice::findOrFail($quote->invoice_id);
        $this->assertSame($client->id, $invoice->client_id);
        $this->assertSame($client->company_id, $invoice->company_id);
        $this->assertEquals(120, $invoice->amount);
        $this->assertEquals($quote->line_items, $invoice->line_items);
        $read = $this->callTool('invoices', 'show', [], ['invoice' => $invoice->hashed_id]);
        $this->assertEquals(120, $read['data']['amount']);
    }

    public function testProjectTaskTimeTrackingAndExpenseArchiveRestorePersist(): void
    {
        $client = $this->fixture['entities']['alpha']['clients'];
        $projectData = $this->callTool('projects', 'store', ['client_id' => $client->hashed_id, 'name' => 'MCP delivery', 'task_rate' => 50]);
        $project = Project::findOrFail($this->decodePrimaryKey($projectData['data']['id']));
        $taskData = $this->callTool('tasks', 'store', [
            'client_id' => $client->hashed_id, 'project_id' => $project->hashed_id, 'description' => 'Demo work',
            'time_log' => json_encode([[1768471200, 1768474800]]),
        ]);
        $task = Task::findOrFail($this->decodePrimaryKey($taskData['data']['id']));
        $this->assertSame($project->id, $task->project_id);
        $this->assertSame($client->company_id, $task->company_id);
        $this->assertEquals(3600, $task->calcDuration());
        $this->callTool('tasks', 'update', ['time_log' => json_encode([[1768471200, 1768478400]])], ['task' => $task->hashed_id]);
        $this->assertEquals(7200, $task->fresh()->calcDuration());

        $expenseData = $this->callTool('expenses', 'store', ['client_id' => $client->hashed_id, 'amount' => 25, 'currency_id' => 1, 'date' => '2026-01-15']);
        $expense = Expense::findOrFail($this->decodePrimaryKey($expenseData['data']['id']));
        $this->assertSame($client->company_id, $expense->company_id);
        $this->assertEquals(25, $expense->amount);
        $this->callTool('expenses', 'bulk', ['ids' => [$expense->hashed_id], 'action' => 'archive']);
        $this->assertSoftDeleted('expenses', ['id' => $expense->id]);
        $this->callTool('expenses', 'bulk', ['ids' => [$expense->hashed_id], 'action' => 'restore']);
        $this->assertFalse($expense->fresh()->trashed());
        $read = $this->callTool('expenses', 'show', [], ['expense' => $expense->hashed_id]);
        $this->assertEquals(25, $read['data']['amount']);
    }
}
