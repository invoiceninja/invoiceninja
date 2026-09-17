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

use App\Factory\BankTransactionFactory;
use App\Factory\CreditFactory;
use App\Factory\ExpenseFactory;
use App\Factory\InvoiceFactory;
use App\Factory\PaymentFactory;
use App\Factory\ProductFactory;
use App\Factory\ProjectFactory;
use App\Factory\PurchaseOrderFactory;
use App\Factory\QuoteFactory;
use App\Factory\RecurringExpenseFactory;
use App\Factory\RecurringInvoiceFactory;
use App\Factory\TaskFactory;
use App\Models\Company;
use App\Models\Project;
use App\Models\Tag;
use App\Models\Task;
use App\Repositories\BankTransactionRepository;
use App\Repositories\CreditRepository;
use App\Repositories\ExpenseRepository;
use App\Repositories\InvoiceRepository;
use App\Repositories\PaymentRepository;
use App\Repositories\ProductRepository;
use App\Repositories\ProjectRepository;
use App\Repositories\PurchaseOrderRepository;
use App\Repositories\QuoteRepository;
use App\Repositories\RecurringExpenseRepository;
use App\Repositories\RecurringInvoiceRepository;
use App\Repositories\TaskRepository;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Session;
use Tests\MockAccountData;
use Tests\TestCase;

