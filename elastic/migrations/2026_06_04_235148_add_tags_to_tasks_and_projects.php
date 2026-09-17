<?php
declare(strict_types=1);

use Elastic\Adapter\Indices\Mapping;
use Elastic\Adapter\Indices\Settings;
use Elastic\Migrations\Facades\Index;
use Elastic\Migrations\MigrationInterface;

final class AddTagsToTasksAndProjects implements MigrationInterface
{
    /**
     * Run the migration.
     *
     * Tags are denormalized onto the tasks/projects search documents as the tag
     * names array (see Task/Project::toSearchableArray). The field is analyzed
     * text (so a tag word matches via the existing multi_match on *) with a
     * keyword sub-field for exact matching/aggregation.
     *
     * putMapping only registers the field; existing documents are not backfilled.
     * After deploying, reindex: php artisan elastic:import-all --model=tasks,projects
     */
    public function up(): void
    {
        foreach (['tasks', 'projects'] as $index) {
            Index::putMapping($index, static function (Mapping $mapping): void {
                $mapping->text('tags', [
                    'fields' => [
                        'keyword' => ['type' => 'keyword', 'ignore_above' => 256],
                    ],
                ]);
            });
        }
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        //
    }
}
