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

namespace App\Transformers;

use App\Utils\Traits\MakesHash;
use League\Fractal\TransformerAbstract;

class EntityTransformer extends TransformerAbstract
{
    use MakesHash;

    protected $serializer;

    public const API_SERIALIZER_ARRAY = 'array';

    public const API_SERIALIZER_JSON = 'json';

    public function __construct($serializer = null)
    {
        $this->serializer = $serializer;
    }

    protected function includeCollection($data, $transformer, $entityType)
    {
        if ($this->serializer && $this->serializer != self::API_SERIALIZER_JSON) {
            $entityType = null;
        }

        return $this->collection($data, $transformer, $entityType);
    }

    protected function includeItem($data, $transformer, $entityType)
    {
        if ($this->serializer && $this->serializer != self::API_SERIALIZER_JSON) {
            $entityType = null;
        }

        return $this->item($data, $transformer, $entityType);
    }

    public function getDefaultIncludes(): array
    {
        return $this->defaultIncludes;
    }

    public function getAvailableIncludes(): array
    {
        return $this->availableIncludes ?? []; //@phpstan-ignore-line
    }

    protected function getDefaults($entity) {}

    /**
     * @return array<int, array{id: string, name: string, color: string|null}>
     */
    protected function transformTags(object $entity): array
    {
        if (! method_exists($entity, 'tags') || ! ($entity->exists ?? false)) {
            return [];
        }

        $tags = $entity->relationLoaded('tags') ? $entity->tags : $entity->tags()->get();

        return $tags->map(fn ($tag) => [
            'id' => (string) $this->encodePrimaryKey($tag->id),
            'name' => (string) $tag->name,
            'color' => $tag->color,
        ])->values()->all();
    }
}