class GlobalTagInheritanceTest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeTestData();
        Session::start();
        Model::reguard();
    }

    private function makeTag(string $entity_type, string $name): Tag
    {
        return Tag::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'entity_type' => $entity_type,
            'name' => $name,
            'color' => '#ff0000',
        ]);
    }

    private function setInheritance(bool $enabled): void
    {
        $settings = $this->company->settings;
        $settings->global_tag_inheritance = $enabled;
        $this->company->settings = $settings;
        $this->company->saveQuietly();
    }

    /**
     * Enables inheritance and attaches one distinct GLOBAL tag to each of the
     * three taggable parents (client, vendor, project), so a child can be
     * asserted to have inherited exactly the union of its parents' tags.
     *
     * @return array{client: Tag, vendor: Tag, project: Tag}
     */
    private function seedGlobalParentTags(): array
    {
        $this->setInheritance(true);

        $client = $this->makeTag(Company::class, 'global-client');
        $vendor = $this->makeTag(Company::class, 'global-vendor');
        $project = $this->makeTag(Company::class, 'global-project');

        $this->client->syncTags([$this->encodePrimaryKey($client->id)]);
        $this->vendor->syncTags([$this->encodePrimaryKey($vendor->id)]);
        $this->project->syncTags([$this->encodePrimaryKey($project->id)]);

        return ['client' => $client, 'vendor' => $vendor, 'project' => $project];
    }

    /**
     * @param array<int> $expected_tag_ids
     */
    private function assertInheritedTags(object $entity, array $expected_tag_ids, string $label): void
    {
        $actual = $entity->tags()->pluck('tags.id')->map(fn ($id): int => (int) $id)->all();

        $this->assertEqualsCanonicalizing($expected_tag_ids, $actual, "[{$label}] inherited tags did not match.");
    }

    private function currencyId(): int
    {
        return (int) $this->company->settings->currency_id;
    }

    /**
     * @return array<string, string>
     */
    private function apiHeaders(): array
    {
        return [
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ];
    }

    /* -----------------------------------------------------------------
     |  Behaviour
     | ----------------------------------------------------------------- */

    public function testNoCascadeWhenSettingDisabled(): void
    {
        $this->setInheritance(false);

        $global_tag = $this->makeTag(Company::class, 'global-bob');
        $this->project->syncTags([$this->encodePrimaryKey($global_tag->id)]);

        $task = (new TaskRepository())->save(
            ['project_id' => $this->project->id],
            TaskFactory::create($this->company->id, $this->user->id)
        );

        $this->assertSame(0, $task->tags()->count());
    }

    public function testNoCascadeWhenRecordHasNoParents(): void
    {
        $this->seedGlobalParentTags();

        $task = (new TaskRepository())->save(
            ['description' => 'orphan'],
            TaskFactory::create($this->company->id, $this->user->id)
        );

        $this->assertSame(0, $task->tags()->count());
    }

    public function testOnlyGlobalTagsCascadeNotEntitySpecificTags(): void
    {
        $this->setInheritance(true);

        $global_tag = $this->makeTag(Company::class, 'global-bob');
        $project_tag = $this->makeTag(Project::class, 'project-only');
        $this->project->syncTags([
            $this->encodePrimaryKey($global_tag->id),
            $this->encodePrimaryKey($project_tag->id),
        ]);

        $task = (new TaskRepository())->save(
            ['project_id' => $this->project->id],
            TaskFactory::create($this->company->id, $this->user->id)
        );

        $this->assertInheritedTags($task, [$global_tag->id], 'task/global-only');
    }

    public function testTaskInheritsGlobalTagFromNewlyCreatedProject(): void
    {
        $this->setInheritance(true);

        $global_tag = $this->makeTag(Company::class, 'global-project-parent');

        $project = (new ProjectRepository())->save(
            [
                'client_id' => $this->client->id,
                'name' => 'Project with global tag',
                'tags' => [$this->encodePrimaryKey($global_tag->id)],
            ],
            ProjectFactory::create($this->company->id, $this->user->id)
        );

        $this->assertInheritedTags($project, [$global_tag->id], 'project');

        $blank = $this->withHeaders($this->apiHeaders())->getJson('/api/v1/tasks/create');
        $blank->assertStatus(200);

        $payload = $blank->json('data');
        $payload['description'] = 'asdasd';
        $payload['duration'] = 0;
        $payload['rate'] = 128;
        $payload['project_id'] = $this->encodePrimaryKey($project->id);
        $payload['time_log'] = '[[1782788852,1782806852,"",true]]';
        $payload['is_running'] = false;
        $payload['tags'] = [];

        $response = $this->withHeaders($this->apiHeaders())->postJson('/api/v1/tasks', $payload);

        $response->assertStatus(200);

        $task = Task::find($this->decodePrimaryKey($response->json('data.id')));

        $this->assertNotNull($task);
        $this->assertInheritedTags($task, [$global_tag->id], 'task/from-new-project-via-api');
    }

    public function testExplicitTagsAreMergedWithInheritedTags(): void
    {
        $this->setInheritance(true);

        $global_tag = $this->makeTag(Company::class, 'global-bob');
        $task_tag = $this->makeTag(Task::class, 'task-only');
        $this->project->syncTags([$this->encodePrimaryKey($global_tag->id)]);

        $task = (new TaskRepository())->save(
            [
                'project_id' => $this->project->id,
                'tags' => [$this->encodePrimaryKey($task_tag->id)],
            ],
            TaskFactory::create($this->company->id, $this->user->id)
        );

        $this->assertInheritedTags($task, [$global_tag->id, $task_tag->id], 'task/merge-explicit');
    }

    public function testInheritanceAppliesOnlyOnceAtCreationNotOnUpdate(): void
    {
        $this->seedGlobalParentTags();

        $repo = new TaskRepository();

        $task = $repo->save(
            ['description' => 'orphan'],
            TaskFactory::create($this->company->id, $this->user->id)
        );

        $this->assertSame(0, $task->tags()->count());

        // Attaching a project on a later update must NOT trigger inheritance.
        $task = $repo->save(['project_id' => $this->project->id], $task);

        $this->assertSame(0, $task->tags()->count(), 'Inheritance must only run at initial creation.');
    }

    /* -----------------------------------------------------------------
     |  Per-entity coverage
     | ----------------------------------------------------------------- */

    public function testInvoiceInheritsFromAllParents(): void
    {
        $tags = $this->seedGlobalParentTags();

        $invoice = (new InvoiceRepository())->save(
            [
                'client_id' => $this->client->id,
                'vendor_id' => $this->vendor->id,
                'project_id' => $this->project->id,
            ],
            InvoiceFactory::create($this->company->id, $this->user->id)
        );

        $this->assertInheritedTags($invoice, [$tags['client']->id, $tags['vendor']->id, $tags['project']->id], 'invoice');
    }

    public function testQuoteInheritsFromAllParents(): void
    {
        $tags = $this->seedGlobalParentTags();

        $quote = (new QuoteRepository())->save(
            [
                'client_id' => $this->client->id,
                'vendor_id' => $this->vendor->id,
                'project_id' => $this->project->id,
            ],
            QuoteFactory::create($this->company->id, $this->user->id)
        );

        $this->assertInheritedTags($quote, [$tags['client']->id, $tags['vendor']->id, $tags['project']->id], 'quote');
    }

    public function testCreditInheritsFromAllParents(): void
    {
        $tags = $this->seedGlobalParentTags();

        $credit = (new CreditRepository())->save(
            [
                'client_id' => $this->client->id,
                'vendor_id' => $this->vendor->id,
                'project_id' => $this->project->id,
            ],
            CreditFactory::create($this->company->id, $this->user->id)
        );

        $this->assertInheritedTags($credit, [$tags['client']->id, $tags['vendor']->id, $tags['project']->id], 'credit');
    }

    public function testRecurringInvoiceInheritsFromAllParents(): void
    {
        $tags = $this->seedGlobalParentTags();

        $recurring_invoice = (new RecurringInvoiceRepository())->save(
            [
                'client_id' => $this->client->id,
                'vendor_id' => $this->vendor->id,
                'project_id' => $this->project->id,
                'frequency_id' => 5,
            ],
            RecurringInvoiceFactory::create($this->company->id, $this->user->id)
        );

        $this->assertInheritedTags($recurring_invoice, [$tags['client']->id, $tags['vendor']->id, $tags['project']->id], 'recurring_invoice');
    }

    public function testPurchaseOrderInheritsFromAllParents(): void
    {
        $tags = $this->seedGlobalParentTags();

        $purchase_order = (new PurchaseOrderRepository())->save(
            [
                'vendor_id' => $this->vendor->id,
                'client_id' => $this->client->id,
                'project_id' => $this->project->id,
            ],
            PurchaseOrderFactory::create($this->company->id, $this->user->id)
        );

        $this->assertInheritedTags($purchase_order, [$tags['client']->id, $tags['vendor']->id, $tags['project']->id], 'purchase_order');
    }

    public function testExpenseInheritsFromAllParents(): void
    {
        $tags = $this->seedGlobalParentTags();

        $expense = (new ExpenseRepository())->save(
            [
                'currency_id' => $this->currencyId(),
                'client_id' => $this->client->id,
                'vendor_id' => $this->vendor->id,
                'project_id' => $this->project->id,
            ],
            ExpenseFactory::create($this->company->id, $this->user->id)
        );

        $this->assertInheritedTags($expense, [$tags['client']->id, $tags['vendor']->id, $tags['project']->id], 'expense');
    }

    public function testRecurringExpenseInheritsFromAllParents(): void
    {
        $tags = $this->seedGlobalParentTags();

        $recurring_expense = (new RecurringExpenseRepository())->save(
            [
                'currency_id' => $this->currencyId(),
                'client_id' => $this->client->id,
                'vendor_id' => $this->vendor->id,
                'project_id' => $this->project->id,
                'frequency_id' => 5,
            ],
            RecurringExpenseFactory::create($this->company->id, $this->user->id)
        );

        $this->assertInheritedTags($recurring_expense, [$tags['client']->id, $tags['vendor']->id, $tags['project']->id], 'recurring_expense');
    }

    public function testTaskInheritsFromClientAndProject(): void
    {
        $tags = $this->seedGlobalParentTags();

        // Task has no vendor_id column, so the vendor global tag must NOT cascade.
        $task = (new TaskRepository())->save(
            [
                'client_id' => $this->client->id,
                'project_id' => $this->project->id,
            ],
            TaskFactory::create($this->company->id, $this->user->id)
        );

        $this->assertInheritedTags($task, [$tags['client']->id, $tags['project']->id], 'task');
    }

    public function testPaymentInheritsFromClient(): void
    {
        $tags = $this->seedGlobalParentTags();

        $payment = app(PaymentRepository::class)->save(
            [
                'client_id' => $this->client->id,
                'amount' => 10,
                'date' => '2020-01-01',
            ],
            PaymentFactory::create($this->company->id, $this->user->id, $this->client->id)
        );

        $this->assertInheritedTags($payment, [$tags['client']->id], 'payment');
    }

    public function testProjectInheritsFromClient(): void
    {
        $tags = $this->seedGlobalParentTags();

        // A newly created project is itself a child of its client.
        $project = (new ProjectRepository())->save(
            [
                'client_id' => $this->client->id,
                'name' => 'Inherited project',
            ],
            ProjectFactory::create($this->company->id, $this->user->id)
        );

        $this->assertInheritedTags($project, [$tags['client']->id], 'project');
    }

    public function testBankTransactionInheritsFromVendor(): void
    {
        $tags = $this->seedGlobalParentTags();

        $bank_transaction = (new BankTransactionRepository())->save(
            [
                'amount' => 10,
                'date' => '2020-01-01',
                'currency_id' => $this->currencyId(),
                'bank_integration_id' => $this->bank_integration->id,
                'vendor_id' => $this->vendor->id,
                'base_type' => 'DEBIT',
            ],
            BankTransactionFactory::create($this->company->id, $this->user->id)
        );

        $this->assertInheritedTags($bank_transaction, [$tags['vendor']->id], 'bank_transaction');
    }

    public function testProductDoesNotInheritBecauseItHasNoOwningParent(): void
    {
        $this->seedGlobalParentTags();

        // Products are company-level; they carry no client/vendor/project owner.
        $product = (new ProductRepository())->save(
            [
                'product_key' => 'inherited-product',
                'notes' => 'n',
                'cost' => 1,
                'price' => 1,
            ],
            ProductFactory::create($this->company->id, $this->user->id)
        );

        $this->assertSame(0, $product->tags()->count());
    }

    /* -----------------------------------------------------------------
     |  Roll-up: invoices inherit the global tags of the tasks/expenses
     |  they bill (additive, at link time).
     | ----------------------------------------------------------------- */

    private function taggedTask(int $tag_id): Task
    {
        $task = TaskFactory::create($this->company->id, $this->user->id);
        $task->save();
        $task->syncTags([$this->encodePrimaryKey($tag_id)]);

        return $task;
    }

    private function taggedExpense(int $tag_id): \App\Models\Expense
    {
        $expense = ExpenseFactory::create($this->company->id, $this->user->id);
        $expense->save();
        $expense->syncTags([$this->encodePrimaryKey($tag_id)]);

        return $expense;
    }

    /**
     * @param array<int, array<string, mixed>> $line_items
     */
    private function saveInvoiceWithLineItems(array $line_items): \App\Models\Invoice
    {
        return (new InvoiceRepository())->save(
            ['client_id' => $this->client->id, 'line_items' => $line_items],
            InvoiceFactory::create($this->company->id, $this->user->id)
        );
    }

    public function testInvoiceRollsUpGlobalTagsFromBilledTasksAndExpenses(): void
    {
        $this->setInheritance(true);

        $task_tag = $this->makeTag(Company::class, 'task-global');
        $expense_tag = $this->makeTag(Company::class, 'expense-global');

        $task = $this->taggedTask($task_tag->id);
        $expense = $this->taggedExpense($expense_tag->id);

        $invoice = $this->saveInvoiceWithLineItems([
            ['product_key' => 'svc', 'notes' => 't', 'cost' => 10, 'quantity' => 1, 'type_id' => '2', 'task_id' => $this->encodePrimaryKey($task->id)],
            ['product_key' => 'exp', 'notes' => 'e', 'cost' => 5, 'quantity' => 1, 'type_id' => '1', 'expense_id' => $this->encodePrimaryKey($expense->id)],
        ]);

        $this->assertInheritedTags($invoice, [$task_tag->id, $expense_tag->id], 'invoice-rollup');
    }

    public function testRollupIsAdditiveAndPreservesOwnershipTags(): void
    {
        $this->setInheritance(true);

        $client_tag = $this->makeTag(Company::class, 'client-global');
        $this->client->syncTags([$this->encodePrimaryKey($client_tag->id)]);

        $task_tag = $this->makeTag(Company::class, 'task-global');
        $task = $this->taggedTask($task_tag->id);

        $invoice = $this->saveInvoiceWithLineItems([
            ['product_key' => 'svc', 'notes' => 't', 'cost' => 10, 'quantity' => 1, 'type_id' => '2', 'task_id' => $this->encodePrimaryKey($task->id)],
        ]);

        // client_tag from the ownership cascade + task_tag from the roll-up
        $this->assertInheritedTags($invoice, [$client_tag->id, $task_tag->id], 'invoice-rollup-additive');
    }

    public function testRollupExcludesEntitySpecificTaskTags(): void
    {
        $this->setInheritance(true);

        $global_tag = $this->makeTag(Company::class, 'task-global');
        $task_only_tag = $this->makeTag(Task::class, 'task-only');

        $task = TaskFactory::create($this->company->id, $this->user->id);
        $task->save();
        $task->syncTags([
            $this->encodePrimaryKey($global_tag->id),
            $this->encodePrimaryKey($task_only_tag->id),
        ]);

        $invoice = $this->saveInvoiceWithLineItems([
            ['product_key' => 'svc', 'notes' => 't', 'cost' => 10, 'quantity' => 1, 'type_id' => '2', 'task_id' => $this->encodePrimaryKey($task->id)],
        ]);

        $this->assertInheritedTags($invoice, [$global_tag->id], 'invoice-rollup-global-only');
    }

    public function testNoRollupWhenSettingDisabled(): void
    {
        $this->setInheritance(false);

        $task_tag = $this->makeTag(Company::class, 'task-global');
        $task = $this->taggedTask($task_tag->id);

        $invoice = $this->saveInvoiceWithLineItems([
            ['product_key' => 'svc', 'notes' => 't', 'cost' => 10, 'quantity' => 1, 'type_id' => '2', 'task_id' => $this->encodePrimaryKey($task->id)],
        ]);

        $this->assertSame(0, $invoice->tags()->count());
    }
}
