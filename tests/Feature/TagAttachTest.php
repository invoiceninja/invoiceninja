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

use App\Models\Company;
use App\Models\Credit;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\Quote;
use App\Models\Product;
use App\Models\RecurringExpense;
use App\Models\RecurringInvoice;
use App\Models\Tag;
use App\Models\Task;
use App\Models\Vendor;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Tests\MockAccountData;
use Tests\TestCase;

class TagAttachTest extends TestCase
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

    private function headers(): array
    {
        return [
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ];
    }

    private function makeTag(string $entity_type, string $name = 'urgent', ?string $color = '#ff0000'): Tag
    {
        return Tag::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'entity_type' => $entity_type,
            'name' => $name,
            'color' => $color,
        ]);
    }

    private function assertResponseHasOnlyTag($response, Tag $tag): void
    {
        $tags = $response->json('data.tags');

        $this->assertCount(1, $tags);
        $this->assertSame($this->encodePrimaryKey($tag->id), $tags[0]['id']);
        $this->assertSame($tag->name, $tags[0]['name']);
    }

    public function testSyncTagsAttachesTagToTask(): void
    {
        $task = $this->task;
        $tag = $this->makeTag(Task::class, 'urgent');

        $task->syncTags([$this->encodePrimaryKey($tag->id)]);

        $this->assertSame(1, $task->tags()->count());
        $this->assertSame($tag->id, $task->tags()->first()->id);
    }

    public function testSyncTagsRejectsCrossEntityType(): void
    {
        $task = $this->task;
        $project_tag = $this->makeTag(Project::class, 'project-only');

        $this->expectException(ValidationException::class);

        $task->syncTags([$this->encodePrimaryKey($project_tag->id)]);
    }

    public function testSyncTagsRejectsUnknownId(): void
    {
        $task = $this->task;

        $this->expectException(ValidationException::class);

        $task->syncTags([$this->encodePrimaryKey(999999999)]);
    }

    public function testSyncTagsRejectsMalformedId(): void
    {
        $task = $this->task;

        $this->expectException(ValidationException::class);

        $task->syncTags(['not-a-tag-id']);
    }

    public function testSyncTagsRejectsDeletedTag(): void
    {
        $task = $this->task;
        $tag = $this->makeTag(Task::class, 'deleted-task');
        $tag->is_deleted = true;
        $tag->save();
        $tag->delete();

        $this->expectException(ValidationException::class);

        $task->syncTags([$this->encodePrimaryKey($tag->id)]);
    }

    public function testTaskUpdateRejectsRawNumericTagIdString(): void
    {
        $tag = $this->makeTag(Task::class, 'urgent');

        $response = $this->withHeaders($this->headers())
            ->putJson('/api/v1/tasks/'.$this->encodePrimaryKey($this->task->id), [
                'tags' => [(string) $tag->id],
            ]);

        $response->assertStatus(422);
        $this->assertSame(0, $this->task->tags()->count());
    }

    public function testSyncTagsEmptyArrayDetachesAll(): void
    {
        $task = $this->task;
        $tag = $this->makeTag(Task::class, 'urgent');
        $task->syncTags([$this->encodePrimaryKey($tag->id)]);
        $this->assertSame(1, $task->tags()->count());

        $task->syncTags([]);

        $this->assertSame(0, $task->tags()->count());
    }

    public function testTransformerEmitsTagObjects(): void
    {
        $task = $this->task;
        $tag = $this->makeTag(Task::class, 'urgent', '#ff0000');
        $task->syncTags([$this->encodePrimaryKey($tag->id)]);

        $response = $this->withHeaders($this->headers())
            ->getJson('/api/v1/tasks/'.$this->encodePrimaryKey($task->id));

        $response->assertStatus(200);

        $tags = $response->json('data.tags');
        $this->assertCount(1, $tags);
        $this->assertSame($this->encodePrimaryKey($tag->id), $tags[0]['id']);
        $this->assertSame('urgent', $tags[0]['name']);
        $this->assertSame('#ff0000', $tags[0]['color']);
    }

    public function testTransformerEmitsEmptyTagsArrayWhenNoneAttached(): void
    {
        $response = $this->withHeaders($this->headers())
            ->getJson('/api/v1/tasks/'.$this->encodePrimaryKey($this->task->id));

        $response->assertStatus(200);
        $this->assertSame([], $response->json('data.tags'));
    }

    public function testTaskStoreWithTagsSyncs(): void
    {
        $tag = $this->makeTag(Task::class, 'store-task');

        $response = $this->withHeaders($this->headers())
            ->postJson('/api/v1/tasks', [
                'description' => 'Tagged task',
                'tags' => [
                    [
                        'id' => $this->encodePrimaryKey($tag->id),
                        'name' => $tag->name,
                        'color' => $tag->color,
                    ],
                ],
            ]);

        $response->assertStatus(200);
        $tags = $response->json('data.tags');
        $this->assertCount(1, $tags);
        $this->assertSame($this->encodePrimaryKey($tag->id), $tags[0]['id']);
        $this->assertSame('store-task', $tags[0]['name']);
    }

    public function testTaskUpdateWithTagsSyncs(): void
    {
        $tag = $this->makeTag(Task::class, 'urgent');

        $response = $this->withHeaders($this->headers())
            ->putJson('/api/v1/tasks/'.$this->encodePrimaryKey($this->task->id), [
                'tags' => [
                    [
                        'id' => $this->encodePrimaryKey($tag->id),
                        'name' => $tag->name,
                        'color' => $tag->color,
                    ],
                ],
            ]);

        $response->assertStatus(200);
        $tags = $response->json('data.tags');
        $this->assertCount(1, $tags);
        $this->assertSame($this->encodePrimaryKey($tag->id), $tags[0]['id']);
        $this->assertSame('urgent', $tags[0]['name']);
    }

    public function testTaskUpdateRejectsMalformedTagObject(): void
    {
        $response = $this->withHeaders($this->headers())
            ->putJson('/api/v1/tasks/'.$this->encodePrimaryKey($this->task->id), [
                'tags' => [
                    ['name' => 'missing-id'],
                ],
            ]);

        $response->assertStatus(422);
        $this->assertSame(0, $this->task->fresh()->tags()->count());
    }

    public function testTaskUpdateRejectsCrossCompanyTag(): void
    {
        $other_company = Company::factory()->create([
            'account_id' => $this->account->id,
        ]);

        $tag = Tag::factory()->create([
            'company_id' => $other_company->id,
            'user_id' => $this->user->id,
            'entity_type' => Task::class,
            'name' => 'other-company',
        ]);

        $response = $this->withHeaders($this->headers())
            ->putJson('/api/v1/tasks/'.$this->encodePrimaryKey($this->task->id), [
                'tags' => [$this->encodePrimaryKey($tag->id)],
            ]);

        $response->assertStatus(422);
        $this->assertSame(0, $this->task->fresh()->tags()->count());
    }

    public function testTaskUpdateWithEmptyTagsDetachesAll(): void
    {
        $tag = $this->makeTag(Task::class, 'detachable-task');
        $this->task->syncTags([$this->encodePrimaryKey($tag->id)]);

        $response = $this->withHeaders($this->headers())
            ->putJson('/api/v1/tasks/'.$this->encodePrimaryKey($this->task->id), [
                'tags' => [],
            ]);

        $response->assertStatus(200);
        $this->assertSame([], $response->json('data.tags'));
        $this->assertSame(0, $this->task->fresh()->tags()->count());
    }

    public function testTaskUpdateWithCrossTypeTagFails(): void
    {
        $project_tag = $this->makeTag(Project::class, 'project-tag');

        $response = $this->withHeaders($this->headers())
            ->putJson('/api/v1/tasks/'.$this->encodePrimaryKey($this->task->id), [
                'tags' => [$this->encodePrimaryKey($project_tag->id)],
            ]);

        $response->assertStatus(422);
    }

    public function testTaskUpdateIsTransactionalOnInvalidTag(): void
    {
        $original_description = $this->task->description;

        $response = $this->withHeaders($this->headers())
            ->putJson('/api/v1/tasks/'.$this->encodePrimaryKey($this->task->id), [
                'description' => 'should not persist',
                'tags' => [$this->encodePrimaryKey(999999999)],
            ]);

        $response->assertStatus(422);
        $this->assertSame($original_description, $this->task->fresh()->description);
    }

    public function testProjectStoreWithTagsSyncs(): void
    {
        $tag = $this->makeTag(Project::class, 'store-project');

        $response = $this->withHeaders($this->headers())
            ->postJson('/api/v1/projects', [
                'name' => 'Tagged project',
                'client_id' => $this->client->hashed_id,
                'task_rate' => 0,
                'tags' => [$this->encodePrimaryKey($tag->id)],
            ]);

        $response->assertStatus(200);
        $tags = $response->json('data.tags');
        $this->assertCount(1, $tags);
        $this->assertSame($this->encodePrimaryKey($tag->id), $tags[0]['id']);
        $this->assertSame('store-project', $tags[0]['name']);
    }

    public function testProjectUpdateWithTagsSyncs(): void
    {
        $tag = $this->makeTag(Project::class, 'client-facing');

        $response = $this->withHeaders($this->headers())
            ->putJson('/api/v1/projects/'.$this->encodePrimaryKey($this->project->id), [
                'tags' => [
                    [
                        'id' => $this->encodePrimaryKey($tag->id),
                        'name' => $tag->name,
                        'color' => $tag->color,
                    ],
                ],
            ]);

        $response->assertStatus(200);
        $tags = $response->json('data.tags');
        $this->assertCount(1, $tags);
        $this->assertSame($this->encodePrimaryKey($tag->id), $tags[0]['id']);
        $this->assertSame('client-facing', $tags[0]['name']);
    }

    public function testProjectUpdateWithEmptyTagsDetachesAll(): void
    {
        $tag = $this->makeTag(Project::class, 'detachable-project');
        $this->project->syncTags([$this->encodePrimaryKey($tag->id)]);

        $response = $this->withHeaders($this->headers())
            ->putJson('/api/v1/projects/'.$this->encodePrimaryKey($this->project->id), [
                'tags' => [],
            ]);

        $response->assertStatus(200);
        $this->assertSame([], $response->json('data.tags'));
        $this->assertSame(0, $this->project->fresh()->tags()->count());
    }

    public function testSyncTagsSupportsExpandedEntitySet(): void
    {
        $entities = [
            'invoice' => $this->invoice,
            'quote' => $this->quote,
            'credit' => $this->credit,
            'payment' => $this->payment,
            'recurring-invoice' => $this->recurring_invoice,
            'expense' => $this->expense,
            'recurring-expense' => $this->recurring_expense,
            'bank-transaction' => $this->bank_transaction,
            'purchase-order' => $this->purchase_order,
            'client' => $this->client,
            'vendor' => $this->vendor,
            'product' => $this->product,
        ];

        foreach ($entities as $name => $entity) {
            $tag = $this->makeTag(get_class($entity), 'sync-'.$name);

            $entity->syncTags([$this->encodePrimaryKey($tag->id)]);

            $this->assertDatabaseHas('taggables', [
                'tag_id' => $tag->id,
                'taggable_id' => $entity->id,
                'taggable_type' => $entity->getMorphClass(),
            ]);
        }
    }

    public function testInvoiceUpdateWithTagsSyncs(): void
    {
        $tag = $this->makeTag(Invoice::class, 'invoice-api');

        $response = $this->withHeaders($this->headers())
            ->putJson('/api/v1/invoices/'.$this->encodePrimaryKey($this->invoice->id), [
                'tags' => [$this->encodePrimaryKey($tag->id)],
            ]);

        $response->assertStatus(200);

        $tags = $response->json('data.tags');
        $this->assertCount(1, $tags);
        $this->assertSame($this->encodePrimaryKey($tag->id), $tags[0]['id']);
        $this->assertSame('invoice-api', $tags[0]['name']);
    }

    public function testInvoiceTagOnlyUpdateTouchesParentForIncrementalSync(): void
    {
        $tag = $this->makeTag(Invoice::class, 'invoice-touch');
        $this->invoice->updated_at = now()->subDay();
        $this->invoice->saveQuietly();
        $updated_at = (int) $this->invoice->fresh()->updated_at;

        $response = $this->withHeaders($this->headers())
            ->putJson('/api/v1/invoices/'.$this->encodePrimaryKey($this->invoice->id), [
                'tags' => [$this->encodePrimaryKey($tag->id)],
        ]);

        $response->assertStatus(200);
        $this->assertGreaterThan($updated_at, (int) $this->invoice->fresh()->updated_at);
    }

    public function testInvoiceUpdateReplacesExistingTags(): void
    {
        $old_tag = $this->makeTag(Invoice::class, 'invoice-old');
        $new_tag = $this->makeTag(Invoice::class, 'invoice-new');
        $this->invoice->syncTags([$this->encodePrimaryKey($old_tag->id)]);

        $response = $this->withHeaders($this->headers())
            ->putJson('/api/v1/invoices/'.$this->encodePrimaryKey($this->invoice->id), [
                'tags' => [$this->encodePrimaryKey($new_tag->id)],
            ]);

        $response->assertStatus(200);
        $this->assertResponseHasOnlyTag($response, $new_tag);
        $this->assertDatabaseMissing('taggables', [
            'tag_id' => $old_tag->id,
            'taggable_id' => $this->invoice->id,
            'taggable_type' => Invoice::class,
        ]);
    }

    public function testPaymentUpdateWithTagsSyncs(): void
    {
        $tag = $this->makeTag(get_class($this->payment), 'payment-api');

        $response = $this->withHeaders($this->headers())
            ->putJson('/api/v1/payments/'.$this->encodePrimaryKey($this->payment->id), [
                'tags' => [$this->encodePrimaryKey($tag->id)],
            ]);

        $response->assertStatus(200);

        $tags = $response->json('data.tags');
        $this->assertCount(1, $tags);
        $this->assertSame($this->encodePrimaryKey($tag->id), $tags[0]['id']);
        $this->assertSame('payment-api', $tags[0]['name']);
    }

    public function testPaymentStoreDecodesGlobalKeysWhenSyncingTags(): void
    {
        $tag = $this->makeTag(get_class($this->payment), 'payment-store-api');

        $response = $this->withHeaders($this->headers())
            ->postJson('/api/v1/payments', [
                'client_id' => $this->encodePrimaryKey($this->client->id),
                'assigned_user_id' => $this->encodePrimaryKey($this->user->id),
                'amount' => 17.13,
                'date' => now()->format('Y-m-d'),
                'tags' => [$this->encodePrimaryKey($tag->id)],
            ]);

        $response->assertStatus(200);
        $this->assertResponseHasOnlyTag($response, $tag);
    }

    public function testBankTransactionStoreDecodesGlobalKeysWhenSyncingTags(): void
    {
        $tag = $this->makeTag(get_class($this->bank_transaction), 'bank-transaction-store-api');

        $response = $this->withHeaders($this->headers())
            ->postJson('/api/v1/bank_transactions', [
                'bank_integration_id' => $this->encodePrimaryKey($this->bank_integration->id),
                'assigned_user_id' => $this->encodePrimaryKey($this->user->id),
                'base_type' => 'debit',
                'amount' => 12.34,
                'tags' => [$this->encodePrimaryKey($tag->id)],
            ]);

        $response->assertStatus(200);
        $this->assertResponseHasOnlyTag($response, $tag);
    }

    public function testBankTransactionUpdateDecodesGlobalKeysWhenSyncingTags(): void
    {
        $tag = $this->makeTag(get_class($this->bank_transaction), 'bank-transaction-update-api');

        $response = $this->withHeaders($this->headers())
            ->putJson('/api/v1/bank_transactions/'.$this->encodePrimaryKey($this->bank_transaction->id), [
                'date' => now()->format('Y-m-d'),
                'amount' => $this->bank_transaction->amount,
                'bank_integration_id' => $this->encodePrimaryKey($this->bank_integration->id),
                'assigned_user_id' => $this->encodePrimaryKey($this->user->id),
                'tags' => [$this->encodePrimaryKey($tag->id)],
            ]);

        $response->assertStatus(200);
        $this->assertResponseHasOnlyTag($response, $tag);
    }

    public function testExpandedEntityApiUpdatesWithTagsSync(): void
    {
        $cases = [
            'quote' => [
                'entity' => $this->quote,
                'entity_type' => Quote::class,
                'endpoint' => '/api/v1/quotes/',
                'payload' => [],
            ],
            'credit' => [
                'entity' => $this->credit,
                'entity_type' => Credit::class,
                'endpoint' => '/api/v1/credits/',
                'payload' => [],
            ],
            'expense' => [
                'entity' => $this->expense,
                'entity_type' => Expense::class,
                'endpoint' => '/api/v1/expenses/',
                'payload' => [],
            ],
            'recurring-expense' => [
                'entity' => $this->recurring_expense,
                'entity_type' => RecurringExpense::class,
                'endpoint' => '/api/v1/recurring_expenses/',
                'payload' => [],
            ],
            'purchase-order' => [
                'entity' => $this->purchase_order,
                'entity_type' => PurchaseOrder::class,
                'endpoint' => '/api/v1/purchase_orders/',
                'payload' => [],
            ],
            'vendor' => [
                'entity' => $this->vendor,
                'entity_type' => Vendor::class,
                'endpoint' => '/api/v1/vendors/',
                'payload' => [],
            ],
            'bank-transaction' => [
                'entity' => $this->bank_transaction,
                'entity_type' => get_class($this->bank_transaction),
                'endpoint' => '/api/v1/bank_transactions/',
                'payload' => [
                    'date' => now()->format('Y-m-d'),
                    'amount' => $this->bank_transaction->amount,
                    'bank_integration_id' => $this->encodePrimaryKey($this->bank_transaction->bank_integration_id),
                ],
            ],
        ];

        foreach ($cases as $name => $case) {
            $tag = $this->makeTag($case['entity_type'], 'api-'.$name);
            $payload = array_merge($case['payload'], [
                'tags' => [$this->encodePrimaryKey($tag->id)],
            ]);

            $response = $this->withHeaders($this->headers())
                ->putJson($case['endpoint'].$this->encodePrimaryKey($case['entity']->id), $payload);

            $response->assertStatus(200);
            $this->assertResponseHasOnlyTag($response, $tag);
        }
    }

    public function testInvoiceUpdateRespectsArchivedTagInTagsPayload(): void
    {
        $archived_tag = $this->makeTag(Invoice::class, 'archived-invoice');
        $this->invoice->syncTags([$this->encodePrimaryKey($archived_tag->id)]);

        $archived_tag->delete();

        $response = $this->withHeaders($this->headers())
            ->putJson('/api/v1/invoices/'.$this->encodePrimaryKey($this->invoice->id), [
                'tags' => [$this->encodePrimaryKey($archived_tag->id)],
            ]);

        $response->assertStatus(200);

        $tags = $response->json('data.tags');

        $this->assertCount(1, $tags);
        $this->assertSame($this->encodePrimaryKey($archived_tag->id), $tags[0]['id']);
    }

    public function testRecurringInvoiceStoreWithTagsSyncs(): void
    {
        $tag = $this->makeTag(RecurringInvoice::class, 'recurring-store-api');

        $response = $this->withHeaders($this->headers())
            ->post('/api/v1/recurring_invoices/', [
                'frequency_id' => 1,
                'status_id' => RecurringInvoice::STATUS_DRAFT,
                'client_id' => $this->encodePrimaryKey($this->client->id),
                'line_items' => $this->buildLineItems(),
                'remaining_cycles' => -1,
                'tags' => [
                    [
                        'id' => $this->encodePrimaryKey($tag->id),
                        'name' => $tag->name,
                        'color' => $tag->color,
                    ],
                ],
            ]);

        $response->assertStatus(200);

        $tags = $response->json('data.tags');
        $this->assertCount(1, $tags);
        $this->assertSame($this->encodePrimaryKey($tag->id), $tags[0]['id']);
        $this->assertSame('recurring-store-api', $tags[0]['name']);
    }

    public function testRecurringInvoiceUpdateWithTagsSyncs(): void
    {
        $tag = $this->makeTag(RecurringInvoice::class, 'recurring-update-api');

        $response = $this->withHeaders($this->headers())
            ->putJson('/api/v1/recurring_invoices/'.$this->encodePrimaryKey($this->recurring_invoice->id), [
                'client_id' => $this->encodePrimaryKey($this->recurring_invoice->client_id),
                'next_send_date' => now()->format('Y-m-d'),
                'tags' => [$this->encodePrimaryKey($tag->id)],
            ]);

        $response->assertStatus(200);

        $tags = $response->json('data.tags');
        $this->assertCount(1, $tags);
        $this->assertSame($this->encodePrimaryKey($tag->id), $tags[0]['id']);
        $this->assertSame('recurring-update-api', $tags[0]['name']);
    }

    public function testRecurringInvoiceUpdateRejectsRawNumericTagId(): void
    {
        $tag = $this->makeTag(RecurringInvoice::class, 'recurring-raw-id');

        $response = $this->withHeaders($this->headers())
            ->putJson('/api/v1/recurring_invoices/'.$this->encodePrimaryKey($this->recurring_invoice->id), [
                'next_send_date' => now()->format('Y-m-d'),
                'tags' => [$tag->id],
            ]);

        $response->assertStatus(422);
        $this->assertSame(0, $this->recurring_invoice->fresh()->tags()->count());
    }

    public function testProductStoreWithTagsSyncs(): void
    {
        $tag = $this->makeTag(Product::class, 'product-api');

        $response = $this->withHeaders($this->headers())
            ->postJson('/api/v1/products', [
                'product_key' => 'tagged-product',
                'cost' => 10,
                'price' => 15,
                'tags' => [
                    [
                        'id' => $this->encodePrimaryKey($tag->id),
                        'name' => $tag->name,
                        'color' => $tag->color,
                    ],
                ],
            ]);

        $response->assertStatus(200);

        $tags = $response->json('data.tags');
        $this->assertCount(1, $tags);
        $this->assertSame($this->encodePrimaryKey($tag->id), $tags[0]['id']);
        $this->assertSame('product-api', $tags[0]['name']);
    }

    public function testProductUpdateWithTagsSyncs(): void
    {
        $tag = $this->makeTag(Product::class, 'product-update-api');

        $response = $this->withHeaders($this->headers())
            ->putJson('/api/v1/products/'.$this->encodePrimaryKey($this->product->id), [
                'product_key' => 'tagged-product-update',
                'tags' => [$this->encodePrimaryKey($tag->id)],
            ]);

        $response->assertStatus(200);

        $tags = $response->json('data.tags');
        $this->assertCount(1, $tags);
        $this->assertSame($this->encodePrimaryKey($tag->id), $tags[0]['id']);
        $this->assertSame('product-update-api', $tags[0]['name']);
    }

    public function testClientUpdateWithTagsSyncs(): void
    {
        $tag = $this->makeTag(get_class($this->client), 'client-api');

        $response = $this->withHeaders($this->headers())
            ->putJson('/api/v1/clients/'.$this->encodePrimaryKey($this->client->id), [
                'name' => $this->client->name,
                'contacts' => [
                    [
                        'id' => $this->encodePrimaryKey($this->contact->id),
                        'first_name' => $this->contact->first_name,
                        'last_name' => $this->contact->last_name,
                        'email' => $this->contact->email,
                        'send_email' => true,
                    ],
                ],
                'tags' => [$this->encodePrimaryKey($tag->id)],
            ]);

        $response->assertStatus(200);

        $tags = $response->json('data.tags');
        $this->assertCount(1, $tags);
        $this->assertSame($this->encodePrimaryKey($tag->id), $tags[0]['id']);
        $this->assertSame('client-api', $tags[0]['name']);
    }

    public function testTagsIncludeParameterIsIgnoredBecauseTagsAreSerializedAsFields(): void
    {
        $tag = $this->makeTag(Invoice::class, 'invoice-include');
        $this->invoice->syncTags([$this->encodePrimaryKey($tag->id)]);

        $response = $this->withHeaders($this->headers())
            ->getJson('/api/v1/invoices/'.$this->encodePrimaryKey($this->invoice->id).'?include=tags');

        $response->assertStatus(200);

        $tags = $response->json('data.tags');
        $this->assertCount(1, $tags);
        $this->assertSame($this->encodePrimaryKey($tag->id), $tags[0]['id']);
        $this->assertSame('invoice-include', $tags[0]['name']);
    }

    public function testNestedTagsIncludeParameterPreservesParentInclude(): void
    {
        $tag = $this->makeTag(get_class($this->client), 'client-include');
        $this->client->syncTags([$this->encodePrimaryKey($tag->id)]);

        $response = $this->withHeaders($this->headers())
            ->getJson('/api/v1/invoices/'.$this->encodePrimaryKey($this->invoice->id).'?include=client.tags');

        $response->assertStatus(200);
        $this->assertSame($this->encodePrimaryKey($this->client->id), $response->json('data.client.id'));

        $tags = $response->json('data.client.tags');
        $this->assertCount(1, $tags);
        $this->assertSame($this->encodePrimaryKey($tag->id), $tags[0]['id']);
        $this->assertSame('client-include', $tags[0]['name']);
    }

    public function testVendorContactsDotIncludeStillWorks(): void
    {
        $response = $this->withHeaders($this->headers())
            ->getJson('/api/v1/purchase_orders/'.$this->encodePrimaryKey($this->purchase_order->id).'?include=vendor.contacts');

        $response->assertStatus(200);
        $this->assertSame($this->encodePrimaryKey($this->vendor->id), $response->json('data.vendor.id'));
        $this->assertNotEmpty($response->json('data.vendor.contacts'));
    }

    public function testMalformedTagsIncludeParameterIsIgnored(): void
    {
        foreach (['tags.client', 'client.tags.contacts', 'vendor.contacts.tags'] as $include) {
            $response = $this->withHeaders($this->headers())
                ->getJson('/api/v1/purchase_orders/'.$this->encodePrimaryKey($this->purchase_order->id).'?include='.$include);

            $response->assertStatus(200);
            $this->assertArrayNotHasKey('vendor', $response->json('data'));
        }
    }

    public function testBankIntegrationIncludeUsesTransactionRelationForTagEagerLoads(): void
    {
        $tag = $this->makeTag(get_class($this->bank_transaction), 'bank-transaction-include');
        $this->bank_transaction->syncTags([$this->encodePrimaryKey($tag->id)]);

        $response = $this->withHeaders($this->headers())
            ->getJson('/api/v1/bank_integrations?include=bank_transactions');

        $response->assertStatus(200);

        $bank_integration = collect($response->json('data'))
            ->firstWhere('id', $this->encodePrimaryKey($this->bank_integration->id));

        $this->assertNotNull($bank_integration);

        $bank_transaction = collect($bank_integration['bank_transactions'] ?? [])
            ->firstWhere('id', $this->encodePrimaryKey($this->bank_transaction->id));

        $this->assertNotNull($bank_transaction);
        $this->assertSame($this->encodePrimaryKey($tag->id), $bank_transaction['tags'][0]['id']);
    }

    public function testArchivedTagIsHiddenFromTagIndexButReturnedOnTaskPayload(): void
    {
        $tag = $this->makeTag(Task::class, 'archived-task', '#00ff00');
        $this->task->syncTags([$this->encodePrimaryKey($tag->id)]);

        $tag->delete();

        $index_response = $this->withHeaders($this->headers())
            ->getJson('/api/v1/tags?entity_type=task');

        $index_response->assertStatus(200);
        $index_ids = collect($index_response->json('data'))->pluck('id')->all();

        $this->assertNotContains($this->encodePrimaryKey($tag->id), $index_ids);

        $entity_response = $this->withHeaders($this->headers())
            ->getJson('/api/v1/tasks/'.$this->encodePrimaryKey($this->task->id));

        $entity_response->assertStatus(200);

        $tags = $entity_response->json('data.tags');

        $this->assertCount(1, $tags);
        $this->assertSame($this->encodePrimaryKey($tag->id), $tags[0]['id']);
        $this->assertSame('archived-task', $tags[0]['name']);
        $this->assertSame('#00ff00', $tags[0]['color']);
    }

    public function testTaskUpdateRespectsArchivedTagInTagsPayload(): void
    {
        $archived_tag = $this->makeTag(Task::class, 'archived-task');
        $active_tag = $this->makeTag(Task::class, 'active-task');
        $this->task->syncTags([$this->encodePrimaryKey($archived_tag->id)]);

        $archived_tag->delete();

        $response = $this->withHeaders($this->headers())
            ->putJson('/api/v1/tasks/'.$this->encodePrimaryKey($this->task->id), [
                'description' => 'Archived tag should survive update',
                'tags' => [
                    $this->encodePrimaryKey($archived_tag->id),
                    $this->encodePrimaryKey($active_tag->id),
                ],
            ]);

        $response->assertStatus(200);

        $returned_ids = collect($response->json('data.tags'))->pluck('id')->all();

        $this->assertEqualsCanonicalizing([
            $this->encodePrimaryKey($archived_tag->id),
            $this->encodePrimaryKey($active_tag->id),
        ], $returned_ids);
    }

    public function testTaskUpdateWithEmptyTagsDetachesArchivedTag(): void
    {
        $archived_tag = $this->makeTag(Task::class, 'archived-detach');
        $this->task->syncTags([$this->encodePrimaryKey($archived_tag->id)]);

        $archived_tag->delete();

        $response = $this->withHeaders($this->headers())
            ->putJson('/api/v1/tasks/'.$this->encodePrimaryKey($this->task->id), [
                'tags' => [],
            ]);

        $response->assertStatus(200);

        $this->assertSame([], $response->json('data.tags'));
        $this->assertDatabaseMissing('taggables', [
            'tag_id' => $archived_tag->id,
            'taggable_id' => $this->task->id,
            'taggable_type' => Task::class,
        ]);
    }

    public function testProjectUpdateRespectsArchivedTagInTagsPayload(): void
    {
        $archived_tag = $this->makeTag(Project::class, 'archived-project');
        $this->project->syncTags([$this->encodePrimaryKey($archived_tag->id)]);

        $archived_tag->delete();

        $response = $this->withHeaders($this->headers())
            ->putJson('/api/v1/projects/'.$this->encodePrimaryKey($this->project->id), [
                'tags' => [$this->encodePrimaryKey($archived_tag->id)],
            ]);

        $response->assertStatus(200);

        $tags = $response->json('data.tags');

        $this->assertCount(1, $tags);
        $this->assertSame($this->encodePrimaryKey($archived_tag->id), $tags[0]['id']);
    }

    public function testDeletingTagCascadesPivot(): void
    {
        $task = $this->task;
        $tag = $this->makeTag(Task::class, 'urgent');
        $task->syncTags([$this->encodePrimaryKey($tag->id)]);

        $this->assertDatabaseHas('taggables', [
            'tag_id' => $tag->id,
            'taggable_id' => $task->id,
            'taggable_type' => Task::class,
        ]);

        $tag->forceDelete();

        $this->assertDatabaseMissing('taggables', ['tag_id' => $tag->id]);
    }

    public function testTagIdsFilterReturnsOnlyTaggedTasks(): void
    {
        Task::query()->where('company_id', $this->company->id)->forceDelete();

        $tag = $this->makeTag(Task::class, 'filter-tag');

        $tagged = Task::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'description' => 'tagged',
        ]);
        $tagged->syncTags([$this->encodePrimaryKey($tag->id)]);

        Task::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'description' => 'untagged',
        ]);

        $response = $this->withHeaders($this->headers())
            ->getJson('/api/v1/tasks?tag_ids='.$this->encodePrimaryKey($tag->id));

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertCount(1, $data);
        $this->assertSame($this->encodePrimaryKey($tagged->id), $data[0]['id']);
    }

    public function testTagIdsFilterAcceptsMultipleIdsAsOr(): void
    {
        Task::query()->where('company_id', $this->company->id)->forceDelete();

        $tagA = $this->makeTag(Task::class, 'tag-a');
        $tagB = $this->makeTag(Task::class, 'tag-b');
        $tagC = $this->makeTag(Task::class, 'tag-c');

        $taskA = Task::factory()->create(['user_id' => $this->user->id, 'company_id' => $this->company->id]);
        $taskA->syncTags([$this->encodePrimaryKey($tagA->id)]);

        $taskB = Task::factory()->create(['user_id' => $this->user->id, 'company_id' => $this->company->id]);
        $taskB->syncTags([$this->encodePrimaryKey($tagB->id)]);

        $taskC = Task::factory()->create(['user_id' => $this->user->id, 'company_id' => $this->company->id]);
        $taskC->syncTags([$this->encodePrimaryKey($tagC->id)]);

        $filter = $this->encodePrimaryKey($tagA->id).','.$this->encodePrimaryKey($tagB->id);

        $response = $this->withHeaders($this->headers())
            ->getJson('/api/v1/tasks?tag_ids='.$filter);

        $response->assertStatus(200);

        $returned = collect($response->json('data'))->pluck('id')->all();

        $this->assertCount(2, $returned);
        $this->assertContains($this->encodePrimaryKey($taskA->id), $returned);
        $this->assertContains($this->encodePrimaryKey($taskB->id), $returned);
        $this->assertNotContains($this->encodePrimaryKey($taskC->id), $returned);
    }

    public function testTagIdsFilterIsNoopWhenEmpty(): void
    {
        Task::query()->where('company_id', $this->company->id)->forceDelete();

        Task::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
        ]);

        $response = $this->withHeaders($this->headers())
            ->getJson('/api/v1/tasks?tag_ids=');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    public function testTagIdsFilterScopedToCompanyTags(): void
    {
        Task::query()->where('company_id', $this->company->id)->forceDelete();

        $tag = $this->makeTag(Task::class, 'in-company');

        $tagged = Task::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
        ]);
        $tagged->syncTags([$this->encodePrimaryKey($tag->id)]);

        $response = $this->withHeaders($this->headers())
            ->getJson('/api/v1/tasks?tag_ids='.$this->encodePrimaryKey(999999999));

        $response->assertStatus(200);
        $this->assertCount(0, $response->json('data'));
    }

    public function testTagIdsFilterAppliesToProjects(): void
    {
        Project::query()->where('company_id', $this->company->id)->forceDelete();

        $tag = $this->makeTag(Project::class, 'proj-tag');

        $tagged = Project::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
        ]);
        $tagged->syncTags([$this->encodePrimaryKey($tag->id)]);

        Project::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
        ]);

        $response = $this->withHeaders($this->headers())
            ->getJson('/api/v1/projects?tag_ids='.$this->encodePrimaryKey($tag->id));

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertCount(1, $data);
        $this->assertSame($this->encodePrimaryKey($tagged->id), $data[0]['id']);
    }

    public function testTagIdsFilterAppliesToInvoices(): void
    {
        $tag = $this->makeTag(Invoice::class, 'invoice-filter');
        $this->invoice->syncTags([$this->encodePrimaryKey($tag->id)]);

        Invoice::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'line_items' => $this->buildLineItems(),
            'uses_inclusive_taxes' => false,
        ]);

        $response = $this->withHeaders($this->headers())
            ->getJson('/api/v1/invoices?tag_ids='.$this->encodePrimaryKey($tag->id));

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame($this->encodePrimaryKey($this->invoice->id), $data[0]['id']);
    }

    public function testDeletingTaskLeavesTagCatalogIntact(): void
    {
        $task = $this->task;
        $tag = $this->makeTag(Task::class, 'urgent');
        $task->syncTags([$this->encodePrimaryKey($tag->id)]);

        $task->forceDelete();

        $this->assertDatabaseHas('tags', ['id' => $tag->id]);
        $this->assertDatabaseMissing('taggables', [
            'tag_id' => $tag->id,
            'taggable_id' => $task->id,
            'taggable_type' => Task::class,
        ]);
        $this->assertSame(0, $tag->tasks()->count());
    }
}
