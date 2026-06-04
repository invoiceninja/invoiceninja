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

use App\Http\Controllers\TagController;
use App\Models\Project;
use App\Models\Tag;
use App\Models\Task;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Session;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\MockAccountData;
use Tests\TestCase;

#[CoversClass(TagController::class)]
class TagApiTest extends TestCase
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

    public function testStoreTag(): void
    {
        $payload = [
            'entity_type' => 'task',
            'name' => 'urgent',
            'color' => '#ff0000',
        ];

        $response = $this->withHeaders($this->headers())->postJson('/api/v1/tags', $payload);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertSame('urgent', $arr['data']['name']);
        $this->assertSame(Task::class, $arr['data']['entity_type']);
        $this->assertSame('#ff0000', $arr['data']['color']);
    }

    public function testTagCreatedViaApiCanBeAttachedToTask(): void
    {
        $create = $this->withHeaders($this->headers())->postJson('/api/v1/tags', [
            'entity_type' => 'task',
            'name' => 'inline-created',
        ]);

        $create->assertStatus(200);
        $tag_id = $create->json('data.id');

        $response = $this->withHeaders($this->headers())
            ->putJson('/api/v1/tasks/'.$this->encodePrimaryKey($this->task->id), [
                'tags' => [$tag_id],
            ]);

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.tags'));
        $this->assertSame($tag_id, $response->json('data.tags.0.id'));
    }

    public function testStoreRejectsDisallowedEntityType(): void
    {
        $payload = [
            'entity_type' => 'App\\Models\\Invoice',
            'name' => 'follow-up',
        ];

        $response = $this->withHeaders($this->headers())->postJson('/api/v1/tags', $payload);

        $response->assertStatus(422);
    }

    public function testStoreRejectsBadColor(): void
    {
        $payload = [
            'entity_type' => 'task',
            'name' => 'urgent',
            'color' => 'red',
        ];

        $response = $this->withHeaders($this->headers())->postJson('/api/v1/tags', $payload);

        $response->assertStatus(422);
    }

    public function testNullColorAllowed(): void
    {
        $payload = [
            'entity_type' => 'task',
            'name' => 'no-color',
            'color' => null,
        ];

        $response = $this->withHeaders($this->headers())->postJson('/api/v1/tags', $payload);

        $response->assertStatus(200);
        $this->assertNull($response->json('data.color'));
    }

    public function testSameNameAcrossDifferentEntityTypesIsAllowed(): void
    {
        $this->withHeaders($this->headers())->postJson('/api/v1/tags', [
            'entity_type' => 'task',
            'name' => 'shared',
        ])->assertStatus(200);

        $this->withHeaders($this->headers())->postJson('/api/v1/tags', [
            'entity_type' => 'project',
            'name' => 'shared',
        ])->assertStatus(200);

        $this->assertSame(2, Tag::where('company_id', $this->company->id)->where('name', 'shared')->count());
    }

    public function testDuplicateNameSameEntityTypeRejected(): void
    {
        Tag::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'entity_type' => Task::class,
            'name' => 'dup',
        ]);

        $response = $this->withHeaders($this->headers())->postJson('/api/v1/tags', [
            'entity_type' => 'task',
            'name' => 'dup',
        ]);

        $response->assertStatus(422);
    }

    public function testArchivedNameSameEntityTypeRejected(): void
    {
        $tag = Tag::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'entity_type' => Task::class,
            'name' => 'archived-dup',
        ]);

        $tag->delete();

        $response = $this->withHeaders($this->headers())->postJson('/api/v1/tags', [
            'entity_type' => 'task',
            'name' => 'archived-dup',
        ]);

        $response->assertStatus(422);
    }

    public function testIndexRequiresEntityType(): void
    {
        $response = $this->withHeaders($this->headers())->getJson('/api/v1/tags');

        $response->assertStatus(422);
    }

    public function testIndexFilterByEntityType(): void
    {
        Tag::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'entity_type' => Task::class,
            'name' => 'task-tag',
        ]);
        Tag::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'entity_type' => Project::class,
            'name' => 'project-tag',
        ]);

        $response = $this->withHeaders($this->headers())
            ->getJson('/api/v1/tags?entity_type=project');

        $response->assertStatus(200);

        $rows = $response->json('data');
        $this->assertNotEmpty($rows);

        foreach ($rows as $row) {
            $this->assertSame(Project::class, $row['entity_type']);
        }
    }

    public function testUpdateTagName(): void
    {
        $tag = Tag::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'entity_type' => 'task',
            'name' => 'old',
        ]);

        $response = $this->withHeaders($this->headers())
            ->putJson('/api/v1/tags/'.$this->encodePrimaryKey($tag->id), ['name' => 'new']);

        $response->assertStatus(200);
        $this->assertSame('new', $response->json('data.name'));
    }

    public function testEntityTypeIsImmutableOnUpdate(): void
    {
        $tag = Tag::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'entity_type' => Task::class,
            'name' => 'immut',
        ]);

        $response = $this->withHeaders($this->headers())
            ->putJson('/api/v1/tags/'.$this->encodePrimaryKey($tag->id), [
                'entity_type' => 'project',
                'name' => 'immut',
            ]);

        $response->assertStatus(200);
        $this->assertSame(Task::class, $response->json('data.entity_type'));
    }

    public function testShowTag(): void
    {
        $tag = Tag::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'entity_type' => Task::class,
        ]);

        $response = $this->withHeaders($this->headers())
            ->getJson('/api/v1/tags/'.$this->encodePrimaryKey($tag->id));

        $response->assertStatus(200);
        $this->assertSame(Task::class, $response->json('data.entity_type'));
    }

    public function testDestroyTag(): void
    {
        $tag = Tag::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'entity_type' => 'task',
        ]);

        $response = $this->withHeaders($this->headers())
            ->deleteJson('/api/v1/tags/'.$this->encodePrimaryKey($tag->id));

        $response->assertStatus(200);
        $this->assertTrue($response->json('data.is_deleted'));
    }

    public function testBulkArchiveRestoreDelete(): void
    {
        $tag = Tag::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'entity_type' => 'task',
        ]);

        $ids = ['ids' => [$this->encodePrimaryKey($tag->id)]];

        $this->withHeaders($this->headers())->postJson('/api/v1/tags/bulk?action=archive', $ids)
            ->assertStatus(200)
            ->assertJsonPath('data.0.archived_at', fn ($v) => $v !== null && $v !== 0);

        $this->withHeaders($this->headers())->postJson('/api/v1/tags/bulk?action=restore', $ids)
            ->assertStatus(200)
            ->assertJsonPath('data.0.archived_at', 0);

        $this->withHeaders($this->headers())->postJson('/api/v1/tags/bulk?action=delete', $ids)
            ->assertStatus(200)
            ->assertJsonPath('data.0.is_deleted', true);
    }

    public function testCompanyDeleteCascadesTags(): void
    {
        $tag = Tag::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'entity_type' => 'task',
        ]);

        $this->company->forceDelete();

        $this->assertDatabaseMissing('tags', ['id' => $tag->id]);
    }
}
