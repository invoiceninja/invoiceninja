<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

#[CoversNothing]
class OpenApiSpecMenuTest extends TestCase
{
    public function testTaskStatusUsesSingleMenuTag(): void
    {
        $misc = $this->yamlFile('openapi/misc/misc.yaml');
        $tag_names = array_column($misc['tags'], 'name');

        $this->assertContains('task_status', $tag_names);
        $this->assertNotContains('task_statuss', $tag_names);
        $this->assertSame(1, count(array_filter(
            $misc['tags'],
            static fn (array $tag): bool => ($tag['x-displayName'] ?? null) === 'Task Status'
        )));

        foreach (['openapi/paths.yaml', 'openapi/api-docs.yaml'] as $path) {
            $operation_tags = $this->taskStatusOperationTags($path);

            $this->assertNotEmpty($operation_tags);

            foreach ($operation_tags as $tags) {
                $this->assertContains('task_status', $tags);
                $this->assertNotContains('task_statuss', $tags);
            }
        }
    }

    public function testTagsMenuEntryIsAlphabeticallyPositioned(): void
    {
        $misc = $this->yamlFile('openapi/misc/misc.yaml');
        $tag_positions = array_flip(array_column($misc['tags'], 'name'));

        $this->assertArrayHasKey('system_logs', $tag_positions);
        $this->assertArrayHasKey('tags', $tag_positions);
        $this->assertArrayHasKey('task_schedulers', $tag_positions);
        $this->assertArrayHasKey('task_status', $tag_positions);

        $this->assertLessThan($tag_positions['tags'], $tag_positions['system_logs']);
        $this->assertLessThan($tag_positions['task_schedulers'], $tag_positions['tags']);
        $this->assertLessThan($tag_positions['task_status'], $tag_positions['tags']);
    }

    private function taskStatusOperationTags(string $path): array
    {
        $document = $this->yamlFile($path);
        $operation_tags = [];

        foreach ($document['paths'] as $openapi_path => $methods) {
            if (! str_starts_with((string) $openapi_path, '/api/v1/task_statuses')) {
                continue;
            }

            foreach ($methods as $method => $operation) {
                if ($method === 'parameters' || ! is_array($operation) || ! isset($operation['tags'])) {
                    continue;
                }

                $operation_tags[] = $operation['tags'];
            }
        }

        return $operation_tags;
    }

    private function yamlFile(string $path): array
    {
        return Yaml::parseFile(dirname(__DIR__, 2) . '/' . $path);
    }
}
