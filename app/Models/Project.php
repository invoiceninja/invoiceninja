<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Laracasts\Presenter\PresentableTrait;

/**
 * Class ExpenseCategory.
 */
class Project extends EntityModel
{
    // Expense Categories
    use SoftDeletes;
    use PresentableTrait;

    /**
     * @var array
     */
    protected $dates = ['deleted_at'];

    /**
     * @var array
     */
    protected $fillable = [
        'name',
    ];

    /**
     * @var string
     */
    protected $presenter = 'App\Ninja\Presenters\EntityPresenter';

    /**
     * @return mixed
     */
    public function getEntityType()
    {
        return ENTITY_PROJECT;
    }

    /**
     * @return string
     */
    public function getRoute()
    {
        return "/projects/{$this->public_id}/edit";
    }

    /**
     * @return mixed
     */
    public function client()
    {
        return $this->belongsTo('App\Models\Client')->withTrashed();
    }
}

Project::creating(function ($project) {
    $project->setNullValues();
});

Project::updating(function ($project) {
    $project->setNullValues();
});
