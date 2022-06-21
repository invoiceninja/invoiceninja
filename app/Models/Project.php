<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laracasts\Presenter\PresentableTrait;

/**
 * Class Project.
 */
class Project extends BaseModel
{
    use SoftDeletes;
    use PresentableTrait;
    use Filterable;

    /**
     * @var array
     */
    /**
     * @var array
     */
    protected $fillable = [
        'name',
        'client_id',
        'task_rate',
        'private_notes',
        'public_notes',
        'due_date',
        'budgeted_hours',
        'custom_value1',
        'custom_value2',
        'custom_value3',
        'custom_value4',
        'assigned_user_id',
        'color',
        'number',
    ];

    public function getEntityType()
    {
        return self::class;
    }

    protected $touches = [];

    /**
     * @return BelongsTo
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return mixed
     */
    public function client()
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class)->withTrashed();
    }

    public function project()
    {
        return $this->belongsTo(self::class)->withTrashed();
    }

    public function documents()
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

    public function translate_entity()
    {
        return ctrans('texts.project');
    }
}
