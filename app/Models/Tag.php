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
        Task::class,
        Project::class,
    ];

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
