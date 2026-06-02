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

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class Tag.
 *
 * @property int $id
 * @property int $company_id
 * @property int $user_id
 * @property string $entity_type
 * @property string $name
 * @property string|null $color
 * @property bool $is_deleted
 * @property int|null $created_at
 * @property int|null $updated_at
 * @property int|null $deleted_at
 * @property-read mixed $hashed_id
 * @method static \Database\Factories\TagFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|Tag query()
 * @method static \Illuminate\Database\Eloquent\Builder|Tag withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel company()
 * @mixin \Eloquent
 */
class Tag extends BaseModel
{
    use SoftDeletes;
    use Filterable;

    /**
     * List of FQCNs that may be referenced by `entity_type`.
     *
     * @var array<class-string>
     */
    public const TAGGABLE_TYPES = [
        'task' =>Task::class,
        'project' => Project::class,
    ];

    /**
     * Translate a taggable reference into its canonical FQCN. Accepts either a
     * short key (e.g. "task") or an already-resolved FQCN, and returns null for
     * anything outside the catalog so callers can reject it.
     */
    public static function normalizeEntityType(string $entity_type): ?string
    {
        if (array_key_exists($entity_type, self::TAGGABLE_TYPES)) {
            return self::TAGGABLE_TYPES[$entity_type];
        }

        return in_array($entity_type, self::TAGGABLE_TYPES, true) ? $entity_type : null;
    }

    public $timestamps = true;

    protected $fillable = [
        'entity_type',
        'name',
        'color',
    ];

    public function company(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function tasks(): \Illuminate\Database\Eloquent\Relations\MorphToMany
    {
        return $this->morphedByMany(Task::class, 'taggable');
    }

    public function projects(): \Illuminate\Database\Eloquent\Relations\MorphToMany
    {
        return $this->morphedByMany(Project::class, 'taggable');
    }
}
