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

use App\Models\Client;
use App\Models\Document;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Quote;
use App\Models\Task;
use App\Models\User;
use App\Utils\Traits\MakesHash;
use League\Fractal\Resource\Item;

/**
 * class ProjectTransformer.
 */
class ProjectProfileTransformer extends ProjectTransformer
{
    use MakesHash;

    protected array $defaultIncludes = [];

    /**
     * @var array
     */
    protected array $availableIncludes = [
        'client',
        'user',
        'assigned_user',
    ];

    public function includeUser(Project $project): ?Item
    {
        $transformer = new UserProfileTransformer($this->serializer, $this->user);

        if (!$project->user) { //@phpstan-ignore-line
            return null;
        }

        return $this->includeItem($project->user, $transformer, User::class);
    }

    public function includeAssignedUser(Project $project): ?Item
    {
        $transformer = new UserProfileTransformer($this->serializer, $this->user);

        if (!$project->assigned_user) { //@phpstan-ignore-line
            return null;
        }

        return $this->includeItem($project->assigned_user, $transformer, User::class);
    }

    public function includeClient(Project $project): ?\League\Fractal\Resource\Item
    {

        if (!$project->client) {
            nlog("Project {$project->hashed_id} does not have a client attached - this project is in a bad state");
            return null;
        }

        $transformer = new ClientTransformer($this->serializer, $this->user);

        if($this->user?->cannot('view', $project->client)) {
            $transformer = new ClientProfileTransformer($this->serializer, $this->user);
        }

        return $this->includeItem($project->client, $transformer, Client::class);
    }

    public function transform(Project $project)
    {
        return array_merge(parent::transform($project), [
            'task_rate' => (float) 0,
            'private_notes' => '',
            'public_notes' => '',
            'budgeted_hours' => (float) 0,
            'custom_value1' => '',
            'custom_value2' => '',
            'custom_value3' => '',
            'custom_value4' => '',
            'current_hours' => 0,
            'budgeted_amount' => (float) 0,
        ]);
    }

}
