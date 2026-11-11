<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Mcp;

use Symfony\Component\Yaml\Yaml;

/** Compact discovery hints from the application's existing OpenAPI document. */
final class OpenApi
{
    private static ?array $document = null;

    private array $source;

    public function __construct(?array $source = null)
    {
        $this->source = $source ?? (self::$document ??= Yaml::parseFile(base_path('openapi/api-docs.yaml')));
    }

    public function operation(array $spec): array
    {
        $canonical = fn ($path) => preg_replace('/\{[^}]+\}/', '{}', ltrim($path, '/'));
        foreach ($this->source['paths'] ?? [] as $path => $methods) {
            if ($canonical($path) === $canonical($spec['uri'])) {
                $operation = $methods[strtolower($spec['method'])] ?? [];
                if ($operation) {
                    $operation['parameters'] = array_merge($methods['parameters'] ?? [], $operation['parameters'] ?? []);
                }
                return $operation;
            }
        }
        return [];
    }

    public function inputSchema(array $operations): array
    {
        $parameters = $query = $data = [];
        foreach ($operations as $spec) {
            $operation = $this->operation($spec);
            foreach ($spec['parameters'] as $name) {
                $parameters[$name] = ['type' => 'string'];
            }
            foreach ($operation['parameters'] ?? [] as $parameter) {
                $parameter = $this->resolve($parameter);
                if (($parameter['in'] ?? null) === 'query' && isset($parameter['name'])) {
                    $this->merge($query, $parameter['name'], $this->compact($parameter['schema'] ?? []));
                }
            }
            $body = $this->resolve($operation['requestBody'] ?? []);
            $schema = $this->resolve($body['content']['application/json']['schema'] ?? []);
            foreach ($schema['properties'] ?? [] as $name => $property) {
                if (!($property['readOnly'] ?? false)) {
                    $this->merge($data, $name, $this->compact($property));
                }
            }
        }
        return [
            'type' => 'object',
            'properties' => [
                'operation' => ['type' => 'string', 'enum' => array_keys($operations)],
                'parameters' => ['type' => 'object', 'properties' => (object)$parameters, 'description' => 'Path parameter names are listed per operation. Use hashed entity IDs.'],
                'query' => ['type' => 'object', 'properties' => (object)array_map(fn ($schema) => (object)$schema, $query), 'description' => 'REST filters and pagination. Compact OpenAPI hints; original validation applies.'],
                'data' => ['type' => 'object', 'properties' => (object)array_map(fn ($schema) => (object)$schema, $data), 'description' => 'REST JSON body. Fields vary by operation; original FormRequest validation applies.'],
            ],
            'required' => ['operation'],
        ];
    }

    private function merge(array &$properties, string $name, array $schema): void
    {
        // Different operations may give the same field different types. Do not narrow REST.
        $properties[$name] = isset($properties[$name]) && $properties[$name] != $schema ? [] : $schema;
    }

    private function compact(array $schema, int $depth = 0): array
    {
        $schema = $this->resolve($schema);
        $result = [];
        if (isset($schema['type']) && in_array($schema['type'], ['string', 'integer', 'number', 'boolean', 'array', 'object'], true)) {
            $result['type'] = ($schema['nullable'] ?? false) ? [$schema['type'], 'null'] : $schema['type'];
        }
        if (($schema['type'] ?? null) === 'array') {
            $result['items'] = $depth < 1 ? (object)$this->compact($schema['items'] ?? [], $depth + 1) : (object)[];
        }
        // Nested entity definitions remain in OpenAPI instead of duplicating them in discovery.
        return $result;
    }

    private function resolve(array $value): array
    {
        $seen = [];
        while (isset($value['$ref'])) {
            $ref = $value['$ref'];
            if (!is_string($ref) || !str_starts_with($ref, '#/') || isset($seen[$ref])) {
                return [];
            }
            $seen[$ref] = true;
            $target = $this->source;
            foreach (explode('/', substr($ref, 2)) as $part) {
                $part = str_replace(['~1', '~0'], ['/', '~'], $part);
                $target = is_array($target) ? ($target[$part] ?? null) : null;
            }
            if (!is_array($target)) {
                return [];
            }
            $value = $target;
        }
        return $value;
    }
}
