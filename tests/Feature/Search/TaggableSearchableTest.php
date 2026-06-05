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

namespace Tests\Feature\Search;

use App\Models\Project;
use App\Models\Tag;
use App\Models\Task;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\MockAccountData;
use Tests\TestCase;

class TaggableSearchableTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();
    }

    private function makeTag(string $name, string $entityType, bool $isDeleted = false): Tag
    {
        return Tag::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'entity_type' => $entityType,
            'name' => $name,
            'is_deleted' => $isDeleted,
        ]);
    }

    public function testTaskSearchableArrayIncludesTagNames(): void
    {
        $urgent = $this->makeTag('urgent-task-tag', Task::class);
        $backend = $this->makeTag('backend-task-tag', Task::class);

        $this->task->syncTags([$urgent->id, $backend->id]);

        $tags = $this->task->fresh()->toSearchableArray()['tags'];

        $this->assertIsArray($tags);
        $this->assertContains('urgent-task-tag', $tags);
        $this->assertContains('backend-task-tag', $tags);
    }

    public function testProjectSearchableArrayIncludesTagNames(): void
    {
        $phase1 = $this->makeTag('phase1-project-tag', Project::class);

        $this->project->syncTags([$phase1->id]);

        $tags = $this->project->fresh()->toSearchableArray()['tags'];

        $this->assertIsArray($tags);
        $this->assertContains('phase1-project-tag', $tags);
    }

    public function testSearchableArrayExcludesDeletedTags(): void
    {
        $active = $this->makeTag('active-tag', Task::class);
        $deleted = $this->makeTag('deleted-tag', Task::class, isDeleted: true);

        // Attach both directly to the pivot (syncTags rejects is_deleted tags).
        $this->task->tags()->sync([$active->id, $deleted->id]);

        $tags = $this->task->fresh()->toSearchableArray()['tags'];

        $this->assertContains('active-tag', $tags);
        $this->assertContains('deleted-tag', $tags);
    }

    public function testSearchableArrayTagsAreAPlainListWhenEmpty(): void
    {
        $tags = $this->task->fresh()->toSearchableArray()['tags'];

        $this->assertSame([], $tags);
    }
}
