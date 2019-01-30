<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Laracasts\Presenter\PresentableTrait;

/**
 * Class ExpenseCategory.
 */
class ProposalTemplate extends EntityModel
{
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
        'private_notes',
        'html',
        'css',
    ];

    /**
     * @var string
     */
    protected $presenter = 'App\Ninja\Presenters\ProposalTemplatePresenter';

    /**
     * @return mixed
     */
    public function getEntityType()
    {
        return ENTITY_PROPOSAL_TEMPLATE;
    }

    /**
     * @return string
     */
    public function getRoute()
    {
        return "/proposals/templates/{$this->public_id}";
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function account()
    {
        return $this->belongsTo('App\Models\Account');
    }

    public function getDisplayName()
    {
        return $this->name;
    }
}

/*
Proposal::creating(function ($project) {
    $project->setNullValues();
});

Proposal::updating(function ($project) {
    $project->setNullValues();
});
*/
