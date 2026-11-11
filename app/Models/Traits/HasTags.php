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

namespace App\Models\Traits;

use App\Models\Tag;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Validation\ValidationException;

/**
 *
 * @property int $company_id
 */
trait HasTags
{
    use MakesHash;

    public static function bootHasTags(): void
    {
        if (! method_exists(static::class, 'forceDeleted')) {
            return;
        }

        static::forceDeleted(function (object $model): void {
            $model->tags()->detach();
        });
    }

    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable')
            ->withTrashed();
            // ->where('tags.is_deleted', false);
    }

    /**
     * Replace the entity's tags with the given ids. Every id must exist in the
     * catalog for (company_id, static::class); otherwise throws
     * ValidationException so the caller can short-circuit before any other
     * writes happen.
     *
     * @param  array<int|string> $tag_ids
     */
    public function syncTags(array $tag_ids): void
    {
        $tag_ids = $this->normalizeTagIds($tag_ids);

        if (empty($tag_ids)) {
            $this->tags()->sync([]);
            return;
        }

        $this->tags()->sync(static::resolveTagIds($tag_ids, (int) $this->company_id));
    }

    /**
     * Pre-flight ownership check used by repositories that want to abort
     * before any other writes. Returns the resolved tag ids on success.
     *
     * @param  array<int|string> $tag_ids
     * @return array<int>
     */
    public static function resolveTagIds(array $tag_ids, int $company_id): array
    {
        $tag_ids = (new static())->normalizeTagIds($tag_ids);

        if (empty($tag_ids)) {
            return [];
        }

        $found = Tag::withTrashed()
            ->whereIn('id', $tag_ids)
            ->where('company_id', $company_id)
            ->whereIn('entity_type', [static::class, Tag::GLOBAL_ENTITY_TYPE])
            // ->where('entity_type', static::class)
            ->where('is_deleted', false)
            ->pluck('id');

        if ($found->count() !== count($tag_ids)) {
            throw ValidationException::withMessages([
                'tags' => ['One or more tags are invalid for this entity.'],
            ]);
        }

        return $found->map(fn ($id) => (int) $id)->all();
    }

    /**
     * @param  array<mixed> $tag_ids
     * @return array<int>
     */
    private function normalizeTagIds(array $tag_ids): array
    {
        $out = [];

        foreach ($tag_ids as $tag_id) {
            if (is_int($tag_id)) {
                $out[] = (int) $tag_id;
                continue;
            }

            if (is_string($tag_id)) {
                $decoded = $this->decodePrimaryKey($tag_id);

                if (is_int($decoded) || (is_string($decoded) && ctype_digit($decoded))) {
                    $out[] = (int) $decoded;
                    continue;
                }
            }

            throw ValidationException::withMessages([
                'tags' => ['One or more tags are invalid for this entity.'],
            ]);
        }

        $out = array_filter($out, fn (int $tag_id): bool => $tag_id > 0);

        if (count($out) !== count($tag_ids)) {
            throw ValidationException::withMessages([
                'tags' => ['One or more tags are invalid for this entity.'],
            ]);
        }

        return array_values(array_unique($out));
    }
}
