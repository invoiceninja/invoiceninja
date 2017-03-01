<?php

namespace App\Http\Requests;

class TaskRequest extends EntityRequest
{
    protected $entityType = ENTITY_TASK;

    public function sanitize()
    {
        $input = $this->all();

        // check if we're creating a new project
        if ($this->input('project_id') == '-1' && $this->user()->can('create', ENTITY_PROJECT)) {
            $project = app('App\Services\ProjectService')->save([
                'name' => $this->input('project_name'),
                'client_id' => $this->input('client')
            ]);
            $input['project_id'] = $project->public_id;
        }

        $this->replace($input);

        return $this->all();
    }
}
