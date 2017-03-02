<?php

namespace App\Http\Requests;

use App\Models\Client;
use App\Models\Project;

class TaskRequest extends EntityRequest
{
    protected $entityType = ENTITY_TASK;

    public function sanitize()
    {
        $input = $this->all();

        // check if we're creating a new project
        if ($this->project_id == '-1') {
            $project = [
                'name' => trim($this->project_name),
                'client_id' => Client::getPrivateId($this->client),
            ];
            if (Project::validate($project) === true) {
                $project = app('App\Ninja\Repositories\ProjectRepository')->save($project);
                $input['project_id'] = $project->public_id;
            } else {
                $input['project_id'] = null;
            }
        }

        $this->replace($input);

        return $this->all();
    }
}
