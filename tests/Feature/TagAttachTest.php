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
use App\Models\Project;
use App\Models\Tag;
use App\Models\Task;
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

    public function testTagIdsFilterIsNoopOnEntityWithoutTags(): void
    {
        $tag = $this->makeTag(Task::class, 'noop');

        $response = $this->withHeaders($this->headers())
            ->getJson('/api/v1/invoices?tag_ids='.$this->encodePrimaryKey($tag->id));

        $response->assertStatus(200);
        $this->assertIsArray($response->json('data'));
    }

    public function testDeletingTaskLeavesTagCatalogIntact(): void
    {
        $task = $this->task;
        $tag = $this->makeTag(Task::class, 'urgent');
        $task->syncTags([$this->encodePrimaryKey($tag->id)]);

        $task->forceDelete();

        $this->assertDatabaseHas('tags', ['id' => $tag->id]);
        $this->assertSame(0, $tag->tasks()->count());
    }
}