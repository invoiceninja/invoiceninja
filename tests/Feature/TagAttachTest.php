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
                'tags' => [$this->encodePrimaryKey($tag->id)],
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
                'tags' => [$this->encodePrimaryKey($tag->id)],
            ]);

        $response->assertStatus(200);
        $tags = $response->json('data.tags');
        $this->assertCount(1, $tags);
        $this->assertSame($this->encodePrimaryKey($tag->id), $tags[0]['id']);
        $this->assertSame('urgent', $tags[0]['name']);
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
                'tags' => [$this->encodePrimaryKey($tag->id)],
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