<?php

namespace App\Http\Requests;

use App\Models\Client;

class TaskRequest extends EntityRequest
{
    protected $entityType = ENTITY_TASK;

    public function sanitize()
    {
        $input = $this->all();

        // check if we're creating a new project
        if ($this->project_id == '-1'
            && trim($this->project_name)
            && $this->user()->can('create', ENTITY_PROJECT))
        {
            $project = app('App\Ninja\Repositories\ProjectRepository')->save([
                'name' => $this->project_name,
                'client_id' => Client::getPrivateId($this->client),
            ]);
            $input['project_id'] = $project->public_id;
        }

        $this->replace($input);

        return $this->all();
    }
}
